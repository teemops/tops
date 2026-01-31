<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        // Get organization role from attributes (set by SetOrganizationContext middleware)
        $organizationRole = $request->attributes->get('organization_role');
        
        // Fallback: if role is not set, try to calculate it from the organization
        if ($organizationRole === null) {
            $user = $request->user();
            
            if ($user) {
                // Try to get organization from request attributes or input
                $organization = $request->attributes->get('organization') 
                    ?? $request->get('organization');
                
                // If not found, try to look it up from route parameter
                if (!$organization && $request->route('orgId')) {
                    $organization = \App\Models\Organization::where('org_id', $request->route('orgId'))->first();
                }
                
                if ($organization) {
                    $permissionService = app(\App\Services\OrganizationPermission::class);
                    $organizationRole = $permissionService->getUserRole($user, $organization);
                }
            }
        }
        
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
            ],
            'organization_role' => $organizationRole,
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
                'warning' => $request->session()->get('warning'),
                'info' => $request->session()->get('info'),
            ],
        ];
    }
}
