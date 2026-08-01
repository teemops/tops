<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Scan;
use App\Models\ScanResult;
use App\Services\ComplianceScoreService;
use App\Services\FindingsBreakdownService;
use App\Services\OrganizationPermission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class InsightsController extends Controller
{
    public function __construct(
        protected OrganizationPermission $permission,
        protected ComplianceScoreService $compliance,
        protected FindingsBreakdownService $breakdown
    ) {
    }

    public function index(Request $request, string $orgId): JsonResponse
    {
        $user = auth()->user();
        $organization = $request->get('organization');

        if (!$organization) {
            return response()->json(['error' => 'Organization not found'], 404);
        }

        if (!$this->permission->canViewInsights($user, $organization)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $period = $this->resolvePeriod($request->input('period', '30d'));
        $days = $this->resolveDays($period);
        $since = now()->subDays($days)->startOfDay();

        $baseQuery = ScanResult::query()
            ->join('scans', 'scan_results.scan_id', '=', 'scans.id')
            ->where('scans.organization_id', $organization->id)
            ->where('scans.status', 'completed')
            ->where('scan_results.created_at', '>=', $since);

        $bySeverity = [
            'critical' => (clone $baseQuery)->where('scan_results.severity', 'critical')->count(),
            'high' => (clone $baseQuery)->where('scan_results.severity', 'high')->count(),
            'medium' => (clone $baseQuery)->where('scan_results.severity', 'medium')->count(),
            'low' => (clone $baseQuery)->where('scan_results.severity', 'low')->count(),
        ];

        $totalFindings = array_sum($bySeverity);
        $resolvedFindings = (clone $baseQuery)->where('scan_results.status', 'resolved')->count();
        $openFindings = (clone $baseQuery)->where('scan_results.status', 'open')->count();
        $criticalOpen = (clone $baseQuery)
            ->where('scan_results.severity', 'critical')
            ->where('scan_results.status', 'open')
            ->count();

        $remediationRate = $totalFindings === 0
            ? 100
            : (int) round(($resolvedFindings / $totalFindings) * 100);

        // The equivalent window immediately before this one, for the trend indicator.
        $previousSince = (clone $since)->subDays($days);
        $previousTotalFindings = ScanResult::query()
            ->join('scans', 'scan_results.scan_id', '=', 'scans.id')
            ->where('scans.organization_id', $organization->id)
            ->where('scans.status', 'completed')
            ->where('scan_results.created_at', '>=', $previousSince)
            ->where('scan_results.created_at', '<', $since)
            ->count();

        $findingsChangePercent = $previousTotalFindings === 0
            ? null
            : (int) round((($totalFindings - $previousTotalFindings) / $previousTotalFindings) * 100);

        $complianceFrameworks = $this->compliance->scoresFor($organization->id, $since);
        $averageCompliance = $this->compliance->averageOf($complianceFrameworks);

        $scanCount = Scan::query()
            ->where('organization_id', $organization->id)
            ->where('status', 'completed')
            ->where('created_at', '>=', $since)
            ->count();

        $topServices = (clone $baseQuery)
            ->selectRaw('scan_results.service as service')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('scan_results.service')
            ->orderByDesc('count')
            ->limit(5)
            ->get()
            ->map(fn ($item) => [
                'service' => $item->service,
                'count' => (int) $item->count,
            ])
            ->values()
            ->all();

        $newFindingsByDay = (clone $baseQuery)
            ->selectRaw('DATE(scan_results.created_at) as date')
            ->selectRaw('COUNT(*) as new_findings')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->mapWithKeys(fn ($item) => [$item->date => (int) $item->new_findings]);

        $resolvedFindingsByDay = ScanResult::query()
            ->join('scans', 'scan_results.scan_id', '=', 'scans.id')
            ->where('scans.organization_id', $organization->id)
            ->where('scans.status', 'completed')
            ->where('scan_results.status', 'resolved')
            ->whereNotNull('scan_results.resolved_at')
            ->where('scan_results.resolved_at', '>=', $since)
            ->selectRaw('DATE(scan_results.resolved_at) as date')
            ->selectRaw('COUNT(*) as resolved_findings')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->mapWithKeys(fn ($item) => [$item->date => (int) $item->resolved_findings]);

        $granularity = $this->resolveGranularity($period);
        $trend = $this->buildTrend($days, $granularity, $newFindingsByDay, $resolvedFindingsByDay);

        $keyInsights = $this->buildKeyInsights(
            $criticalOpen,
            $topServices,
            $remediationRate,
            $findingsChangePercent,
            $complianceFrameworks
        );

        return response()->json([
            'period' => $period,
            'granularity' => $granularity,
            'summary' => [
                'totalFindings' => $totalFindings,
                'previousTotalFindings' => $previousTotalFindings,
                'findingsChangePercent' => $findingsChangePercent,
                'openFindings' => $openFindings,
                'criticalOpen' => $criticalOpen,
                'remediationRate' => $remediationRate,
                'averageCompliance' => $averageCompliance,
                'scanCount' => $scanCount,
            ],
            'severityDistribution' => [
                'critical' => $bySeverity['critical'],
                'high' => $bySeverity['high'],
                'medium' => $bySeverity['medium'],
                'low' => $bySeverity['low'],
            ],
            'compliance' => [
                'average' => $averageCompliance,
                'frameworks' => $complianceFrameworks,
            ],
            'trend' => $trend,
            'topServices' => $topServices,
            // Deliberately *not* windowed by $period, unlike everything above it. The
            // breakdown is current state, so it reads the same here as on Scan detail and
            // Findings — windowing it would mean the same service showing two different
            // numbers depending on which page you opened, which is the problem this whole
            // workstream exists to remove. The UI labels it "across all scans".
            //
            // Same service Scan detail uses, keyed on the organization instead of one
            // account. See docs/features/insights-by-service.md.
            'breakdown' => $this->breakdown->for($organization->id),
            'keyInsights' => $keyInsights,
        ]);
    }

    /**
     * Bucket per-day counts into the granularity for the period, so a year does not
     * return 366 points. Empty buckets are kept so the chart shows real gaps.
     *
     * @param \Illuminate\Support\Collection<string, int> $newFindingsByDay
     * @param \Illuminate\Support\Collection<string, int> $resolvedFindingsByDay
     * @return array<int, array{date: string, new: int, resolved: int}>
     */
    private function buildTrend(int $days, string $granularity, $newFindingsByDay, $resolvedFindingsByDay): array
    {
        $buckets = [];

        for ($offset = $days; $offset >= 0; $offset--) {
            $date = now()->subDays($offset)->startOfDay();

            $bucketStart = match ($granularity) {
                'week' => $date->copy()->startOfWeek(),
                'month' => $date->copy()->startOfMonth(),
                default => $date->copy(),
            };

            $bucketKey = $bucketStart->toDateString();

            $buckets[$bucketKey] ??= [
                'date' => $granularity === 'month'
                    ? $bucketStart->format('M Y')
                    : $bucketStart->format('M d'),
                'new' => 0,
                'resolved' => 0,
            ];

            $dayKey = $date->toDateString();
            $buckets[$bucketKey]['new'] += $newFindingsByDay[$dayKey] ?? 0;
            $buckets[$bucketKey]['resolved'] += $resolvedFindingsByDay[$dayKey] ?? 0;
        }

        return array_values($buckets);
    }

    /**
     * @param array<int, array{service: string, count: int}> $topServices
     * @param array<int, array{label: string, score: int|null, evaluated: bool}> $frameworks
     * @return array<int, array{title: string, detail: string, severity: string}>
     */
    private function buildKeyInsights(
        int $criticalOpen,
        array $topServices,
        int $remediationRate,
        ?int $findingsChangePercent,
        array $frameworks
    ): array {
        $keyInsights = [];

        if ($findingsChangePercent !== null && $findingsChangePercent !== 0) {
            $falling = $findingsChangePercent < 0;
            $keyInsights[] = [
                'title' => sprintf(
                    'Findings %s %d%% vs. previous period',
                    $falling ? 'down' : 'up',
                    abs($findingsChangePercent)
                ),
                'detail' => $falling
                    ? 'Remediation efforts are improving security posture.'
                    : 'More findings were discovered than in the previous period.',
                'severity' => $falling ? 'positive' : 'warning',
            ];
        }

        if ($criticalOpen > 0) {
            $keyInsights[] = [
                'title' => 'Critical findings need attention',
                'detail' => sprintf('%d critical findings are still open and should be prioritized.', $criticalOpen),
                'severity' => 'danger',
            ];
        }

        if (!empty($topServices)) {
            $topService = $topServices[0];
            $keyInsights[] = [
                'title' => 'Most affected service',
                'detail' => sprintf('%s has the highest concentration of findings (%d).', ucfirst($topService['service']), $topService['count']),
                'severity' => 'warning',
            ];
        }

        $evaluated = array_filter($frameworks, fn ($framework) => $framework['evaluated']);
        if (!empty($evaluated)) {
            usort($evaluated, fn ($a, $b) => $b['score'] <=> $a['score']);
            $best = $evaluated[0];
            $keyInsights[] = [
                'title' => sprintf('%s score at %d%%', $best['label'], $best['score']),
                'detail' => count($evaluated) > 1
                    ? 'Highest compliance score among the frameworks evaluated.'
                    : 'Based on the rules evaluated in this period.',
                'severity' => 'positive',
            ];
        }

        if ($remediationRate < 60) {
            $keyInsights[] = [
                'title' => 'Remediation is lagging',
                'detail' => 'The remediation rate is below the target, so consider assigning ownership to the backlog.',
                'severity' => 'warning',
            ];
        }

        if (empty($keyInsights)) {
            $keyInsights[] = [
                'title' => 'Security posture is healthy',
                'detail' => 'No urgent issues were detected in the selected period.',
                'severity' => 'positive',
            ];
        }

        return $keyInsights;
    }

    private function resolvePeriod(string $period): string
    {
        return in_array($period, ['30d', '90d', '365d'], true) ? $period : '30d';
    }

    private function resolveDays(string $period): int
    {
        return match ($period) {
            '90d' => 90,
            '365d' => 365,
            default => 30,
        };
    }

    /**
     * Daily points for a month, weekly for a quarter, monthly for a year.
     */
    private function resolveGranularity(string $period): string
    {
        return match ($period) {
            '90d' => 'week',
            '365d' => 'month',
            default => 'day',
        };
    }
}
