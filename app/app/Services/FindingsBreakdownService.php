<?php

namespace App\Services;

use App\Models\ScanResult;
use Illuminate\Database\Eloquent\Builder;

/**
 * The grouped, severity-stacked breakdown behind Scan detail — and, keyed on the whole
 * organization instead of one account, behind Insights.
 *
 * Deliberately takes an organization and an *optional* account rather than a scan. Since
 * D-1 a finding is current state: it belongs to the account, not to the scan that spotted
 * it. Summarising a scan's own rows would answer "what did this scan see", which is a
 * different and now unanswerable question.
 *
 * See docs/features/scan-detail-summarise-and-dispatch.md.
 */
class FindingsBreakdownService
{
    /**
     * How many "fix these first" entries to offer. A ranked list nobody scrolls is a list
     * of one useful item and some noise.
     */
    private const FIX_FIRST_LIMIT = 3;

    public function __construct(protected RecommendationsLoader $recommendations) {}

    /**
     * @param  string|null  $awsAccountId  Null for the whole organization (Insights).
     */
    public function for(string $organizationId, ?string $awsAccountId = null): array
    {
        $byFindingType = $this->groupBySeverity($this->openFindings($organizationId, $awsAccountId), 'finding_type');

        return [
            'summary' => $this->summary($byFindingType),
            'byService' => $this->groupBySeverity($this->openFindings($organizationId, $awsAccountId), 'service'),
            'byFindingType' => $byFindingType,
            'fixFirst' => $this->fixFirst($byFindingType),
        ];
    }

    /**
     * What is open now: resolved findings are not problems, ignored ones still are.
     *
     * Ignoring something is a decision not to act on a real problem, not evidence it went
     * away — excluding those would let the numbers be improved by dismissing things.
     */
    private function openFindings(string $organizationId, ?string $awsAccountId): Builder
    {
        return ScanResult::query()
            ->where('organization_id', $organizationId)
            ->when($awsAccountId !== null, fn (Builder $q) => $q->where('aws_account_id', $awsAccountId))
            ->where('status', '!=', 'resolved');
    }

    /**
     * One grouped query per dimension — grouping by (column, severity) and pivoting in
     * PHP, rather than a query per group or a query per severity.
     *
     * Rows come back largest first so the caller can render them in order without sorting
     * again; ties break on the key so the order is stable between requests.
     */
    private function groupBySeverity(Builder $query, string $column): array
    {
        $rows = $query
            ->select($column, 'severity')
            ->selectRaw('COUNT(*) as severity_count')
            ->groupBy($column, 'severity')
            ->get();

        $grouped = [];

        foreach ($rows as $row) {
            $key = $row->{$column};

            if ($key === null) {
                continue;
            }

            if (! isset($grouped[$key])) {
                $grouped[$key] = [
                    'key' => $key,
                    'total' => 0,
                    'severities' => array_fill_keys(ScanResult::SEVERITY_ORDER, 0),
                    'weight' => 0,
                ];
            }

            $count = (int) $row->severity_count;
            $severity = $row->severity;

            $grouped[$key]['total'] += $count;

            if (array_key_exists($severity, $grouped[$key]['severities'])) {
                $grouped[$key]['severities'][$severity] += $count;
                $grouped[$key]['weight'] += $count * ScanResult::SEVERITY_WEIGHTS[$severity];
            }
        }

        $grouped = array_values($grouped);

        usort($grouped, fn ($a, $b) => [$b['total'], $a['key']] <=> [$a['total'], $b['key']]);

        return $grouped;
    }

    /**
     * Severity totals, derived from a breakdown already in memory rather than re-queried.
     */
    private function summary(array $byFindingType): array
    {
        $bySeverity = array_fill_keys(ScanResult::SEVERITY_ORDER, 0);

        foreach ($byFindingType as $group) {
            foreach ($group['severities'] as $severity => $count) {
                $bySeverity[$severity] += $count;
            }
        }

        return [
            'total' => array_sum($bySeverity),
            'bySeverity' => $bySeverity,
        ];
    }

    /**
     * The changes that clear the most, ranked by severity rather than raw count — so
     * eighteen mediums do not outrank eighteen highs.
     *
     * Grouped by tips.json's themes, which already map rules into "one fix" units, because
     * one real change often closes several rules at once. Rules with no theme fall back to
     * standing alone rather than being dropped: an unthemed high-severity rule is still
     * the thing to fix first.
     */
    private function fixFirst(array $byFindingType): array
    {
        $themes = $this->recommendations->getMapByRuleId();
        $entries = [];

        foreach ($byFindingType as $group) {
            $rule = $group['key'];
            $theme = $themes[$rule] ?? null;

            $key = $theme['name'] ?? $rule;
            $title = $theme['recommendation'] ?? ($rule.' — no grouped recommendation');

            if (! isset($entries[$key])) {
                $entries[$key] = [
                    'title' => $title,
                    'clears' => 0,
                    'weight' => 0,
                    'rules' => [],
                    'severities' => array_fill_keys(ScanResult::SEVERITY_ORDER, 0),
                ];
            }

            $entries[$key]['clears'] += $group['total'];
            $entries[$key]['weight'] += $group['weight'];
            $entries[$key]['rules'][] = $rule;

            foreach ($group['severities'] as $severity => $count) {
                $entries[$key]['severities'][$severity] += $count;
            }
        }

        $entries = array_values($entries);

        usort($entries, fn ($a, $b) => [$b['weight'], $b['clears'], $a['title']] <=> [$a['weight'], $a['clears'], $b['title']]);

        return array_map(function (array $entry) {
            sort($entry['rules']);

            // The worst severity present, which is what the entry is labelled with — a
            // group containing one critical is a critical job even if the rest are low.
            $entry['topSeverity'] = collect(ScanResult::SEVERITY_ORDER)
                ->first(fn (string $severity) => $entry['severities'][$severity] > 0);

            return $entry;
        }, array_slice($entries, 0, self::FIX_FIRST_LIMIT));
    }
}
