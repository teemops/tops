<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\InviteMemberRequest;
use App\Http\Requests\TransferOwnershipRequest;
use App\Http\Requests\UpdateMemberRoleRequest;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Notifications\MemberJoinedNotification;
use App\Notifications\OrganizationInvitation as OrganizationInvitationNotification;
use App\Services\OrganizationPermission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class OrganizationMembersController extends Controller
{
    protected OrganizationPermission $permission;

    public function __construct(OrganizationPermission $permission)
    {
        $this->permission = $permission;
    }

    /**
     * List all members of an organization
     * Permission: Only members with Administrator Role or Owner Role can list
     */
    public function index(Request $request, string $orgId): JsonResponse
    {
        $user = auth()->user();
        $organization = $request->get('organization');

        if (!$organization) {
            return response()->json(['error' => 'Organization not found'], 404);
        }

        // Check permission - only administrators and owner can list members
        if (!$this->permission->canListMembers($user, $organization)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Get owner
        $owner = $organization->owner;
        
        // Get all members
        $members = $organization->members()
            ->with('user')
            ->get()
            ->map(function ($member) use ($organization) {
                return [
                    'id' => $member->id,
                    'user_id' => $member->user_id,
                    'role' => $member->role,
                    'user' => [
                        'id' => $member->user->id,
                        'name' => $member->user->name,
                        'email' => $member->user->email,
                    ],
                    'created_at' => $member->created_at->toISOString(),
                ];
            });

        // Add owner to the list if not already included
        $ownerInList = $members->firstWhere('user_id', $owner->id);
        if (!$ownerInList) {
            $members->prepend([
                'id' => null, // Owner doesn't have a member record ID
                'user_id' => $owner->id,
                'role' => 'owner',
                'user' => [
                    'id' => $owner->id,
                    'name' => $owner->name,
                    'email' => $owner->email,
                ],
                'created_at' => $organization->created_at->toISOString(),
            ]);
        }

        return response()->json(['members' => $members->values()]);
    }

    /**
     * Invite a member to the organization
     * Permission: Only administrators and owner
     */
    public function invite(InviteMemberRequest $request, string $orgId): JsonResponse
    {
        $user = auth()->user();
        $organization = $request->get('organization');

        if (!$organization) {
            return response()->json(['error' => 'Organization not found'], 404);
        }

        // Check permission
        if (!$this->permission->canInviteMembers($user, $organization)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $email = $request->validated()['email'];

        // Check if user is already a member
        $existingMember = $organization->members()->whereHas('user', function ($query) use ($email) {
            $query->where('email', $email);
        })->first();

        if ($existingMember) {
            return response()->json([
                'error' => 'User is already a member of this organization'
            ], 422);
        }

        // Check if user is the owner
        if ($organization->owner->email === $email) {
            return response()->json([
                'error' => 'User is already the owner of this organization'
            ], 422);
        }

        // Check if there's a pending invitation for this email
        $pendingInvitation = $organization->invitations()
            ->where('email', $email)
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->first();

        if ($pendingInvitation) {
            return response()->json([
                'error' => 'An invitation has already been sent to this email address'
            ], 422);
        }

        // Create invitation
        $invitation = OrganizationInvitation::create([
            'organization_id' => $organization->id,
            'email' => $email,
            'token' => Str::uuid()->toString(),
            'invited_by' => $user->id,
            'expires_at' => now()->addHours(24),
        ]);

        // Load relationships for notification
        $invitation->load(['organization', 'invitedBy']);

        // Send invitation email
        // Note: Since we're sending to an email address (not a User), we use Notification::route
        Notification::route('mail', $email)
            ->notify(new OrganizationInvitationNotification($invitation));

        return response()->json([
            'id' => $invitation->id,
            'email' => $invitation->email,
            'token' => $invitation->token,
            'expires_at' => $invitation->expires_at->toISOString(),
            'created_at' => $invitation->created_at->toISOString(),
        ], 201);
    }

    /**
     * Update a member's role
     * Permission: Only owner
     */
    public function update(UpdateMemberRoleRequest $request, string $orgId, string $memberId): JsonResponse
    {
        $user = auth()->user();
        $organization = $request->get('organization');

        if (!$organization) {
            return response()->json(['error' => 'Organization not found'], 404);
        }

        // Check permission - only owner can change roles
        if (!$this->permission->canManageMembers($user, $organization)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $member = OrganizationMember::findOrFail($memberId);

        // Verify member belongs to this organization
        if ($member->organization_id !== $organization->id) {
            return response()->json(['error' => 'Member not found'], 404);
        }

        $newRole = $request->validated()['role'];

        // If assigning owner role, transfer ownership
        if ($newRole === 'owner') {
            $organization->transferOwnership($member->user);
            
            return response()->json([
                'id' => $member->id,
                'user_id' => $member->user_id,
                'role' => 'owner',
                'message' => 'Ownership transferred successfully',
            ]);
        }

        // Cannot change owner's role (must transfer ownership first)
        if ($member->role === 'owner' || $organization->isOwner($member->user)) {
            return response()->json([
                'error' => 'Cannot change owner role. Transfer ownership first.'
            ], 422);
        }

        // Update role
        $member->update(['role' => $newRole]);

        return response()->json([
            'id' => $member->id,
            'user_id' => $member->user_id,
            'role' => $member->role,
            'updated_at' => $member->updated_at->toISOString(),
        ]);
    }

    /**
     * Remove a member from the organization
     * Permission: Only owner
     */
    public function destroy(Request $request, string $orgId, string $memberId): JsonResponse
    {
        $user = auth()->user();
        $organization = $request->get('organization');

        if (!$organization) {
            return response()->json(['error' => 'Organization not found'], 404);
        }

        // Check permission - only owner can remove members
        if (!$this->permission->canManageMembers($user, $organization)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $member = OrganizationMember::findOrFail($memberId);

        // Verify member belongs to this organization
        if ($member->organization_id !== $organization->id) {
            return response()->json(['error' => 'Member not found'], 404);
        }

        // Cannot remove owner
        if ($member->role === 'owner' || $organization->isOwner($member->user)) {
            return response()->json([
                'error' => 'Cannot remove owner. Transfer ownership first.'
            ], 422);
        }

        // Cannot remove self if only administrator
        if ($member->user_id === $user->id) {
            $administrators = $organization->members()
                ->where('role', 'administrator')
                ->count();
            
            if ($administrators === 0) {
                return response()->json([
                    'error' => 'Cannot remove yourself if you are the only administrator'
                ], 422);
            }
        }

        // Cannot remove last administrator
        if ($member->role === 'administrator') {
            $administratorCount = $organization->members()
                ->where('role', 'administrator')
                ->count();
            
            if ($administratorCount <= 1) {
                return response()->json([
                    'error' => 'Cannot remove the last administrator'
                ], 422);
            }
        }

        $member->delete();

        return response()->json(['success' => true]);
    }

    /**
     * Accept an invitation
     * Permission: Authenticated user (email must match invitation)
     */
    public function acceptInvitation(Request $request, string $token): JsonResponse
    {
        $user = auth()->user();

        $invitation = OrganizationInvitation::where('token', $token)->firstOrFail();

        // Check if invitation is expired
        if ($invitation->isExpired()) {
            return response()->json([
                'error' => 'This invitation has expired'
            ], 422);
        }

        // Check if invitation was already accepted
        if ($invitation->isAccepted()) {
            return response()->json([
                'error' => 'This invitation has already been accepted'
            ], 422);
        }

        // Verify email matches
        if ($invitation->email !== $user->email) {
            return response()->json([
                'error' => 'This invitation was sent to a different email address'
            ], 403);
        }

        // Check if user is already a member
        $existingMember = $invitation->organization->members()
            ->where('user_id', $user->id)
            ->first();

        if ($existingMember) {
            return response()->json([
                'error' => 'You are already a member of this organization'
            ], 422);
        }

        // Accept invitation
        $member = $invitation->accept($user);

        // Send notification to inviter
        $inviter = $invitation->invitedBy;
        if ($inviter) {
            $inviter->notify(new MemberJoinedNotification($invitation->organization, $user));
        }

        return response()->json([
            'success' => true,
            'organization' => [
                'id' => $invitation->organization->id,
                'org_id' => $invitation->organization->org_id,
                'name' => $invitation->organization->name,
            ],
            'member' => [
                'id' => $member->id,
                'role' => $member->role, // Will be null
            ],
        ]);
    }

    /**
     * List pending invitations
     * Permission: Only administrators and owner
     */
    public function listInvitations(Request $request, string $orgId): JsonResponse
    {
        $user = auth()->user();
        $organization = $request->get('organization');

        if (!$organization) {
            return response()->json(['error' => 'Organization not found'], 404);
        }

        // Check permission
        if (!$this->permission->canInviteMembers($user, $organization)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $invitations = $organization->invitations()
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->with('invitedBy')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($invitation) {
                return [
                    'id' => $invitation->id,
                    'email' => $invitation->email,
                    'expires_at' => $invitation->expires_at->toISOString(),
                    'created_at' => $invitation->created_at->toISOString(),
                    'invited_by' => [
                        'id' => $invitation->invitedBy->id,
                        'name' => $invitation->invitedBy->name,
                    ],
                ];
            });

        return response()->json(['invitations' => $invitations]);
    }

    /**
     * Cancel an invitation
     * Permission: Only administrators and owner
     */
    public function cancelInvitation(Request $request, string $orgId, string $invitationId): JsonResponse
    {
        $user = auth()->user();
        $organization = $request->get('organization');

        if (!$organization) {
            return response()->json(['error' => 'Organization not found'], 404);
        }

        // Check permission
        if (!$this->permission->canInviteMembers($user, $organization)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $invitation = OrganizationInvitation::findOrFail($invitationId);

        // Verify invitation belongs to this organization
        if ($invitation->organization_id !== $organization->id) {
            return response()->json(['error' => 'Invitation not found'], 404);
        }

        // Cannot cancel accepted invitations
        if ($invitation->isAccepted()) {
            return response()->json([
                'error' => 'Cannot cancel an accepted invitation'
            ], 422);
        }

        $invitation->delete();

        return response()->json(['success' => true]);
    }

    /**
     * Transfer ownership to another member
     * Permission: Only owner
     */
    public function transferOwnership(TransferOwnershipRequest $request, string $orgId): JsonResponse
    {
        $user = auth()->user();
        $organization = $request->get('organization');

        if (!$organization) {
            return response()->json(['error' => 'Organization not found'], 404);
        }

        // Check permission - only owner can transfer ownership
        if (!$this->permission->canTransferOwnership($user, $organization)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $newOwnerId = $request->validated()['user_id'];
        $newOwner = User::findOrFail($newOwnerId);

        // Verify new owner is a member of the organization
        $isMember = $organization->members()
            ->where('user_id', $newOwnerId)
            ->exists();

        if (!$isMember && !$organization->isOwner($newOwner)) {
            return response()->json([
                'error' => 'User must be a member of the organization to become owner'
            ], 422);
        }

        // Cannot transfer to self
        if ($organization->isOwner($newOwner)) {
            return response()->json([
                'error' => 'You are already the owner of this organization'
            ], 422);
        }

        // Transfer ownership
        $organization->transferOwnership($newOwner);

        return response()->json([
            'success' => true,
            'organization' => [
                'id' => $organization->id,
                'org_id' => $organization->org_id,
                'name' => $organization->name,
                'user_id' => $organization->user_id,
            ],
            'new_owner' => [
                'id' => $newOwner->id,
                'name' => $newOwner->name,
                'email' => $newOwner->email,
            ],
        ]);
    }
}
