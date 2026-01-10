<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrganizationRequest;
use App\Http\Requests\UpdateOrganizationRequest;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class OrganizationsController extends Controller
{
    /**
     * List all organizations for the authenticated user
     */
    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();
        
        $organizations = $user->organizations()
            ->select('id', 'org_id', 'name', 'is_default', 'created_at')
            ->orderBy('is_default', 'desc')
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function ($org) {
                return [
                    'id' => $org->id,
                    'org_id' => $org->org_id,
                    'name' => $org->name,
                    'is_default' => $org->is_default,
                    'aws_accounts_count' => $org->awsAccounts()->count(),
                    'created_at' => $org->created_at->toISOString(),
                ];
            });

        return response()->json(['organizations' => $organizations]);
    }

    /**
     * Get current organization (from context)
     */
    public function current(Request $request): JsonResponse
    {
        $organization = $request->get('organization');

        if (!$organization) {
            return response()->json(['error' => 'No organization context'], 404);
        }

        return response()->json([
            'id' => $organization->id,
            'name' => $organization->name,
            'org_id' => $organization->org_id, // Internal ID for API calls
            'is_default' => $organization->is_default,
            'created_at' => $organization->created_at->toISOString(),
        ]);
    }

    /**
     * Show a specific organization
     */
    public function show(Request $request, string $orgId): JsonResponse
    {
        $user = auth()->user();
        
        $organization = Organization::where('org_id', $orgId)
            ->where('user_id', $user->id)
            ->firstOrFail();

        return response()->json([
            'id' => $organization->id,
            'name' => $organization->name,
            'org_id' => $organization->org_id,
            'is_default' => $organization->is_default,
            'aws_accounts_count' => $organization->awsAccounts()->count(),
            'created_at' => $organization->created_at->toISOString(),
            'updated_at' => $organization->updated_at->toISOString(),
        ]);
    }

    /**
     * Create a new organization
     */
    public function store(StoreOrganizationRequest $request): JsonResponse
    {
        $user = auth()->user();

        // Check if this will be the default (first organization)
        $isDefault = $user->organizations()->count() === 0;

        $organization = Organization::create([
            'user_id' => $user->id,
            'name' => $request->validated()['name'],
            'is_default' => $isDefault,
        ]);

        return response()->json([
            'id' => $organization->id,
            'name' => $organization->name,
            'org_id' => $organization->org_id,
            'is_default' => $organization->is_default,
            'created_at' => $organization->created_at->toISOString(),
        ], 201);
    }

    /**
     * Update an organization
     */
    public function update(UpdateOrganizationRequest $request, string $orgId): JsonResponse
    {
        $user = auth()->user();
        
        $organization = Organization::where('org_id', $orgId)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $organization->update($request->validated());

        return response()->json([
            'id' => $organization->id,
            'name' => $organization->name,
            'org_id' => $organization->org_id,
            'is_default' => $organization->is_default,
            'updated_at' => $organization->updated_at->toISOString(),
        ]);
    }

    /**
     * Delete an organization
     */
    public function destroy(Request $request, string $orgId): JsonResponse
    {
        $user = auth()->user();
        
        $organization = Organization::where('org_id', $orgId)
            ->where('user_id', $user->id)
            ->firstOrFail();

        // Business rules: Cannot delete if it's the only organization
        if ($user->organizations()->count() === 1) {
            return response()->json([
                'error' => 'Cannot delete the last organization'
            ], 422);
        }

        // Cannot delete if has AWS accounts
        if ($organization->awsAccounts()->count() > 0) {
            return response()->json([
                'error' => 'Cannot delete organization with AWS accounts. Please remove all AWS accounts first.'
            ], 422);
        }

        $organization->delete();

        return response()->json(['success' => true]);
    }
}
