<?php

namespace Tests\Unit;

use App\Services\RulesEngine\RulesetLoader;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class RulesetLoaderTest extends TestCase
{
    /** @var string[] */
    private array $created = [];

    protected function tearDown(): void
    {
        foreach ($this->created as $path) {
            File::delete($path);
        }

        parent::tearDown();
    }

    private function writeRuleset(string $name, array $document): void
    {
        $path = base_path("rules/rulesets/{$name}.json");
        $this->created[] = $path;
        File::put($path, json_encode($document, JSON_PRETTY_PRINT));
    }

    private function mapping(array $rules): array
    {
        return [
            'controls' => [
                '100' => ['paragraph' => '1.1.1.C.01', 'level' => 'SHOULD', 'section' => 'S'],
                '200' => ['paragraph' => '1.1.2.C.01', 'level' => 'MUST', 'section' => 'S'],
                '50' => ['paragraph' => '1.1.3.C.01', 'level' => 'SHOULD NOT', 'section' => 'S'],
            ],
            'rules' => $rules,
        ];
    }

    public function test_it_resolves_a_reference_to_the_full_rule_and_attaches_its_controls(): void
    {
        $this->writeRuleset('fixturemap', $this->mapping([
            ['ref' => 'tops-iam-001', 'controls' => ['100', '200', '50']],
        ]));

        $rules = (new RulesetLoader())->load('fixturemap');

        $this->assertCount(1, $rules);
        $this->assertSame('tops-iam-001', $rules[0]['rule']);
        $this->assertNotEmpty($rules[0]['condition']);
        $this->assertNotEmpty($rules[0]['remediation']);

        // Same check, same remediation: identical to the rule as defined in basic.json.
        $source = collect(json_decode(File::get(base_path('rules/rulesets/basic.json')), true)['rules'])
            ->firstWhere('rule', 'tops-iam-001');
        $resolved = $rules[0];
        unset($resolved['controls']);
        $this->assertSame($source, $resolved);
        // MUST first, then SHOULD / SHOULD NOT by CID.
        $this->assertSame(['200', '50', '100'], array_column($rules[0]['controls'], 'cid'));
        $this->assertSame(
            ['cid' => '200', 'paragraph' => '1.1.2.C.01', 'level' => 'MUST', 'section' => 'S'],
            $rules[0]['controls'][0]
        );
    }

    public function test_full_rules_pass_through_unchanged(): void
    {
        $rules = (new RulesetLoader())->load('basic');

        $this->assertNotEmpty($rules);
        $this->assertArrayHasKey('rule', $rules[0]);
        $this->assertArrayNotHasKey('controls', $rules[0]);
    }

    public function test_an_unresolvable_reference_is_skipped_not_fatal(): void
    {
        Log::spy();
        $this->writeRuleset('fixturemap', $this->mapping([
            ['ref' => 'no-such-rule-999', 'controls' => ['100']],
            ['ref' => 'tops-iam-001', 'controls' => ['100']],
        ]));

        $rules = (new RulesetLoader())->load('fixturemap');

        $this->assertSame(['tops-iam-001'], array_column($rules, 'rule'));
        Log::shouldHaveReceived('warning')->withArgs(
            fn ($message, $context = []) => ($context['ref'] ?? null) === 'no-such-rule-999'
        );
    }

    public function test_a_non_string_reference_is_skipped_not_fatal(): void
    {
        Log::spy();
        $this->writeRuleset('fixturemap', $this->mapping([
            ['ref' => ['tops-iam-001'], 'controls' => ['100']],
            ['ref' => 'tops-iam-001', 'controls' => ['100']],
        ]));

        $rules = (new RulesetLoader())->load('fixturemap');

        $this->assertSame(['tops-iam-001'], array_column($rules, 'rule'));
        Log::shouldHaveReceived('warning')->once();
    }

    public function test_a_control_missing_from_the_catalogue_is_not_attached(): void
    {
        $this->writeRuleset('fixturemap', $this->mapping([
            ['ref' => 'tops-iam-001', 'controls' => ['100', '99999']],
        ]));

        $rules = (new RulesetLoader())->load('fixturemap');

        $this->assertSame(['100'], array_column($rules[0]['controls'], 'cid'));
    }

    public function test_a_missing_ruleset_throws(): void
    {
        $this->expectException(\Exception::class);

        (new RulesetLoader())->load('does-not-exist');
    }

    /**
     * The correctness criterion: levels come from the November 2025 manual, not AWS's mapping.
     */
    public function test_the_shipped_nzism_mapping_carries_the_manual_levels(): void
    {
        $document = json_decode(File::get(base_path('rules/rulesets/nzism.json')), true);
        $catalogue = $document['controls'];

        $this->assertSame('MUST', $catalogue['6843']['level']);
        $this->assertSame('16.4.37.C.02', $catalogue['6843']['paragraph']);
        $this->assertSame('MUST', $catalogue['6953']['level']);
        $this->assertSame('SHOULD', $catalogue['7437']['level']);

        // CIDs absent from the November 2025 manual must not appear anywhere.
        $this->assertArrayNotHasKey('1893', $catalogue);
        $this->assertArrayNotHasKey('1946', $catalogue);
        foreach ($document['rules'] as $ref) {
            $this->assertNotContains('1893', $ref['controls']);
            $this->assertNotContains('1946', $ref['controls']);
        }
    }

    public function test_every_shipped_nzism_reference_resolves(): void
    {
        $document = json_decode(File::get(base_path('rules/rulesets/nzism.json')), true);

        $resolved = (new RulesetLoader())->load('nzism');

        $this->assertCount(count($document['rules']), $resolved);
        foreach ($resolved as $rule) {
            $this->assertNotEmpty($rule['controls'], "{$rule['rule']} has no controls");
        }
    }

    public function test_controls_by_rule_id_is_keyed_by_the_referenced_rule(): void
    {
        $this->writeRuleset('fixturemap', $this->mapping([
            ['ref' => 'tops-iam-001', 'controls' => ['100', '200']],
        ]));

        $map = (new RulesetLoader())->controlsByRuleId('fixturemap');

        $this->assertSame(['tops-iam-001'], array_keys($map));
        $this->assertSame('MUST', $map['tops-iam-001'][0]['level']);
    }
}
