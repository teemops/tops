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

    /**
     * Files that already existed and must be put back, not deleted.
     *
     * @var array<string, string>
     */
    private array $restore = [];

    protected function tearDown(): void
    {
        foreach ($this->created as $path) {
            if (is_dir($path)) {
                File::deleteDirectory($path);
            } elseif (File::exists($path)) {
                File::delete($path);
            }
        }

        foreach ($this->restore as $path => $contents) {
            File::put($path, $contents);
        }

        ServiceRegistry::flush();
        parent::tearDown();
    }

    /**
     * Swap tips.json for a fixture, remembering the real one so tearDown restores
     * it. There is only one recommendations file, so unlike rulesets this cannot be
     * tested by adding an extra file alongside.
     */
    private function replaceRecommendations(array $recommendations): void
    {
        $path = base_path('rules/recommendations/tips.json');
        $this->restore[$path] = File::get($path);

        File::put($path, json_encode(
            ['recommendations' => $recommendations],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        ));
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

    /**
     * Roadmap N-4. This was a warning until 2026-07-30, which is exactly how 39
     * rules — every CIS rule among them — ended up producing findings with no fix
     * text. A warning nobody reads is not a guard rail.
     */
    public function test_it_rejects_a_rule_with_no_remediation(): void
    {
        $this->writeRuleset('fixtureremediation', [[
            'rule' => 'fixture-remediation-001',
            'name' => 'No remediation',
            'service' => 's3',
            'method' => 'getPublicAccessBlock',
            'severity' => 'high',
            'condition' => 'true',
        ]]);

        $this->artisan('scan:validate-rules')
            ->expectsOutputToContain("has no 'remediation'")
            ->assertFailed();
    }

    public function test_it_rejects_a_rule_whose_remediation_is_only_whitespace(): void
    {
        $this->writeRuleset('fixtureblankremediation', [[
            'rule' => 'fixture-remediation-002',
            'name' => 'Blank remediation',
            'service' => 's3',
            'method' => 'getPublicAccessBlock',
            'severity' => 'high',
            'remediation' => "   \n\t ",
            'condition' => 'true',
        ]]);

        $this->artisan('scan:validate-rules')
            ->expectsOutputToContain("has no 'remediation'")
            ->assertFailed();
    }

    /**
     * tips.json referenced a rule that no ruleset defined (tops-route53-001) for
     * months. Nothing checked it, so the guidance simply never appeared.
     */
    public function test_it_rejects_a_recommendation_referencing_an_unknown_rule(): void
    {
        $this->replaceRecommendations([[
            'name' => 'fixture-rec-001',
            'recommendation' => 'Fixture',
            'description' => 'Fixture recommendation',
            'links' => ['https://example.com'],
            'steps' => ['Do the thing'],
            'rules' => ['tops-does-not-exist-001'],
        ]]);

        $this->artisan('scan:validate-rules')
            ->expectsOutputToContain('which no ruleset defines')
            ->assertFailed();
    }

    public function test_it_rejects_a_recommendation_referencing_no_rules(): void
    {
        $this->replaceRecommendations([[
            'name' => 'fixture-rec-002',
            'recommendation' => 'Fixture',
            'description' => 'Fixture recommendation',
            'links' => ['https://example.com'],
            'steps' => ['Do the thing'],
            'rules' => [],
        ]]);

        $this->artisan('scan:validate-rules')
            ->expectsOutputToContain('references no rules')
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

    private function writeMapping(array $controls, array $rules): void
    {
        $path = base_path('rules/rulesets/fixturemapping.json');
        $this->created[] = $path;
        File::put($path, json_encode(['controls' => $controls, 'rules' => $rules], JSON_PRETTY_PRINT));

        // Refs to iam rules are only collectable if iam's scans include this profile.
        $this->addProfile('iam', 'fixturemapping');
    }

    private function addProfile(string $service, string $profile): void
    {
        $path = base_path("rules/tasks/{$service}/tasks.json");
        $this->restore[$path] ??= File::get($path);

        $decoded = json_decode(File::get($path), true);
        $decoded['config']['profiles'][] = $profile;
        File::put($path, json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        ServiceRegistry::flush();
    }

    private function validControls(): array
    {
        return ['100' => ['paragraph' => '16.4.37.C.02', 'level' => 'MUST', 'section' => 'S']];
    }

    public function test_a_valid_mapping_ruleset_passes(): void
    {
        $this->writeMapping($this->validControls(), [['ref' => 'tops-iam-001', 'controls' => ['100']]]);

        $this->artisan('scan:validate-rules')->assertSuccessful();
    }

    public function test_it_rejects_a_reference_to_an_unknown_rule(): void
    {
        $this->writeMapping($this->validControls(), [['ref' => 'no-such-rule-999', 'controls' => ['100']]]);

        $this->artisan('scan:validate-rules')
            ->expectsOutputToContain("ref 'no-such-rule-999' names a rule id that no other ruleset defines")
            ->assertFailed();
    }

    public function test_it_rejects_a_reference_carrying_its_own_condition(): void
    {
        $this->writeMapping($this->validControls(), [
            ['ref' => 'tops-iam-001', 'condition' => 'true', 'controls' => ['100']],
        ]);

        $this->artisan('scan:validate-rules')
            ->expectsOutputToContain("ref 'tops-iam-001' carries its own 'condition'")
            ->assertFailed();
    }

    public function test_it_rejects_a_control_missing_from_the_catalogue(): void
    {
        $this->writeMapping($this->validControls(), [['ref' => 'tops-iam-001', 'controls' => ['100', '424242']]]);

        $this->artisan('scan:validate-rules')
            ->expectsOutputToContain('CID 424242')
            ->assertFailed();
    }

    public function test_it_rejects_a_malformed_paragraph(): void
    {
        $controls = ['100' => ['paragraph' => '16.4.37.2', 'level' => 'MUST', 'section' => 'S']];
        $this->writeMapping($controls, [['ref' => 'tops-iam-001', 'controls' => ['100']]]);

        $this->artisan('scan:validate-rules')
            ->expectsOutputToContain('CID 100 has a bad paragraph')
            ->assertFailed();
    }

    public function test_it_rejects_an_unknown_level(): void
    {
        $controls = ['100' => ['paragraph' => '16.4.37.C.02', 'level' => 'MAY', 'section' => 'S']];
        $this->writeMapping($controls, [['ref' => 'tops-iam-001', 'controls' => ['100']]]);

        $this->artisan('scan:validate-rules')
            ->expectsOutputToContain('CID 100 has a bad level')
            ->assertFailed();
    }

    public function test_it_rejects_a_reference_with_missing_empty_or_non_array_controls(): void
    {
        $this->writeMapping($this->validControls(), [
            ['ref' => 'tops-iam-001'],
            ['ref' => 'tops-iam-002', 'controls' => []],
            ['ref' => 'tops-iam-003', 'controls' => '100'],
        ]);

        $this->artisan('scan:validate-rules')
            ->expectsOutputToContain("ref 'tops-iam-001' has no controls")
            ->expectsOutputToContain("ref 'tops-iam-002' has no controls")
            ->expectsOutputToContain("ref 'tops-iam-003' has no controls")
            ->assertFailed();
    }

    public function test_it_rejects_the_same_ref_twice_in_one_ruleset(): void
    {
        $this->writeMapping($this->validControls(), [
            ['ref' => 'tops-iam-001', 'controls' => ['100']],
            ['ref' => 'tops-iam-001', 'controls' => ['100']],
        ]);

        $this->artisan('scan:validate-rules')
            ->expectsOutputToContain("ref 'tops-iam-001' appears more than once")
            ->assertFailed();
    }

    public function test_it_reports_malformed_entries_instead_of_crashing(): void
    {
        $this->writeMapping($this->validControls(), [
            'not-an-array',
            ['ref' => ['tops-iam-001'], 'controls' => ['100']],
        ]);

        $this->artisan('scan:validate-rules')
            ->expectsOutputToContain("has no 'rule' id")
            ->expectsOutputToContain('names a rule id that no other ruleset defines')
            ->assertFailed();
    }

    /**
     * Without the profile listed, a scan of it never collects the service, so the
     * referenced rule has no data and silently never fires.
     */
    public function test_it_rejects_a_reference_to_a_service_the_profile_does_not_collect(): void
    {
        $this->writeService('fixturesqs', $this->validService('fixturesqs', 'sqs'));
        $this->writeRuleset('fixturebasic', [[
            'rule' => 'fixture-profile-001',
            'name' => 'Profile gap',
            'service' => 'fixturesqs',
            'method' => 'listQueues',
            'severity' => 'low',
            'remediation' => 'n/a',
            'condition' => 'true',
        ]]);
        $this->writeMapping($this->validControls(), [['ref' => 'fixture-profile-001', 'controls' => ['100']]]);

        $this->artisan('scan:validate-rules')
            ->expectsOutputToContain("ref 'fixture-profile-001' targets service 'fixturesqs'")
            ->assertFailed();
    }
}
