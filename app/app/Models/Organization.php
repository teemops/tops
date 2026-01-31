<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Organization extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'org_id',
        'user_id',
        'name',
        'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    /**
     * Generate org_id on creation
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($organization) {
            if (empty($organization->org_id)) {
                $organization->org_id = \Illuminate\Support\Str::uuid()->toString();
            }
        });
    }

    /**
     * Get the user that owns the organization
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the AWS accounts for the organization
     */
    public function awsAccounts(): HasMany
    {
        return $this->hasMany(AwsAccount::class);
    }

    /**
     * Get the scans for the organization
     */
    public function scans(): HasMany
    {
        return $this->hasMany(Scan::class);
    }

    /**
     * Get the members of the organization
     */
    public function members(): HasMany
    {
        return $this->hasMany(OrganizationMember::class);
    }

    /**
     * Get the invitations for the organization
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(OrganizationInvitation::class);
    }

    /**
     * Get the owner of the organization
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Check if user is the owner
     */
    public function isOwner(User $user): bool
    {
        return $this->user_id === $user->id;
    }

    /**
     * Get user's role in organization
     */
    public function getMemberRole(User $user): ?string
    {
        if ($this->isOwner($user)) {
            return 'owner';
        }

        $member = $this->members()->where('user_id', $user->id)->first();
        return $member?->role;
    }

    /**
     * Transfer ownership to another user
     */
    public function transferOwnership(User $newOwner): void
    {
        $oldOwner = $this->owner;
        
        // Update organization owner
        $this->update(['user_id' => $newOwner->id]);

        // Update or create member record for new owner
        $newOwnerMember = $this->members()->where('user_id', $newOwner->id)->first();
        if ($newOwnerMember) {
            $newOwnerMember->update(['role' => 'owner']);
        } else {
            $this->members()->create([
                'user_id' => $newOwner->id,
                'role' => 'owner',
            ]);
        }

        // Update old owner's member record (remove owner role or remove member record)
        if ($oldOwner) {
            $oldOwnerMember = $this->members()->where('user_id', $oldOwner->id)->first();
            if ($oldOwnerMember) {
                // Remove owner role - they'll need to be assigned a new role
                $oldOwnerMember->update(['role' => null]);
            }
        }
    }
}
