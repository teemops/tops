<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class SetOrganizationContext
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        if (!$user) {
            // Handle API vs web routes differently
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }
            return redirect()->route('login');
        }

        // Get organization from request (header, query param, or route param)
        $orgId = $request->header('X-Organization-Id') 
            ?? $request->query('org_id')
            ?? $request->route('orgId')
            ?? $request->input('organization_id');

        if ($orgId) {
            // Find organization by org_id (internal identifier)
            $organization = \App\Models\Organization::where('org_id', $orgId)->first();

            if (!$organization) {
                // Handle API vs web routes differently
                if ($request->expectsJson() || $request->is('api/*')) {
                    return response()->json(['error' => 'Organization not found'], 404);
                }
                return redirect()->route('organizations.index')
                    ->with('error', 'Organization not found');
            }

            // Check if user is owner OR member
            $isOwner = $organization->user_id === $user->id;
            $isMember = $organization->members()->where('user_id', $user->id)->exists();

            if (!$isOwner && !$isMember) {
                // Handle API vs web routes differently
                if ($request->expectsJson() || $request->is('api/*')) {
                    return response()->json(['error' => 'Access denied'], 403);
                }
                return redirect()->route('organizations.index')
                    ->with('error', 'You do not have access to this organization');
            }

            // Attach organization to request
            $request->merge(['organization' => $organization]);
            
            // Attach user's role to request for permission checks
            $permissionService = app(\App\Services\OrganizationPermission::class);
            $role = $permissionService->getUserRole($user, $organization);
            $request->merge(['organization_role' => $role]);
            $request->attributes->set('organization_role', $role); // Also set in attributes for Inertia sharing
            
            $request->setUserResolver(function () use ($organization) {
                return $organization;
            });
        } else {
            // Get default organization (owned or member of)
            $organization = $user->ownedOrganizations()
                ->where('is_default', true)
                ->first();

            // If no owned default org, check member organizations
            if (!$organization) {
                $organization = $user->memberOrganizations()
                    ->where('is_default', true)
                    ->first();
            }

            // If still no organization, get first owned or first member
            if (!$organization) {
                $organization = $user->ownedOrganizations()->first();
                if (!$organization) {
                    $organization = $user->memberOrganizations()->first();
                }
            }

            // Only create default organization if user has no organizations at all
            // (This handles the case where user signs up without invitation)
            if (!$organization) {
                // Check if user has any pending invitations - if so, don't create default org
                $hasInvitations = \App\Models\OrganizationInvitation::where('email', $user->email)
                    ->whereNull('accepted_at')
                    ->where('expires_at', '>', now())
                    ->exists();

                if (!$hasInvitations) {
                    // Create default organization for new user (not invited)
                    $organization = \App\Models\Organization::create([
                        'user_id' => $user->id,
                        'name' => $user->name . "'s Organization",
                        'is_default' => true,
                    ]);

                    // Create owner member record
                    \App\Models\OrganizationMember::create([
                        'organization_id' => $organization->id,
                        'user_id' => $user->id,
                        'role' => 'owner',
                    ]);
                }
            }

            if ($organization) {
                $request->merge(['organization' => $organization]);
                
                // Attach user's role to request
                $permissionService = app(\App\Services\OrganizationPermission::class);
                $role = $permissionService->getUserRole($user, $organization);
                $request->merge(['organization_role' => $role]);
                $request->attributes->set('organization_role', $role); // Also set in attributes for Inertia sharing
            }
        }

        return $next($request);
    }
}
