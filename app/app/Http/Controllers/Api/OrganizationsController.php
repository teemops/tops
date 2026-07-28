<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrganizationRequest;
use App\Http\Requests\UpdateOrganizationRequest;
use App\Models\Organization;
use App\Services\OrganizationPermission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class OrganizationsController extends Controller
{
    protected OrganizationPermission $permission;

    public function __construct(OrganizationPermission $permission)
    {
        $this->permission = $permission;
    }

    /**
     * List all organizations for the authenticated user
     */
    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();
        
        // Get owned organizations
        $owned = $user->ownedOrganizations()
            ->select('id', 'org_id', 'name', 'is_default', 'created_at')
            ->get();
        
        // Get member organizations
        $memberOf = $user->memberOrganizations()
            ->select('organizations.id', 'organizations.org_id', 'organizations.name', 'organizations.is_default', 'organizations.created_at')
            ->get();
        
        // Merge and deduplicate
        $allOrganizations = $owned->merge($memberOf)->unique('id');
        
        // Sort and map (include user's role per organization for permission gating)
        $organizations = $allOrganizations
            ->sortByDesc('is_default')
            ->sortBy('created_at')
            ->values()
            ->map(function ($org) use ($user) {
                $role = $this->permission->getUserRole($user, $org);
                return [
                    'id' => $org->id,
                    'org_id' => $org->org_id,
                    'name' => $org->name,
                    'is_default' => $org->is_default,
                    'aws_accounts_count' => $org->awsAccounts()->count(),
                    'created_at' => $org->created_at->toISOString(),
                    'role' => $role,
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
        $organization = $request->get('organization');

        if (!$organization) {
            return response()->json(['error' => 'Organization not found'], 404);
        }

        // Verify user has access (owner or member)
        $isOwner = $organization->user_id === $user->id;
        $isMember = $organization->members()->where('user_id', $user->id)->exists();

        if (!$isOwner && !$isMember) {
            return response()->json(['error' => 'Access denied'], 403);
        }

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

        // Create owner member record
        \App\Models\OrganizationMember::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'role' => 'owner',
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
        $organization = $request->get('organization');

        if (!$organization) {
            return response()->json(['error' => 'Organization not found'], 404);
        }

        // Check permission - only administrators and owner can update organization
        if (!$this->permission->canManageSettings($user, $organization)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

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
        $organization = $request->get('organization');

        if (!$organization) {
            return response()->json(['error' => 'Organization not found'], 404);
        }

        // Check permission - only owner can delete organization
        if (!$this->permission->canDeleteOrganization($user, $organization)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Business rules: Cannot delete if it's the only organization.
        // organizations() deduplicates: an owner also holds a member record, so
        // summing the two relations counts their own org twice.
        $totalOrgs = $user->organizations()->count();
        if ($totalOrgs === 1) {
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
