<?php

namespace App\Services\RulesEngine;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/**
 * Reads a ruleset file and resolves its references.
 *
 * Most rulesets hold full rules. A mapping ruleset (nzism.json) instead holds
 * `{ "ref": "<rule id>", "controls": [CIDs] }` entries pointing at rules defined in
 * another ruleset, plus a `controls` catalogue keyed by CID. Resolving a ref yields the
 * referenced rule unchanged — same id, condition, severity and remediation, so the
 * mapping can never drift from the check it reuses — with the entry's controls attached
 * under `controls`.
 *
 * Deliberately not memoized: the files are small and a cache would serve stale rules to
 * any test that writes a ruleset file.
 */
class RulesetLoader
{
    /**
     * A ruleset's rules with references resolved.
     *
     * @return array<int, array<string, mixed>>
     * @throws \Exception when the file is missing or not valid JSON
     */
    public function load(string $ruleset): array
    {
        $document = $this->read($ruleset);
        $rules = [];
        $index = null;

        foreach ($document['rules'] ?? [] as $entry) {
            if (!is_array($entry) || !array_key_exists('ref', $entry)) {
                $rules[] = $entry;

                continue;
            }

            $index ??= $this->fullRulesExcept($ruleset);
            $target = $index[$entry['ref']] ?? null;

            if ($target === null) {
                // A scan must not crash on a bad reference; the validator is what fails.
                Log::warning('Skipping an unresolvable ruleset reference', [
                    'ruleset' => $ruleset,
                    'ref' => $entry['ref'],
                ]);

                continue;
            }

            $target['controls'] = $this->resolveControls($document, $ruleset, $entry);
            $rules[] = $target;
        }

        return $rules;
    }

    /**
     * Controls per rule id for a mapping ruleset, ordered MUST / MUST NOT first, then
     * SHOULD / SHOULD NOT, then by CID. Empty for a ruleset with no mapping.
     *
     * @return array<string, array<int, array{cid: string, paragraph: string, level: string, section: string}>>
     */
    public function controlsByRuleId(string $ruleset): array
    {
        $map = [];

        foreach ($this->load($ruleset) as $rule) {
            if (!empty($rule['controls']) && isset($rule['rule'])) {
                $map[$rule['rule']] = $rule['controls'];
            }
        }

        return $map;
    }

    /**
     * @return array<string, mixed>
     */
    private function read(string $ruleset): array
    {
        $path = base_path("rules/rulesets/{$ruleset}.json");

        if (!File::exists($path)) {
            throw new \Exception("Rules file not found: {$path}");
        }

        $decoded = json_decode(File::get($path), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Invalid JSON in rules file: ' . json_last_error_msg());
        }

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Full rules (those with an id of their own) in every other ruleset, keyed by id.
     *
     * @return array<string, array<string, mixed>>
     */
    private function fullRulesExcept(string $ruleset): array
    {
        $index = [];

        foreach (File::glob(base_path('rules/rulesets/*.json')) as $path) {
            if (basename($path, '.json') === $ruleset) {
                continue;
            }

            $decoded = json_decode(File::get($path), true);

            foreach ($decoded['rules'] ?? [] as $rule) {
                if (is_array($rule) && isset($rule['rule'])) {
                    $index[$rule['rule']] ??= $rule;
                }
            }
        }

        return $index;
    }

    /**
     * @return array<int, array{cid: string, paragraph: string, level: string, section: string}>
     */
    private function resolveControls(array $document, string $ruleset, array $entry): array
    {
        $catalogue = $document['controls'] ?? [];
        $controls = [];

        foreach ($entry['controls'] ?? [] as $cid) {
            $cid = (string) $cid;
            $control = $catalogue[$cid] ?? null;

            if ($control === null) {
                Log::warning('Skipping a control missing from the catalogue', [
                    'ruleset' => $ruleset,
                    'ref' => $entry['ref'],
                    'cid' => $cid,
                ]);

                continue;
            }

            $controls[] = [
                'cid' => $cid,
                'paragraph' => $control['paragraph'] ?? '',
                'level' => $control['level'] ?? '',
                'section' => $control['section'] ?? '',
            ];
        }

        // MUST / MUST NOT before SHOULD / SHOULD NOT, then by CID (numerically).
        usort($controls, fn ($a, $b) => [str_starts_with($a['level'], 'MUST') ? 0 : 1, (int) $a['cid']]
            <=> [str_starts_with($b['level'], 'MUST') ? 0 : 1, (int) $b['cid']]);

        return $controls;
    }
}
