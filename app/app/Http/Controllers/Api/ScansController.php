<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreScanRequest;
use App\Models\AwsAccount;
use App\Models\Organization;
use App\Models\Scan;
use App\Models\ScanResult;
use App\Services\FindingsBreakdownService;
use App\Services\OrganizationPermission;
use App\Services\ScanProfilesService;
use App\Services\ScanTypesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScansController extends Controller
{
    protected OrganizationPermission $permission;

    public function __construct(
        OrganizationPermission $permission,
        protected FindingsBreakdownService $breakdown
    ) {
        $this->permission = $permission;
    }

    /**
     * Get available scan types
     */
    public function scanTypes(): JsonResponse
    {
        return response()->json([
            'scanTypes' => ScanTypesService::getAllWithLabels(),
        ]);
    }

    /**
     * Get available scan profiles (groups). Only profiles whose rulesets have
     * rules are returned, so empty compliance rulesets stay hidden from the modal.
     */
    public function scanProfiles(): JsonResponse
    {
        return response()->json([
            'scanProfiles' => ScanProfilesService::getAvailableWithLabels(),
        ]);
    }

    /**
     * List scans for an organization
     */
    public function index(Request $request, string $orgId): JsonResponse
    {
        $user = auth()->user();
        $organization = $request->get('organization');

        if (!$organization) {
            return response()->json(['error' => 'Organization not found'], 404);
        }

        if (!$this->permission->canViewScans($user, $organization)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $query = Scan::with('awsAccount')
            ->where('scans.organization_id', $organization->id);

        // Filter by AWS account
        if ($request->has('aws_account_id')) {
            $query->where('scans.aws_account_id', $request->input('aws_account_id'));
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('scans.status', $request->input('status'));
        }

        // Filter by scan type
        if ($request->has('scan_type')) {
            $scanType = $request->input('scan_type');
            $query->whereJsonContains('scans.scan_types', $scanType);
        }

        // Sorting - default to started_at desc
        $sortBy = $request->input('sort_by', 'started');
        $sortOrder = $request->input('sort_order', 'desc');
        
        // Validate sort order
        if (!in_array($sortOrder, ['asc', 'desc'])) {
            $sortOrder = 'desc';
        }

        // Handle different sort fields
        $needsCollectionSort = false;
        $needsJoin = false;
        switch ($sortBy) {
            case 'started':
                $query->orderBy('scans.started_at', $sortOrder);
                break;
            case 'account':
                // For account sorting, we need to join but get count first
                $needsJoin = true;
                $query->join('aws_accounts', 'scans.aws_account_id', '=', 'aws_accounts.id')
                    ->orderBy('aws_accounts.name', $sortOrder)
                    ->select('scans.*');
                break;
            case 'status':
                $query->orderBy('scans.status', $sortOrder);
                break;
            case 'findings':
                // For findings count, we need to sort after fetching
                $needsCollectionSort = true;
                $query->orderBy('scans.created_at', $sortOrder);
                break;
            case 'types':
                // For types, we'll sort by the first scan type alphabetically
                $needsCollectionSort = true;
                $query->orderBy('scans.created_at', $sortOrder);
                break;
            default:
                // Default to started_at, fallback to created_at if started_at is null
                $query->orderBy('scans.started_at', $sortOrder)
                    ->orderBy('scans.created_at', $sortOrder);
        }

        // Pagination
        $limit = min($request->input('limit', 20), 100);
        $offset = max($request->input('offset', 0), 0);

        // Get total count before join to avoid ambiguous column errors
        // Clone the query before join to get accurate count
        if ($needsJoin) {
            $countQuery = Scan::where('scans.organization_id', $organization->id);
            // Apply same filters to count query
            if ($request->has('aws_account_id')) {
                $countQuery->where('scans.aws_account_id', $request->input('aws_account_id'));
            }
            if ($request->has('status')) {
                $countQuery->where('scans.status', $request->input('status'));
            }
            if ($request->has('scan_type')) {
                $scanType = $request->input('scan_type');
                $countQuery->whereJsonContains('scans.scan_types', $scanType);
            }
            $total = $countQuery->count();
        } else {
            $total = $query->count();
        }

        // For collection-based sorting, we need to get all results first
        if ($needsCollectionSort) {
            $allScans = $query->get();
        } else {
            $allScans = $query->offset($offset)
                ->limit($limit)
                ->get();
        }

        $scans = $allScans->map(function ($scan) {
            return [
                'id' => $scan->id,
                'awsAccountId' => $scan->aws_account_id,
                'awsAccountName' => $scan->awsAccount->name ?? 'Unknown',
                'status' => $scan->status,
                'scanTypes' => $scan->scan_types ?? [],
                'findingsCount' => $scan->results()->count(),
                'createdAt' => $scan->created_at->toISOString(),
                'startedAt' => $scan->started_at?->toISOString(),
                'completedAt' => $scan->completed_at?->toISOString(),
            ];
        });

        // Handle sorting by findings or types in the collection
        if ($sortBy === 'findings') {
            $scans = $scans->sortBy(function ($scan) {
                return $scan['findingsCount'];
            }, SORT_REGULAR, $sortOrder === 'desc');
        } elseif ($sortBy === 'types') {
            $scans = $scans->sortBy(function ($scan) {
                $types = $scan['scanTypes'] ?? [];
                if (empty($types)) {
                    return '';
                }
                $sortedTypes = $types;
                sort($sortedTypes);
                return $sortedTypes[0];
            }, SORT_REGULAR, $sortOrder === 'desc');
        }

        // Apply pagination for collection-sorted results
        if ($needsCollectionSort) {
            $scans = $scans->slice($offset, $limit)->values();
        }

        return response()->json([
            'scans' => $scans,
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
        ]);
    }

    /**
     * Show a specific scan
     */
    public function show(Request $request, string $scanId): JsonResponse
    {
        $user = auth()->user();
        $organization = $request->get('organization');

        if (!$organization) {
            return response()->json(['error' => 'Organization not found'], 404);
        }

        if (!$this->permission->canViewScans($user, $organization)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $scan = Scan::with('awsAccount')
            ->where('id', $scanId)
            ->where('organization_id', $organization->id)
            ->firstOrFail();

        // Findings are current state, so a breakdown only makes sense under the scan that
        // produced that state. Under an older scan's date the same numbers would be wrong
        // and plausible at once, which is worse than showing nothing. See D-11.
        $latest = $this->latestCompletedScanFor($scan);
        $isLatest = $latest !== null && $latest->id === $scan->id;

        return response()->json([
            'id' => $scan->id,
            'awsAccountId' => $scan->aws_account_id,
            'awsAccountName' => $scan->awsAccount->name ?? 'Unknown',
            'status' => $scan->status,
            // What actually ran. Both are stored and neither was returned before, so the
            // page could not say whether it was looking at a benchmark or a few services.
            'scanTypes' => $scan->scan_types ?? [],
            'rulesets' => $scan->rulesets ?? [],
            // Resolved here rather than shipping the whole label map for the client to
            // navigate: rulesetLabels() returns a ['label' => ..., 'description' => ...]
            // per ruleset, and every consumer that only wants the name has to know that.
            'rulesetLabels' => $this->rulesetLabelsFor($scan->rulesets ?? []),
            // A scan can be 'completed' yet have missed regions. Surfacing that lets the
            // UI say so instead of presenting partial results as a full picture.
            'isPartial' => (bool) $scan->is_partial,
            'findingsCount' => $scan->results()->count(),
            'createdAt' => $scan->created_at->toISOString(),
            'startedAt' => $scan->started_at?->toISOString(),
            'completedAt' => $scan->completed_at?->toISOString(),
            'errorMessage' => $scan->error_message ?? null,
            'isLatestForAccount' => $isLatest,
            'latestScanId' => $latest?->id,
            'breakdown' => $isLatest
                ? $this->breakdown->for($organization->id, $scan->aws_account_id)
                : null,
        ]);
    }

    /**
     * Human labels for the rulesets a scan ran, in the order it ran them.
     *
     * Falls back to the raw ruleset name rather than dropping it — a scan of a ruleset
     * that has since been renamed should still say what it ran.
     *
     * @param  array<int, string>  $rulesets
     * @return array<int, string>
     */
    private function rulesetLabelsFor(array $rulesets): array
    {
        $labels = ScanProfilesService::rulesetLabels();

        return array_values(array_map(
            fn (string $ruleset) => $labels[$ruleset]['label'] ?? $ruleset,
            $rulesets
        ));
    }

    /**
     * The most recently completed scan of the same account, within the same organization.
     *
     * Ordered by completed_at rather than created_at: a scan that started earlier can
     * finish later, and it is the finish that decides which one produced current state.
     */
    private function latestCompletedScanFor(Scan $scan): ?Scan
    {
        return Scan::query()
            ->where('organization_id', $scan->organization_id)
            ->where('aws_account_id', $scan->aws_account_id)
            ->where('status', 'completed')
            ->whereNotNull('completed_at')
            ->orderByDesc('completed_at')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Create a new scan
     */
    public function store(StoreScanRequest $request, string $orgId): JsonResponse
    {
        $user = auth()->user();
        $organization = $request->get('organization');

        if (!$organization) {
            return response()->json(['error' => 'Organization not found'], 404);
        }

        // Check permission - administrators, owner, and auditors can run scans
        if (!$this->permission->canRunScans($user, $organization)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validated();

        // Verify AWS account belongs to organization
        $awsAccount = AwsAccount::where('id', $validated['aws_account_id'])
            ->where('organization_id', $organization->id)
            ->where('status', 'completed')
            ->firstOrFail();

        // A scan is described by which ruleset(s) to evaluate and which services to
        // collect data for.
        //
        //  - profiles alone           → every service the profile covers (the default)
        //  - profiles + scan_types    → the profile's rulesets, narrowed to those services
        //  - scan_types alone         → the basic ruleset (API back-compat)
        //
        // The narrowing case is what lets someone scan just S3 against CIS. The subset is
        // validated in StoreScanRequest, so by here the services are known to be ones the
        // chosen profiles actually have rules for.
        $profiles = $validated['scan_profiles'] ?? [];
        $requestedServices = $validated['scan_types'] ?? [];

        if (!empty($profiles)) {
            $rulesets = ScanProfilesService::rulesetsFor($profiles);
            $scanTypes = empty($requestedServices)
                ? ScanProfilesService::servicesFor($profiles)
                : array_values(array_intersect(
                    ScanProfilesService::selectableServicesForAll($profiles),
                    $requestedServices
                ));
        } else {
            $scanTypes = $requestedServices;
            $rulesets = ['basic'];
        }

        // Create scan record
        $scan = Scan::create([
            'organization_id' => $organization->id,
            'aws_account_id' => $awsAccount->id,
            'scan_types' => $scanTypes,
            'rulesets' => $rulesets,
            'status' => 'pending',
        ]);

        $scanConnection = config('queue.scan_connection', 'sqs-audit');

        try {
            \App\Jobs\ProcessAuditScanJob::dispatch($scan)
                ->onConnection($scanConnection);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Failed to dispatch scan job to queue', [
                'scan_id' => $scan->id,
                'connection' => $scanConnection,
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'error' => 'Scan was created but could not be queued for processing. Check that the queue connection is configured (e.g. for SQS: IAM Roles and that the queue exists). For local dev without SQS, set SCAN_QUEUE_CONNECTION=database and run: php artisan queue:work',
                'scan_id' => $scan->id,
            ], 503);
        }

        return response()->json([
            'id' => $scan->id,
            'awsAccountId' => $scan->aws_account_id,
            'scanTypes' => $scan->scan_types,
            'status' => $scan->status,
            'createdAt' => $scan->created_at->toISOString(),
        ], 201);
    }

    /**
     * Get scan results
     */
    public function results(Request $request, string $scanId): JsonResponse
    {
        $user = auth()->user();
        $organization = $request->get('organization');

        if (!$organization) {
            return response()->json(['error' => 'Organization not found'], 404);
        }

        if (!$this->permission->canViewScans($user, $organization)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $scan = Scan::where('id', $scanId)
            ->where('organization_id', $organization->id)
            ->firstOrFail();

        $query = ScanResult::where('scan_id', $scan->id);

        // Filter by severity
        if ($request->has('severity')) {
            $query->where('severity', $request->input('severity'));
        }

        // Filter by service
        if ($request->has('service')) {
            $query->where('service', $request->input('service'));
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        // Pagination
        $limit = min($request->input('limit', 50), 200);
        $offset = max($request->input('offset', 0), 0);

        $total = $query->count();
        $findings = $query->orderBySeverity()
            ->orderBy('created_at', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get()
            ->map(function ($result) {
                return [
                    'id' => $result->id,
                    'title' => $result->title,
                    'description' => $result->description,
                    'severity' => $result->severity,
                    'service' => $result->service,
                    'resourceId' => $result->resource_id,
                    'resourceType' => $result->resource_type,
                    'status' => $result->status,
                    'remediation' => $result->remediation,
                    'createdAt' => $result->created_at->toISOString(),
                ];
            });

        // Calculate summary
        $summary = [
            'total' => $total,
            'critical' => ScanResult::where('scan_id', $scan->id)->where('severity', 'critical')->count(),
            'high' => ScanResult::where('scan_id', $scan->id)->where('severity', 'high')->count(),
            'medium' => ScanResult::where('scan_id', $scan->id)->where('severity', 'medium')->count(),
            'low' => ScanResult::where('scan_id', $scan->id)->where('severity', 'low')->count(),
        ];

        return response()->json([
            'scanId' => $scan->id,
            'findings' => $findings,
            'summary' => $summary,
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
        ]);
    }

    /**
     * Cancel a scan
     */
    public function cancel(Request $request, string $scanId): JsonResponse
    {
        $user = auth()->user();
        $organization = $request->get('organization');

        if (!$organization) {
            return response()->json(['error' => 'Organization not found'], 404);
        }

        // Administrators, owner, and auditors can cancel scans
        if (!$this->permission->canRunScans($user, $organization)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $scan = Scan::where('id', $scanId)
            ->where('organization_id', $organization->id)
            ->firstOrFail();

        // Only allow cancellation if scan is pending or running
        if (!in_array($scan->status, ['pending', 'running'])) {
            return response()->json([
                'error' => 'Cannot cancel scan with status: ' . $scan->status
            ], 422);
        }

        $scan->update([
            'status' => 'cancelled',
            'completed_at' => now(),
        ]);

        return response()->json([
            'id' => $scan->id,
            'status' => $scan->status,
        ]);
    }
}
