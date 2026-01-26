<?php

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationInvitation extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'organization_id',
        'email',
        'token',
        'invited_by',
        'expires_at',
        'accepted_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'accepted_at' => 'datetime',
    ];

    /**
     * Get the organization this invitation is for
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get the user who sent the invitation
     */
    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    /**
     * Check if invitation has expired
     */
    public function isExpired(): bool
    {
        return $this->expires_at < now();
    }

    /**
     * Check if invitation was accepted
     */
    public function isAccepted(): bool
    {
        return $this->accepted_at !== null;
    }

    /**
     * Accept the invitation and create member record
     */
    public function accept(User $user): OrganizationMember
    {
        // Create member record with no role (role will be assigned later)
        $member = OrganizationMember::create([
            'organization_id' => $this->organization_id,
            'user_id' => $user->id,
            'role' => null, // No role assigned yet
        ]);

        // Mark invitation as accepted
        $this->update([
            'accepted_at' => now(),
        ]);

        return $member;
    }
}
