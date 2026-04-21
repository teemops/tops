<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Scan;
use App\Models\ScanResult;
use App\Services\OrganizationPermission;
use App\Services\RecommendationsLoader;
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

        $query = ScanResult::query()
            ->join('scans', 'scan_results.scan_id', '=', 'scans.id')
            ->where('scans.organization_id', $organization->id)
            ->where('scans.status', 'completed')
            ->select('scan_results.*');

        if ($request->filled('aws_account_id')) {
            $query->where('scans.aws_account_id', $request->input('aws_account_id'));
        }

        if ($request->filled('finding_type')) {
            $query->where('scan_results.finding_type', $request->input('finding_type'));
        }

        if ($request->filled('service')) {
            $query->where('scan_results.service', $request->input('service'));
        }

        if ($request->filled('status')) {
            $query->where('scan_results.status', $request->input('status'));
        }

        $limit = min($request->input('limit', 50), 200);
        $offset = max($request->input('offset', 0), 0);

        $total = $query->count();

        $results = (clone $query)
            ->orderByRaw("FIELD(scan_results.severity, 'critical', 'high', 'medium', 'low')")
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

        $baseQuery = ScanResult::query()
            ->join('scans', 'scan_results.scan_id', '=', 'scans.id')
            ->where('scans.organization_id', $organization->id)
            ->where('scans.status', 'completed');

        if ($request->filled('aws_account_id')) {
            $baseQuery->where('scans.aws_account_id', $request->input('aws_account_id'));
        }
        if ($request->filled('finding_type')) {
            $baseQuery->where('scan_results.finding_type', $request->input('finding_type'));
        }
        if ($request->filled('service')) {
            $baseQuery->where('scan_results.service', $request->input('service'));
        }
        if ($request->filled('status')) {
            $baseQuery->where('scan_results.status', $request->input('status'));
        }

        $bySeverity = [
            'critical' => (clone $baseQuery)->where('scan_results.severity', 'critical')->count(),
            'high' => (clone $baseQuery)->where('scan_results.severity', 'high')->count(),
            'medium' => (clone $baseQuery)->where('scan_results.severity', 'medium')->count(),
            'low' => (clone $baseQuery)->where('scan_results.severity', 'low')->count(),
        ];

        $totalForScore = array_sum($bySeverity);
        $securityScore = $totalForScore === 0
            ? 100
            : max(0, 100 - ($bySeverity['critical'] * 10 + $bySeverity['high'] * 5 + $bySeverity['medium'] * 2 + $bySeverity['low']));

        $summary = [
            'securityScore' => $securityScore,
            'total' => $totalForScore,
            'bySeverity' => $bySeverity,
        ];

        $recommendationsMap = $this->recommendations->getMapByRuleId();

        return response()->json([
            'summary' => $summary,
            'findings' => $findings,
            'recommendationsMap' => $recommendationsMap,
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
        ]);
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
            ->orderByRaw("FIELD(severity, 'critical', 'high', 'medium', 'low')")
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
