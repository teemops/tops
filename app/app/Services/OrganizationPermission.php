<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class OrganizationPermission
{
    /**
     * Get user's role in organization
     * Returns: 'owner', 'administrator', 'auditor', 'viewer', or null
     */
    public function getUserRole(User $user, Organization $org): ?string
    {
        // Check if user is owner
        if ($org->user_id === $user->id) {
            // Ensure owner has a member record (fallback for existing users)
            try {
                $member = $org->members()->where('user_id', $user->id)->first();
                if (!$member) {
                    // Auto-create member record for owner if missing
                    try {
                        $org->members()->create([
                            'user_id' => $user->id,
                            'role' => 'owner',
                        ]);
                    } catch (\Exception $e) {
                        // If create fails (e.g., duplicate key), still return owner
                        // The user is still the owner regardless of member record
                        \Log::warning('Failed to create OrganizationMember for owner', [
                            'user_id' => $user->id,
                            'organization_id' => $org->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                } elseif ($member->role !== 'owner') {
                    // Ensure owner role is set correctly
                    try {
                        $member->update(['role' => 'owner']);
                    } catch (\Exception $e) {
                        \Log::warning('Failed to update OrganizationMember role to owner', [
                            'member_id' => $member->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            } catch (\Exception $e) {
                \Log::warning('Error checking/creating OrganizationMember for owner', [
                    'user_id' => $user->id,
                    'organization_id' => $org->id,
                    'error' => $e->getMessage(),
                ]);
            }
            // Always return 'owner' if user is the organization owner
            return 'owner';
        }

        // Check member record
        $member = $org->members()->where('user_id', $user->id)->first();
        return $member?->role;
    }

    /**
     * Check if user has any role (including owner)
     */
    public function hasRole(User $user, Organization $org): bool
    {
        return $this->getUserRole($user, $org) !== null;
    }

    /**
     * Check if user is member but has no role assigned yet
     */
    public function hasNoRole(User $user, Organization $org): bool
    {
        $member = $org->members()->where('user_id', $user->id)->first();
        return $member !== null && $member->role === null;
    }

    /**
     * Check if user can manage organization settings
     */
    public function canManageSettings(User $user, Organization $org): bool
    {
        $role = $this->getUserRole($user, $org);
        return in_array($role, ['owner', 'administrator']);
    }

    /**
     * Check if user can invite members
     */
    public function canInviteMembers(User $user, Organization $org): bool
    {
        $role = $this->getUserRole($user, $org);
        return in_array($role, ['owner', 'administrator']);
    }

    /**
     * Check if user can manage members (assign roles, remove members)
     */
    public function canManageMembers(User $user, Organization $org): bool
    {
        return in_array($this->getUserRole($user, $org), ['owner', 'administrator']);
    }

    /**
     * Check if user can run scans
     */
    public function canRunScans(User $user, Organization $org): bool
    {
        $role = $this->getUserRole($user, $org);
        return in_array($role, ['owner', 'administrator', 'auditor']);
    }

    /**
     * Check if user can view AWS accounts (Viewer: hide)
     */
    public function canViewAwsAccounts(User $user, Organization $org): bool
    {
        $role = $this->getUserRole($user, $org);
        return $role !== null && $role !== 'viewer';
    }

    /**
     * Check if user can add/edit AWS accounts
     */
    public function canAddAwsAccounts(User $user, Organization $org): bool
    {
        $role = $this->getUserRole($user, $org);
        return in_array($role, ['owner', 'administrator']);
    }

    /**
     * Check if user can delete AWS accounts
     */
    public function canDeleteAwsAccounts(User $user, Organization $org): bool
    {
        $role = $this->getUserRole($user, $org);
        return in_array($role, ['owner', 'administrator']);
    }

    /**
     * Check if user can view scans (Viewer: hide)
     */
    public function canViewScans(User $user, Organization $org): bool
    {
        $role = $this->getUserRole($user, $org);
        return $role !== null && $role !== 'viewer';
    }

    /**
     * Check if user can view findings
     */
    public function canViewFindings(User $user, Organization $org): bool
    {
        // All roles can view findings
        return $this->hasRole($user, $org);
    }

    /**
     * Check if user can view insights
     */
    public function canViewInsights(User $user, Organization $org): bool
    {
        // All roles can view insights
        return $this->hasRole($user, $org);
    }

    /**
     * Check if user can transfer ownership
     */
    public function canTransferOwnership(User $user, Organization $org): bool
    {
        // Only owner can transfer ownership
        return $this->getUserRole($user, $org) === 'owner';
    }

    /**
     * Check if user can delete organization
     */
    public function canDeleteOrganization(User $user, Organization $org): bool
    {
        // Only owner can delete organization
        return $this->getUserRole($user, $org) === 'owner';
    }

    /**
     * Check if user can list members
     */
    public function canListMembers(User $user, Organization $org): bool
    {
        $role = $this->getUserRole($user, $org);
        return in_array($role, ['owner', 'administrator']);
    }
}
