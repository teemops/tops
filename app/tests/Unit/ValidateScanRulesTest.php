<?php

namespace Tests\Unit;

use App\Services\ServiceRegistry;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * The validator is the guard rail for JSON-only contributions. These tests write broken
 * fixtures into the real rules directories, run the command, and assert it fails — a
 * validator that passes everything would be worse than none, because the whole point is
 * that these mistakes are otherwise invisible.
 */
class ValidateScanRulesTest extends TestCase
{
    /** @var string[] */
    private array $created = [];

    protected function tearDown(): void
    {
        foreach ($this->created as $path) {
            if (is_dir($path)) {
                File::deleteDirectory($path);
            } elseif (File::exists($path)) {
                File::delete($path);
            }
        }

        ServiceRegistry::flush();
        parent::tearDown();
    }

    private function writeService(string $name, array $document): void
    {
        $directory = base_path("rules/tasks/{$name}");
        File::makeDirectory($directory, 0755, true);
        $this->created[] = $directory;
        File::put($directory . '/tasks.json', json_encode($document, JSON_PRETTY_PRINT));
        ServiceRegistry::flush();
    }

    private function writeRuleset(string $name, array $rules): void
    {
        $path = base_path("rules/rulesets/{$name}.json");
        $this->created[] = $path;
        File::put($path, json_encode(['rules' => $rules], JSON_PRETTY_PRINT));
    }

    private function validService(string $name, string $client, array $extra = []): array
    {
        return [
            'config' => array_merge([
                'service' => $name,
                'label' => strtoupper($name),
                'client' => $client,
                'regional' => true,
                'profiles' => ['basic'],
                'start' => 'listQueues',
            ], $extra),
            'tasks' => [[
                'listQueues' => [
                    'task' => 'listQueues',
                    'id' => 'queue',
                    'items' => ['QueueUrls' => []],
                ],
            ]],
        ];
    }

    public function test_the_shipped_definitions_are_valid(): void
    {
        $this->artisan('scan:validate-rules')->assertSuccessful();
    }

    public function test_it_rejects_a_method_the_aws_sdk_does_not_have(): void
    {
        $document = $this->validService('fixturesqs', 'sqs');
        $document['tasks'][0]['listQueues']['actions'] = [
            ['getQueueAttributesTypo' => ['params' => ['QueueUrl' => '$item']]],
        ];
        $this->writeService('fixturesqs', $document);

        $this->artisan('scan:validate-rules')
            ->expectsOutputToContain("has no 'getQueueAttributesTypo' operation on 'sqs'")
            ->assertFailed();
    }

    public function test_it_rejects_a_client_key_the_aws_sdk_does_not_provide(): void
    {
        $this->writeService('fixturenope', $this->validService('fixturenope', 'notarealawsservice'));

        $this->artisan('scan:validate-rules')->assertFailed();
    }

    public function test_it_rejects_a_start_task_that_does_not_exist(): void
    {
        $this->writeService('fixturestart', $this->validService('fixturestart', 'sqs', ['start' => 'listNothing']));

        $this->artisan('scan:validate-rules')
            ->expectsOutputToContain("names 'listNothing' as its start task")
            ->assertFailed();
    }

    /**
     * The quiet failure the command exists for: a well-formed rule aimed at data no
     * tasks.json collects never matches anything, and reads as a passing check.
     */
    public function test_it_rejects_a_rule_targeting_uncollected_data(): void
    {
        $this->writeRuleset('fixtureruleset', [[
            'rule' => 'fixture-001',
            'name' => 'Nothing collects this',
            'service' => 'ec2',
            'method' => 'describeCarrierGateways',
            'severity' => 'low',
            'remediation' => 'n/a',
            'condition' => 'true',
        ]]);

        $this->artisan('scan:validate-rules')
            ->expectsOutputToContain('which no tasks.json collects')
            ->assertFailed();
    }

    public function test_it_rejects_a_duplicate_rule_id(): void
    {
        // tops-s3-001 already exists in basic.json; ids identify findings in the
        // database, so a second definition would make findings ambiguous.
        $this->writeRuleset('fixtureduplicate', [[
            'rule' => 'tops-s3-001',
            'name' => 'Duplicate',
            'service' => 's3',
            'method' => 'getPublicAccessBlock',
            'severity' => 'low',
            'remediation' => 'n/a',
            'condition' => 'true',
        ]]);

        $this->artisan('scan:validate-rules')
            ->expectsOutputToContain('reuses the rule id')
            ->assertFailed();
    }

    public function test_it_rejects_a_condition_that_is_not_valid_php(): void
    {
        $this->writeRuleset('fixturecondition', [[
            'rule' => 'fixture-002',
            'name' => 'Broken condition',
            'service' => 's3',
            'method' => 'getPublicAccessBlock',
            'severity' => 'low',
            'remediation' => 'n/a',
            'condition' => "count(\$data['Thing'] ?? []) >",
        ]]);

        $this->artisan('scan:validate-rules')
            ->expectsOutputToContain('not valid PHP')
            ->assertFailed();
    }

    public function test_it_rejects_a_rule_missing_required_fields(): void
    {
        $this->writeRuleset('fixtureincomplete', [[
            'rule' => 'fixture-003',
            'name' => 'No condition',
            'service' => 's3',
            'method' => 'getPublicAccessBlock',
        ]]);

        $this->artisan('scan:validate-rules')
            ->expectsOutputToContain("has no 'condition'")
            ->assertFailed();
    }

    public function test_it_rejects_actions_with_no_way_to_find_items(): void
    {
        $document = $this->validService('fixtureitems', 'sqs');
        unset($document['tasks'][0]['listQueues']['items']);
        $document['tasks'][0]['listQueues']['actions'] = [
            ['getQueueAttributes' => ['params' => ['QueueUrl' => '$item']]],
        ];
        $this->writeService('fixtureitems', $document);

        $this->artisan('scan:validate-rules')
            ->expectsOutputToContain("no 'items' or 'itemsPath'")
            ->assertFailed();
    }
}
