<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreScanRequest;
use App\Models\AwsAccount;
use App\Models\Organization;
use App\Models\Scan;
use App\Models\ScanResult;
use App\Services\ScanTypesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScansController extends Controller
{
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
     * List scans for an organization
     */
    public function index(Request $request, string $orgId): JsonResponse
    {
        $user = auth()->user();
        
        $organization = Organization::where('org_id', $orgId)
            ->where('user_id', $user->id)
            ->firstOrFail();

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

        // Sorting
        $sortBy = $request->input('sort_by', 'created_at');
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
                $query->orderBy('scans.created_at', $sortOrder);
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
                sort($types);
                return $types[0];
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
        
        $scan = Scan::with(['awsAccount', 'organization'])
            ->whereHas('organization', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->findOrFail($scanId);

        return response()->json([
            'id' => $scan->id,
            'awsAccountId' => $scan->aws_account_id,
            'awsAccountName' => $scan->awsAccount->name ?? 'Unknown',
            'status' => $scan->status,
            'findingsCount' => $scan->results()->count(),
            'createdAt' => $scan->created_at->toISOString(),
            'startedAt' => $scan->started_at?->toISOString(),
            'completedAt' => $scan->completed_at?->toISOString(),
            'errorMessage' => $scan->error_message ?? null,
        ]);
    }

    /**
     * Create a new scan
     */
    public function store(StoreScanRequest $request, string $orgId): JsonResponse
    {
        $user = auth()->user();
        
        $organization = Organization::where('org_id', $orgId)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $validated = $request->validated();

        // Verify AWS account belongs to organization
        $awsAccount = AwsAccount::where('id', $validated['aws_account_id'])
            ->where('organization_id', $organization->id)
            ->where('status', 'completed')
            ->firstOrFail();

        // Create scan record
        $scan = Scan::create([
            'organization_id' => $organization->id,
            'aws_account_id' => $awsAccount->id,
            'scan_types' => $validated['scan_types'],
            'status' => 'pending',
        ]);

        // Dispatch scan job to SQS audit queue
        \App\Jobs\ProcessAuditScanJob::dispatch($scan)
            ->onConnection('sqs-audit');

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
        
        $scan = Scan::with('organization')
            ->whereHas('organization', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->findOrFail($scanId);

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
        $findings = $query->orderByRaw("FIELD(severity, 'critical', 'high', 'medium', 'low')")
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
        
        $scan = Scan::with('organization')
            ->whereHas('organization', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->findOrFail($scanId);

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
