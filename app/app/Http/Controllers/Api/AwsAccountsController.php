<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\InitAwsAccountRequest;
use App\Http\Requests\StoreAwsAccountRequest;
use App\Models\AwsAccount;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AwsAccountsController extends Controller
{
    /**
     * Initialize AWS account addition (Step 1: CloudFormation setup)
     */
    public function init(Request $request, string $orgId): JsonResponse
    {
        $user = auth()->user();
        
        // Verify organization access
        $organization = Organization::where('org_id', $orgId)
            ->where('user_id', $user->id)
            ->firstOrFail();

        // Create pending AWS account record
        $account = AwsAccount::create([
            'organization_id' => $organization->id,
            'name' => 'Pending AWS Account', // Will be updated when CloudFormation completes
            'status' => 'pending',
        ]);

        // Build CloudFormation URL
        $parentAccountId = config('services.aws.parent_account_id');
        $templateUrl = config('services.aws.cloudformation_template_url');
        
        $cloudFormationUrl = sprintf(
            'https://console.aws.amazon.com/cloudformation/home?#/stacks/quickcreate?templateUrl=%s&param_ParentAWSAccountId=%s&param_ExternalId=%s&param_UniqueId=%s',
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
        
        $organization = Organization::where('org_id', $orgId)
            ->where('user_id', $user->id)
            ->firstOrFail();

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
        
        $account = AwsAccount::with('organization')
            ->whereHas('organization', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->findOrFail($accountId);

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
        
        $organization = Organization::where('org_id', $orgId)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $validated = $request->validated();

        $account = AwsAccount::create([
            'organization_id' => $organization->id,
            'name' => $validated['name'],
            'aws_account_id' => $validated['aws_account_id'],
            'iam_role_arn' => $validated['iam_role_arn'],
            'status' => 'active',
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
        
        $account = AwsAccount::with('organization')
            ->whereHas('organization', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->findOrFail($accountId);

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
        
        $account = AwsAccount::with('organization')
            ->whereHas('organization', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->findOrFail($accountId);

        // Cannot delete if has scans
        if ($account->scans()->count() > 0) {
            return response()->json([
                'error' => 'Cannot delete AWS account with scan history. Please contact support.'
            ], 422);
        }

        $account->delete();

        return response()->json(['success' => true]);
    }

    /**
     * Handle SNS callback from CloudFormation
     * This endpoint receives notifications when CloudFormation stack completes
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
                'status' => 'active',
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
