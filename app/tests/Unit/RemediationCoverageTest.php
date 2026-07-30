<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Roadmap N-4: every finding must tell the operator how to fix it.
 *
 * These assert over the *shipped* rulesets rather than fixtures, deliberately. The
 * gap this closes was not a broken code path — the code worked fine. It was 39
 * rules, including every CIS rule, that had drifted into having no remediation
 * text while `scan:validate-rules` only warned about it. A fixture test would not
 * have caught that; only a test that reads what actually ships does.
 */
class RemediationCoverageTest extends TestCase
{
    /**
     * @return array<int, array{rule: string, severity: string, remediation: string, ruleset: string}>
     */
    private function shippedRules(): array
    {
        $rules = [];

        foreach (File::glob(base_path('rules/rulesets/*.json')) as $path) {
            $decoded = json_decode(File::get($path), true);

            foreach ($decoded['rules'] ?? [] as $rule) {
                $rules[] = [
                    'rule' => $rule['rule'] ?? '(no id)',
                    'severity' => $rule['severity'] ?? '(none)',
                    'remediation' => (string) ($rule['remediation'] ?? ''),
                    'ruleset' => basename($path),
                ];
            }
        }

        return $rules;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recommendations(): array
    {
        $path = base_path('rules/recommendations/tips.json');

        return json_decode(File::get($path), true)['recommendations'] ?? [];
    }

    /**
     * @return array<string, true>
     */
    private function rulesWithGuidance(): array
    {
        $covered = [];

        foreach ($this->recommendations() as $rec) {
            foreach ($rec['rules'] ?? [] as $ruleId) {
                $covered[$ruleId] = true;
            }
        }

        return $covered;
    }

    public function test_the_rulesets_are_not_empty(): void
    {
        // Guards the assertions below from passing vacuously if a glob or a path
        // changes and nothing is loaded.
        $this->assertGreaterThan(50, count($this->shippedRules()));
    }

    public function test_every_shipped_rule_has_a_remediation(): void
    {
        $missing = [];

        foreach ($this->shippedRules() as $rule) {
            if (trim($rule['remediation']) === '') {
                $missing[] = "{$rule['ruleset']}:{$rule['rule']}";
            }
        }

        $this->assertSame([], $missing, "These rules would produce findings a user cannot act on:\n" . implode("\n", $missing));
    }

    /**
     * A one-liner reading "Fix it" would satisfy the check above and help nobody.
     */
    public function test_every_remediation_is_substantive(): void
    {
        $tooShort = [];

        foreach ($this->shippedRules() as $rule) {
            if (strlen(trim($rule['remediation'])) < 40) {
                $tooShort[] = "{$rule['ruleset']}:{$rule['rule']} (" . strlen(trim($rule['remediation'])) . " chars)";
            }
        }

        $this->assertSame([], $tooShort, "These remediations are too short to be actionable:\n" . implode("\n", $tooShort));
    }

    /**
     * Critical and high findings get step-by-step guidance with links, per N-4's
     * acceptance criteria. Medium and low deliberately do not — the one-line
     * remediation is enough for them, and pretending otherwise would mean writing
     * 39 more recommendations nobody asked for.
     */
    public function test_every_critical_and_high_rule_has_step_by_step_guidance(): void
    {
        $covered = $this->rulesWithGuidance();
        $missing = [];

        foreach ($this->shippedRules() as $rule) {
            if (in_array($rule['severity'], ['critical', 'high'], true) && !isset($covered[$rule['rule']])) {
                $missing[] = "{$rule['ruleset']}:{$rule['rule']} ({$rule['severity']})";
            }
        }

        $this->assertSame([], $missing, "These high-severity rules have no recommendation in tips.json:\n" . implode("\n", $missing));
    }

    /**
     * A recommendation pointing at a rule id that does not exist is never shown,
     * which looks exactly like guidance nobody needed. tips.json referenced
     * tops-route53-001 for months and nothing noticed.
     */
    public function test_no_recommendation_references_an_unknown_rule(): void
    {
        $known = [];
        foreach ($this->shippedRules() as $rule) {
            $known[$rule['rule']] = true;
        }

        $dangling = [];
        foreach ($this->recommendations() as $rec) {
            foreach ($rec['rules'] ?? [] as $ruleId) {
                if (!isset($known[$ruleId])) {
                    $dangling[] = "{$rec['name']} -> {$ruleId}";
                }
            }
        }

        $this->assertSame([], $dangling, "Dangling recommendation references:\n" . implode("\n", $dangling));
    }

    public function test_every_recommendation_carries_steps_and_links(): void
    {
        $incomplete = [];

        foreach ($this->recommendations() as $rec) {
            $name = $rec['name'] ?? '(unnamed)';

            if (empty($rec['steps'])) {
                $incomplete[] = "{$name} has no steps";
            }

            if (empty($rec['links'])) {
                $incomplete[] = "{$name} has no links";
            }

            foreach ($rec['links'] ?? [] as $link) {
                if (!str_starts_with($link, 'https://')) {
                    $incomplete[] = "{$name} has a non-HTTPS link: {$link}";
                }
            }
        }

        $this->assertSame([], $incomplete, implode("\n", $incomplete));
    }

    /**
     * Recommendations group related rules rather than mapping one-to-one, so the
     * remediation workflow stays readable. This is a smell check, not a rule: it
     * fails only if the file has degenerated into a recommendation per rule.
     */
    public function test_recommendations_group_rules_rather_than_mirroring_them(): void
    {
        $recommendations = $this->recommendations();
        $referenced = count($this->rulesWithGuidance());

        $this->assertLessThan(
            $referenced,
            count($recommendations),
            'tips.json has as many recommendations as rules it covers, which means the grouping has stopped being meaningful.'
        );
    }
}
