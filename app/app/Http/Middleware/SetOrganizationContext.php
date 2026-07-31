<?php

namespace App\Http\Middleware;

use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Services\OrganizationPermission;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetOrganizationContext
{
    /**
     * Name of the cookie the frontend uses to remember the selected organization.
     */
    private const SELECTION_COOKIE = 'current_organization_id';

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        if (!$user) {

            // Allow guest auth routes through even if they expect JSON
            if (
                $request->is('auth/firebase/*') ||
                $request->routeIs('firebase.*') ||
                $request->is('login') ||
                $request->is('register')
            ) {
                return $next($request);
            }
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            return $next($request);
        }

        // Get organization from request: cookie (selected org, sent with every request), then header, query, route
        // Cookie ensures sidebar/permissions use the correct org on all pages (dashboard, aws-accounts, etc.)
        $cookieOrgId = $request->cookie(self::SELECTION_COOKIE);

        $orgId = $cookieOrgId
            ?? $request->header('X-Organization-Id')
            ?? $request->query('org_id')
            ?? $request->route('orgId')
            ?? $request->input('organization_id');

        // The cookie is an ambient selection the browser attaches to every request,
        // not something this caller asked for. A cookie naming an org that does not
        // exist is the client's stale state, so heal it rather than failing. An org
        // named explicitly (header, query, route) is a real request and still errors.
        $selectionIsAmbient = $orgId !== null && $orgId === $cookieOrgId;
        $isApi = $request->expectsJson() || $request->is('api/*');

        $organization = null;
        $selectionWasUnusable = false;

        if ($orgId) {
            // Find organization by org_id (internal identifier)
            $organization = Organization::where('org_id', $orgId)->first();

            if (!$organization) {
                if ($isApi && !$selectionIsAmbient) {
                    return response()->json(['error' => 'Organization not found'], 404);
                }

                $selectionWasUnusable = true;
            } elseif (!$this->userBelongsTo($user, $organization)) {
                // Losing access is not healed silently on the API: falling back to
                // another org here would let a write aimed at one tenant land in
                // another. Web requests are page renders only, so they fall back
                // rather than locking the user out.
                if ($isApi) {
                    return response()->json(['error' => 'Access denied'], 403);
                }

                $organization = null;
                $selectionWasUnusable = true;
            } else {
                $this->attachOrganization($request, $user, $organization);
            }
        }

        // No usable selection: fall back to the user's default organization.
        //
        // A web request must never be redirected to organizations.index from here.
        // This middleware runs on the whole web group, organizations.index included,
        // so redirecting means the redirect target hits this same branch and bounces
        // again - an infinite loop that locks the user out of every page. A stale
        // selection (e.g. a cookie left over from a previous install, whose org no
        // longer exists) is not worth an error page: forget it and carry on.
        if (!$organization) {
            $organization = $this->resolveDefaultOrganization($user);

            if ($organization) {
                $this->attachOrganization($request, $user, $organization);
            }
        }

        $response = $next($request);

        if ($selectionWasUnusable) {
            // Stop the browser sending the dead selection back on the next request.
            $response->headers->setCookie(cookie()->forget(self::SELECTION_COOKIE));
        }

        return $response;
    }

    /**
     * Is the user the owner of, or a member of, this organization?
     */
    private function userBelongsTo(User $user, Organization $organization): bool
    {
        return $organization->user_id === $user->id
            || $organization->members()->where('user_id', $user->id)->exists();
    }

    /**
     * Make the organization and the user's role in it available to the rest of
     * the request - both as input (for controllers) and as attributes (for
     * Inertia sharing and permission checks).
     */
    private function attachOrganization(Request $request, User $user, Organization $organization): void
    {
        $request->merge(['organization' => $organization]);
        $request->attributes->set('organization', $organization);

        $role = app(OrganizationPermission::class)->getUserRole($user, $organization);
        $request->merge(['organization_role' => $role]);
        $request->attributes->set('organization_role', $role);
    }

    /**
     * The organization to use when the request did not name a usable one:
     * the default owned org, else the default org they are a member of, else
     * whatever they have. Users with no organization at all get one created,
     * unless they are waiting on an invitation.
     */
    private function resolveDefaultOrganization(User $user): ?Organization
    {
        $organization = $user->ownedOrganizations()->where('is_default', true)->first()
            ?? $user->memberOrganizations()->where('is_default', true)->first()
            ?? $user->ownedOrganizations()->first()
            ?? $user->memberOrganizations()->first();

        if ($organization) {
            return $organization;
        }

        // A user with a pending invitation joins that org instead of getting
        // their own, so don't create one for them here.
        $hasInvitations = OrganizationInvitation::where('email', $user->email)
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->exists();

        if ($hasInvitations) {
            return null;
        }

        $organization = Organization::create([
            'user_id' => $user->id,
            'name' => $user->name . "'s Organization",
            'is_default' => true,
        ]);

        OrganizationMember::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'role' => 'owner',
        ]);

        return $organization;
    }
}
