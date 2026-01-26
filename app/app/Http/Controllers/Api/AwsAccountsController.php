<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\InitAwsAccountRequest;
use App\Http\Requests\StoreAwsAccountRequest;
use App\Models\AwsAccount;
use App\Models\Organization;
use App\Services\OrganizationPermission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AwsAccountsController extends Controller
{
    protected OrganizationPermission $permission;

    public function __construct(OrganizationPermission $permission)
    {
        $this->permission = $permission;
    }

    /**
     * Initialize AWS account addition (Step 1: CloudFormation setup)
     */
    public function init(Request $request, string $orgId): JsonResponse
    {
        $user = auth()->user();
        $organization = $request->get('organization');

        if (!$organization) {
            return response()->json(['error' => 'Organization not found'], 404);
        }

        // Check permission - only administrators and owner can add AWS accounts
        if (!$this->permission->canAddAwsAccounts($user, $organization)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Derive UniqueId from organization's orgId (does not change once org is created)
        $uniqueId = $organization->org_id;

        // Create pending AWS account record
        $account = AwsAccount::create([
            'organization_id' => $organization->id,
            'name' => 'Pending AWS Account', // Will be updated when CloudFormation completes
            'status' => 'pending',
            'unique_id' => $uniqueId, // Derived from orgId, same for all accounts in this org
        ]);

        // Build CloudFormation URL
        $parentAccountId = config('services.aws.parent_account_id');
        $templateUrl = config('services.aws.cloudformation_template_url');
        
        $cloudFormationUrl = sprintf(
            'https://console.aws.amazon.com/cloudformation/home?#/stacks/quickcreate?templateUrl=%s&stackName=tops-vendor-audit&param_ParentAWSAccountId=%s&param_ExternalId=%s&param_UniqueId=%s',
            urlencode($templateUrl),
            urlencode($parentAccountId),
            urlencode($account->external_id),
            urlencode($account->unique_id)
        );

        return response()->json([
            'accountId' => $account->id,
            'uniqueId' => $account->unique_id,
            'externalId' => $account->external_id,
            'cloudFormationUrl' => $cloudFormationUrl,
            'status' => $account->status,
        ]);
    }

    /**
     * List AWS accounts for an organization
     */
    public function index(Request $request, string $orgId): JsonResponse
    {
        $user = auth()->user();
        $organization = $request->get('organization');

        if (!$organization) {
            return response()->json(['error' => 'Organization not found'], 404);
        }

        // All members can view AWS accounts (view permission)

        $accounts = $organization->awsAccounts()
            ->select('id', 'name', 'aws_account_id', 'status', 'last_scan_at', 'created_at')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($account) {
                return [
                    'id' => $account->id,
                    'name' => $account->name,
                    'awsAccountId' => $account->aws_account_id,
                    'status' => $account->status,
                    'lastScanAt' => $account->last_scan_at?->toISOString(),
                    'createdAt' => $account->created_at->toISOString(),
                ];
            });

        return response()->json(['accounts' => $accounts]);
    }

    /**
     * Show a specific AWS account
     */
    public function show(Request $request, string $accountId): JsonResponse
    {
        $user = auth()->user();
        $organization = $request->get('organization');

        if (!$organization) {
            return response()->json(['error' => 'Organization not found'], 404);
        }

        $account = AwsAccount::where('id', $accountId)
            ->where('organization_id', $organization->id)
            ->firstOrFail();

        return response()->json([
            'id' => $account->id,
            'name' => $account->name,
            'awsAccountId' => $account->aws_account_id,
            'status' => $account->status,
            'lastScanAt' => $account->last_scan_at?->toISOString(),
            'createdAt' => $account->created_at->toISOString(),
            'updatedAt' => $account->updated_at->toISOString(),
        ]);
    }

    /**
     * Store AWS account manually (fallback)
     */
    public function store(StoreAwsAccountRequest $request, string $orgId): JsonResponse
    {
        $user = auth()->user();
        $organization = $request->get('organization');

        if (!$organization) {
            return response()->json(['error' => 'Organization not found'], 404);
        }

        // Check permission - only administrators and owner can add AWS accounts
        if (!$this->permission->canAddAwsAccounts($user, $organization)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validated();

        // Derive UniqueId from organization's orgId
        $uniqueId = $organization->org_id;

        $account = AwsAccount::create([
            'organization_id' => $organization->id,
            'name' => $validated['name'],
            'aws_account_id' => $validated['aws_account_id'],
            'iam_role_arn' => $validated['iam_role_arn'],
            'unique_id' => $uniqueId, // Derived from orgId
            'status' => 'completed',
        ]);

        return response()->json([
            'id' => $account->id,
            'name' => $account->name,
            'awsAccountId' => $account->aws_account_id,
            'status' => $account->status,
            'createdAt' => $account->created_at->toISOString(),
        ], 201);
    }

    /**
     * Update AWS account
     */
    public function update(Request $request, string $accountId): JsonResponse
    {
        $user = auth()->user();
        $organization = $request->get('organization');

        if (!$organization) {
            return response()->json(['error' => 'Organization not found'], 404);
        }

        // Check permission - only administrators and owner can update AWS accounts
        if (!$this->permission->canAddAwsAccounts($user, $organization)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $account = AwsAccount::where('id', $accountId)
            ->where('organization_id', $organization->id)
            ->firstOrFail();

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'min:2', 'max:255'],
        ]);

        $account->update($validated);

        return response()->json([
            'id' => $account->id,
            'name' => $account->name,
            'awsAccountId' => $account->aws_account_id,
            'status' => $account->status,
            'updatedAt' => $account->updated_at->toISOString(),
        ]);
    }

    /**
     * Delete AWS account
     */
    public function destroy(Request $request, string $accountId): JsonResponse
    {
        $user = auth()->user();
        $organization = $request->get('organization');

        if (!$organization) {
            return response()->json(['error' => 'Organization not found'], 404);
        }

        // Check permission - only administrators and owner can delete AWS accounts
        if (!$this->permission->canAddAwsAccounts($user, $organization)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $account = AwsAccount::where('id', $accountId)
            ->where('organization_id', $organization->id)
            ->firstOrFail();

        // Validate AWS account ID if provided (for confirmation)
        $validated = $request->validate([
            'aws_account_id' => ['sometimes', 'string', 'size:12'],
        ]);

        // If AWS account ID is provided, verify it matches
        if (isset($validated['aws_account_id']) && $account->aws_account_id) {
            if ($validated['aws_account_id'] !== $account->aws_account_id) {
                return response()->json([
                    'error' => 'AWS account ID does not match.'
                ], 422);
            }
        }

        // Allow deletion even with scans - scans remain viewable, linked to soft-deleted account
        $account->delete();

        return response()->json(['success' => true]);
    }

    /**
     * Get CloudFormation Console URL for account deletion
     */
    public function getCloudFormationUrl(Request $request, string $accountId): JsonResponse
    {
        $user = auth()->user();
        $organization = $request->get('organization');

        if (!$organization) {
            return response()->json(['error' => 'Organization not found'], 404);
        }

        // All members can view CloudFormation URL
        $account = AwsAccount::where('id', $accountId)
            ->where('organization_id', $organization->id)
            ->firstOrFail();

        // Get region from config or default to us-east-1
        $region = config('services.aws.region', config('services.ses.region', 'us-east-1'));
        
        // Build CloudFormation stacks URL
        // Note: AWS Console doesn't support direct filtering by stack name in URL
        // User will need to search for "tops-vendor-audit" in the console
        $cloudFormationUrl = sprintf(
            'https://console.aws.amazon.com/cloudformation/home?region=%s#/stacks',
            urlencode($region)
        );

        return response()->json([
            'cloudFormationUrl' => $cloudFormationUrl,
            'stackName' => 'tops-vendor-audit',
        ]);
    }

    /**
     * Handle SNS callback from CloudFormation (Legacy/Backup)
     * NOTE: Primary flow should use SQS Queue polling via ProcessSqsMessages command
     * This endpoint can remain as a fallback but SQS polling is the preferred method
     * 
     * Flow: CloudFormation → SNS → SQS Queue → Laravel SQS Polling Service
     * Environment variables: TOPS_SQS_NAME, TOPS_SQS_ARN
     */
    public function snsCallback(Request $request): JsonResponse
    {
        // Verify SNS signature
        $verifier = new \App\Services\SnsSignatureVerifier();
        if (!$verifier->verify($request)) {
            Log::warning('SNS callback signature verification failed', [
                'headers' => $request->headers->all(),
            ]);
            return response()->json(['error' => 'Invalid signature'], 401);
        }
        
        try {
            $message = json_decode($request->input('Message'), true);
            
            if (!$message) {
                // Handle SNS subscription confirmation
                if ($request->input('Type') === 'SubscriptionConfirmation') {
                    // Subscribe to the topic
                    $subscribeUrl = $request->input('SubscribeURL');
                    Log::info('SNS subscription confirmation', ['url' => $subscribeUrl]);
                    return response()->json(['status' => 'subscription_confirmed']);
                }
                
                return response()->json(['error' => 'Invalid message format'], 400);
            }

            // Extract CloudFormation output values
            $roleArn = $message['TopsRoleArn'] ?? null;
            $externalId = $message['TopsExternalId'] ?? null;
            $uniqueId = $message['TopsUniqueId'] ?? null;
            $orgId = $message['TopsType'] ?? null; // Optional: organization ID

            if (!$roleArn || !$externalId || !$uniqueId) {
                return response()->json(['error' => 'Missing required fields'], 400);
            }

            // Find account by unique_id
            $account = AwsAccount::where('unique_id', $uniqueId)
                ->where('external_id', $externalId)
                ->first();

            if (!$account) {
                Log::warning('SNS callback: Account not found', [
                    'unique_id' => $uniqueId,
                    'external_id' => $externalId,
                ]);
                return response()->json(['error' => 'Account not found'], 404);
            }

            // Extract AWS Account ID from Role ARN
            // Format: arn:aws:iam::123456789012:role/TeemOps
            preg_match('/arn:aws:iam::(\d+):role\/(.+)/', $roleArn, $matches);
            $awsAccountId = $matches[1] ?? null;

            // Update account
            $account->update([
                'iam_role_arn' => $roleArn,
                'aws_account_id' => $awsAccountId,
                'name' => $account->name === 'Pending AWS Account' 
                    ? "AWS Account {$awsAccountId}" 
                    : $account->name,
                'status' => 'completed',
            ]);

            Log::info('AWS account activated via SNS callback', [
                'account_id' => $account->id,
                'aws_account_id' => $awsAccountId,
            ]);

            return response()->json(['success' => true, 'account_id' => $account->id]);
        } catch (\Exception $e) {
            Log::error('SNS callback error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['error' => 'Internal server error'], 500);
        }
    }
}
