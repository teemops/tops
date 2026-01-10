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
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // Get organization from request (header, query param, or route param)
        $orgId = $request->header('X-Organization-Id') 
            ?? $request->query('org_id')
            ?? $request->route('orgId')
            ?? $request->input('organization_id');

        if ($orgId) {
            // Find organization by org_id (internal identifier)
            $organization = \App\Models\Organization::where('org_id', $orgId)
                ->where('user_id', $user->id)
                ->first();

            if (!$organization) {
                return response()->json(['error' => 'Organization not found or access denied'], 404);
            }

            // Attach organization to request
            $request->merge(['organization' => $organization]);
            $request->setUserResolver(function () use ($organization) {
                return $organization;
            });
        } else {
            // Get default organization
            $organization = $user->organizations()
                ->where('is_default', true)
                ->first();

            if (!$organization) {
                // Create default organization if none exists
                $organization = \App\Models\Organization::create([
                    'user_id' => $user->id,
                    'name' => $user->name . "'s Organization",
                    'is_default' => true,
                ]);
            }

            $request->merge(['organization' => $organization]);
        }

        return $next($request);
    }
}
