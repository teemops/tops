<?php

namespace App\Services;

use App\Models\Scan;
use App\Models\ScanResult;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;

/**
 * Per-framework compliance scores for the Insights page.
 *
 * A "framework" is a ruleset under rules/rulesets that actually declares rules —
 * the same availability rule ScanProfilesService uses, so an unauthored ruleset
 * (pci.json today) never shows up as a 0% score.
 *
 * No schema change is needed to attribute a finding to a framework: ScanResult's
 * finding_type *is* the rule id (e.g. "tops-s3-001"), so membership is a lookup
 * against the ruleset's rule ids.
 *
 * A framework only gets a score when at least one completed scan in the period
 * actually evaluated it (scans.rulesets), otherwise it reports as not evaluated —
 * scoring a framework nobody ran would read as 100% compliant.
 */
class ComplianceScoreService
{
    /**
     * Scores for every authored framework, evaluated or not.
     *
     * @return array<int, array{key: string, label: string, description: string, totalRules: int, failingRules: int, score: int|null, evaluated: bool}>
     */
    public function scoresFor(string $organizationId, Carbon $since): array
    {
        $scans = Scan::query()
            ->where('organization_id', $organizationId)
            ->where('status', 'completed')
            ->where('created_at', '>=', $since)
            ->get(['id', 'rulesets']);

        $scores = [];

        foreach ($this->frameworks() as $key => $framework) {
            // Scans with no rulesets recorded fall back to 'basic', matching the jobs.
            $scanIds = $scans
                ->filter(fn (Scan $scan) => in_array($key, $scan->rulesets ?: ['basic'], true))
                ->pluck('id');

            if ($scanIds->isEmpty()) {
                $scores[] = [
                    'key' => $key,
                    'label' => $framework['label'],
                    'description' => $framework['description'],
                    'totalRules' => count($framework['ruleIds']),
                    'failingRules' => 0,
                    'score' => null,
                    'evaluated' => false,
                ];
                continue;
            }

            // Only open findings count against a score — resolved and ignored
            // findings are no longer failing the check.
            $failingRules = ScanResult::query()
                ->whereIn('scan_id', $scanIds)
                ->where('status', 'open')
                ->whereIn('finding_type', $framework['ruleIds'])
                ->distinct()
                ->count('finding_type');

            $totalRules = count($framework['ruleIds']);

            $scores[] = [
                'key' => $key,
                'label' => $framework['label'],
                'description' => $framework['description'],
                'totalRules' => $totalRules,
                'failingRules' => $failingRules,
                'score' => (int) round((($totalRules - $failingRules) / $totalRules) * 100),
                'evaluated' => true,
            ];
        }

        return $scores;
    }

    /**
     * Mean score across frameworks that were actually evaluated, or null when none were.
     *
     * @param array<int, array{score: int|null, evaluated: bool}> $scores
     */
    public function averageOf(array $scores): ?int
    {
        $evaluated = array_filter($scores, fn ($framework) => $framework['evaluated']);

        if (empty($evaluated)) {
            return null;
        }

        return (int) round(array_sum(array_column($evaluated, 'score')) / count($evaluated));
    }

    /**
     * Authored frameworks keyed by ruleset name, with their rule ids.
     *
     * @return array<string, array{label: string, description: string, ruleIds: string[]}>
     */
    public function frameworks(): array
    {
        $frameworks = [];

        foreach (ScanProfilesService::rulesetLabels() as $ruleset => $meta) {
            $ruleIds = $this->ruleIds($ruleset);

            if (empty($ruleIds)) {
                continue;
            }

            $frameworks[$ruleset] = [
                'label' => $meta['label'],
                'description' => $meta['description'],
                'ruleIds' => $ruleIds,
            ];
        }

        return $frameworks;
    }

    /**
     * Rule ids declared by a ruleset.
     *
     * @return string[]
     */
    private function ruleIds(string $ruleset): array
    {
        $path = base_path("rules/rulesets/{$ruleset}.json");

        if (!File::exists($path)) {
            return [];
        }

        $decoded = json_decode(File::get($path), true);

        return array_values(array_filter(
            array_column($decoded['rules'] ?? [], 'rule')
        ));
    }
}
