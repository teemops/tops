<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Scan;
use App\Models\ScanResult;
use App\Services\OrganizationPermission;
use App\Services\RecommendationsLoader;
use App\Services\ScanProfilesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FindingsController extends Controller
{
    public function __construct(
        protected OrganizationPermission $permission,
        protected RecommendationsLoader $recommendations
    ) {}

    /**
     * Org-wide findings list + Executive Summary.
     */
    public function index(Request $request, string $orgId): JsonResponse
    {
        $user = auth()->user();
        $organization = $request->get('organization');

        if (!$organization) {
            return response()->json(['error' => 'Organization not found'], 404);
        }

        if (!$this->permission->canViewFindings($user, $organization)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $query = $this->filteredFindings($request, $organization->id)
            ->select('scan_results.*');

        $limit = min($request->input('limit', 50), 200);
        $offset = max($request->input('offset', 0), 0);

        $total = $query->count();

        $results = (clone $query)
            ->orderBySeverity('scan_results.severity')
            ->orderBy('scan_results.created_at', 'asc')
            ->offset($offset)
            ->limit($limit)
            ->get();

        $results->load('scan.awsAccount');

        $findings = $results->map(function (ScanResult $result) {
            $scan = $result->scan;
            $awsAccount = $scan?->awsAccount;
            return [
                'id' => $result->id,
                'scanId' => $result->scan_id,
                'findingType' => $result->finding_type,
                'title' => $result->title,
                'description' => $result->description,
                'severity' => $result->severity,
                'service' => $result->service,
                'resourceId' => $result->resource_id,
                'resourceType' => $result->resource_type,
                'status' => $result->status,
                'remediation' => $result->remediation,
                'createdAt' => $result->created_at->toISOString(),
                'awsAccountId' => $awsAccount?->id,
                'awsAccountName' => $awsAccount?->name,
            ];
        });

        $baseQuery = $this->filteredFindings($request, $organization->id);

        // A resolved finding is not an open problem, so it does not count toward the
        // totals or the score. This matters more than it used to: before durable findings
        // nothing resolved itself, so "resolved" was a rare manual act. Now a scan closes
        // what it finds fixed, and if these counts included those, fixing something and
        // rescanning would leave the numbers exactly where they were.
        //
        // Ignored findings still count. Ignoring is a decision not to act on a real
        // problem, not evidence that it went away, and excluding them would let the score
        // be improved by dismissing things.
        if (!$request->filled('status')) {
            $baseQuery->where('scan_results.status', '!=', 'resolved');
        }

        $bySeverity = [
            'critical' => (clone $baseQuery)->where('scan_results.severity', 'critical')->count(),
            'high' => (clone $baseQuery)->where('scan_results.severity', 'high')->count(),
            'medium' => (clone $baseQuery)->where('scan_results.severity', 'medium')->count(),
            'low' => (clone $baseQuery)->where('scan_results.severity', 'low')->count(),
        ];

        // No security score. It was 100 minus a weighted penalty, floored at zero, which
        // put every real account at zero and kept it there — fixing ten things moved
        // nothing. A number that cannot move is worse than no number, because it looks
        // like information. Severity counts are honest and already say more. See D-12.
        $summary = [
            'total' => array_sum($bySeverity),
            'bySeverity' => $bySeverity,
        ];

        $recommendationsMap = $this->recommendations->getMapByRuleId();

        return response()->json([
            'summary' => $summary,
            'findings' => $findings,
            'serviceFacets' => $this->serviceFacets($request, $organization->id),
            'benchmarkFacets' => $this->benchmarkFacets($request, $organization->id),
            'recommendationsMap' => $recommendationsMap,
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
        ]);
    }

    /**
     * The organization-scoped base for every findings query on the index page.
     *
     * $except names a filter to leave off, which is what makes faceting work: a facet has
     * to be computed without the filter it is offering, or selecting one service collapses
     * every other pill to zero and there is no way back.
     */
    private function filteredFindings(Request $request, string $organizationId, ?string $except = null)
    {
        $query = ScanResult::query()
            ->join('scans', 'scan_results.scan_id', '=', 'scans.id')
            ->where('scans.organization_id', $organizationId)
            ->where('scans.status', 'completed');

        if ($except !== 'aws_account_id' && $request->filled('aws_account_id')) {
            $query->where('scans.aws_account_id', $request->input('aws_account_id'));
        }

        if ($except !== 'finding_type' && $request->filled('finding_type')) {
            $query->where('scan_results.finding_type', $request->input('finding_type'));
        }

        if ($except !== 'service' && $request->filled('service')) {
            $query->where('scan_results.service', $request->input('service'));
        }

        if ($except !== 'ruleset' && $request->filled('ruleset')) {
            $query->whereJsonContains('scan_results.rulesets', $request->input('ruleset'));
        }

        if ($except !== 'status' && $request->filled('status')) {
            $query->where('scan_results.status', $request->input('status'));
        }

        return $query;
    }

    /**
     * How many findings each benchmark would yield if it were the selected one.
     *
     * This loops where the service facet uses one grouped query, and that is deliberate.
     * Services number eleven and grow, so looping there would not scale; benchmarks number
     * two, and grouping over a JSON array needs `json_each`, whose syntax differs between
     * MySQL and the SQLite the tests run on. Two counts are simpler, portable and cheaper
     * than the query avoiding them. Revisit if benchmarks ever reach double figures.
     *
     * Only benchmarks that actually have rules are offered — the same rule the New Scan
     * modal follows, which is why an empty PCI never appears.
     */
    private function benchmarkFacets(Request $request, string $organizationId): array
    {
        $facets = [];

        foreach (ScanProfilesService::rulesetLabels() as $ruleset => $meta) {
            $count = (clone $this->filteredFindings($request, $organizationId, except: 'ruleset'))
                ->whereJsonContains('scan_results.rulesets', $ruleset)
                ->count();

            if ($count === 0) {
                continue;
            }

            $facets[] = [
                'ruleset' => $ruleset,
                'label' => $meta['label'] ?? $ruleset,
                'count' => $count,
            ];
        }

        usort($facets, fn ($a, $b) => [$b['count'], $a['ruleset']] <=> [$a['count'], $b['ruleset']]);

        return $facets;
    }

    /**
     * How many findings each service would yield if it were the selected one — one grouped
     * query, not a count per service.
     *
     * These deliberately follow the *list* semantics rather than the summary's, which drops
     * resolved findings. A pill reading 12 has to produce 12 rows when clicked; a count the
     * user can see disagree with the list is worse than no count at all.
     */
    private function serviceFacets(Request $request, string $organizationId): array
    {
        return $this->filteredFindings($request, $organizationId, except: 'service')
            ->select('scan_results.service')
            ->selectRaw('COUNT(*) as facet_count')
            ->groupBy('scan_results.service')
            ->orderByDesc('facet_count')
            ->orderBy('scan_results.service')
            ->get()
            ->map(fn ($row) => [
                'service' => $row->service,
                'count' => (int) $row->facet_count,
            ])
            ->all();
    }

    /**
     * Single finding details + recommendation (remediation).
     */
    public function show(Request $request, string $findingId): JsonResponse
    {
        $user = auth()->user();
        $organization = $request->get('organization');

        if (!$organization) {
            return response()->json(['error' => 'Organization not found'], 404);
        }

        if (!$this->permission->canViewFindings($user, $organization)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $result = ScanResult::with(['scan.awsAccount'])
            ->whereHas('scan', fn ($q) => $q->where('organization_id', $organization->id))
            ->findOrFail($findingId);

        $recommendation = $this->recommendations->getByRuleId($result->finding_type);

        return response()->json([
            'id' => $result->id,
            'scanId' => $result->scan_id,
            'findingType' => $result->finding_type,
            'title' => $result->title,
            'description' => $result->description,
            'severity' => $result->severity,
            'service' => $result->service,
            'resourceId' => $result->resource_id,
            'resourceType' => $result->resource_type,
            'status' => $result->status,
            'remediation' => $result->remediation,
            'createdAt' => $result->created_at->toISOString(),
            'updatedAt' => $result->updated_at->toISOString(),
            'awsAccountId' => $result->scan?->awsAccount?->id,
            'awsAccountName' => $result->scan?->awsAccount?->name,
            'recommendation' => $recommendation,
        ]);
    }

    /**
     * Update finding status (open / resolved / ignored).
     */
    public function update(Request $request, string $findingId): JsonResponse
    {
        $user = auth()->user();
        $organization = $request->get('organization');

        if (!$organization) {
            return response()->json(['error' => 'Organization not found'], 404);
        }

        if (!$this->permission->canViewFindings($user, $organization)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'status' => 'required|in:open,resolved,ignored',
        ]);

        $result = ScanResult::query()
            ->whereHas('scan', fn ($q) => $q->where('organization_id', $organization->id))
            ->findOrFail($findingId);

        $result->update([
            'status' => $validated['status'],
            'resolved_at' => $validated['status'] === 'resolved' ? now() : null,
            // Distinguishes a person's decision from a scan closing something it found
            // fixed, so the UI can say which happened.
            'resolution_reason' => $validated['status'] === 'resolved'
                ? \App\Models\ScanResult::REASON_MANUAL
                : null,
        ]);

        return response()->json([
            'id' => $result->id,
            'status' => $result->status,
            'updatedAt' => $result->updated_at->toISOString(),
        ]);
    }

    /**
     * All resources for a finding type (per-finding-type page) + recommendation.
     */
    public function byType(Request $request, string $orgId, string $findingType): JsonResponse
    {
        $user = auth()->user();
        $organization = $request->get('organization');

        if (!$organization) {
            return response()->json(['error' => 'Organization not found'], 404);
        }

        if (!$this->permission->canViewFindings($user, $organization)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $results = ScanResult::with(['scan.awsAccount'])
            ->whereHas('scan', fn ($q) => $q->where('organization_id', $organization->id)->where('status', 'completed'))
            ->where('finding_type', $findingType)
            ->orderBySeverity()
            ->orderBy('created_at', 'asc')
            ->get();

        $recommendation = $this->recommendations->getByRuleId($findingType);

        $findings = $results->map(function (ScanResult $result) {
            return [
                'id' => $result->id,
                'scanId' => $result->scan_id,
                'title' => $result->title,
                'description' => $result->description,
                'severity' => $result->severity,
                'service' => $result->service,
                'resourceId' => $result->resource_id,
                'resourceType' => $result->resource_type,
                'status' => $result->status,
                'createdAt' => $result->created_at->toISOString(),
                'awsAccountId' => $result->scan?->awsAccount?->id,
                'awsAccountName' => $result->scan?->awsAccount?->name,
            ];
        });

        $first = $results->first();
        $title = $first?->title ?? $findingType;
        $description = $first?->description ?? '';

        return response()->json([
            'findingType' => $findingType,
            'title' => $title,
            'description' => $description,
            'recommendation' => $recommendation,
            'findings' => $findings,
            'total' => $findings->count(),
        ]);
    }

    /**
     * Get recommendations (for grouping in UI).
     */
    public function recommendations(Request $request): JsonResponse
    {
        $user = auth()->user();
        $organization = $request->get('organization');

        if (!$organization) {
            return response()->json(['error' => 'Organization not found'], 404);
        }

        if (!$this->permission->canViewFindings($user, $organization)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $data = $this->recommendations->load();

        return response()->json($data);
    }
}
