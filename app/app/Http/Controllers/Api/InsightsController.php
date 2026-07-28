<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ScanResult;
use App\Services\OrganizationPermission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InsightsController extends Controller
{
    public function __construct(protected OrganizationPermission $permission)
    {
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

        $period = $request->input('period', '30d');
        $days = $this->resolveDays($period);
        $since = now()->subDays($days)->startOfDay();

        $baseQuery = ScanResult::query()
            ->join('scans', 'scan_results.scan_id', '=', 'scans.id')
            ->where('scans.organization_id', $organization->id)
            ->where('scans.status', 'completed')
            ->where('scan_results.created_at', '>=', $since)
            ->select('scan_results.*');

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

        $averageCompliance = $totalFindings === 0
            ? 100
            : max(0, min(100, (int) round(100 - (
                ($bySeverity['critical'] * 20) +
                ($bySeverity['high'] * 8) +
                ($bySeverity['medium'] * 3) +
                ($bySeverity['low'] * 1)
            ) / max(1, $totalFindings))));

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

        $resolvedBaseQuery = ScanResult::query()
            ->join('scans', 'scan_results.scan_id', '=', 'scans.id')
            ->where('scans.organization_id', $organization->id)
            ->where('scans.status', 'completed')
            ->where('scan_results.status', 'resolved')
            ->whereNotNull('scan_results.resolved_at')
            ->where('scan_results.resolved_at', '>=', $since)
            ->select('scan_results.*');

        $resolvedFindingsByDay = (clone $resolvedBaseQuery)
            ->selectRaw('DATE(scan_results.resolved_at) as date')
            ->selectRaw('COUNT(*) as resolved_findings')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->mapWithKeys(fn ($item) => [$item->date => (int) $item->resolved_findings]);

        $trend = [];
        for ($offset = $days; $offset >= 0; $offset--) {
            $date = now()->subDays($offset)->startOfDay();
            $key = $date->toDateString();
            $trend[] = [
                'date' => $date->format('M d'),
                'new' => $newFindingsByDay[$key] ?? 0,
                'resolved' => $resolvedFindingsByDay[$key] ?? 0,
            ];
        }

        $keyInsights = [];
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

        return response()->json([
            'period' => $period,
            'summary' => [
                'totalFindings' => $totalFindings,
                'openFindings' => $openFindings,
                'criticalOpen' => $criticalOpen,
                'remediationRate' => $remediationRate,
                'averageCompliance' => $averageCompliance,
            ],
            'severityDistribution' => [
                'critical' => $bySeverity['critical'],
                'high' => $bySeverity['high'],
                'medium' => $bySeverity['medium'],
                'low' => $bySeverity['low'],
            ],
            'trend' => $trend,
            'topServices' => $topServices,
            'keyInsights' => $keyInsights,
        ]);
    }

    private function resolveDays(string $period): int
    {
        return match ($period) {
            '90d' => 90,
            '365d' => 365,
            default => 30,
        };
    }
}
