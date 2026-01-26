<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationMember extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'organization_id',
        'user_id',
        'role',
    ];

    protected $casts = [
        'role' => 'string',
    ];

    /**
     * Get the organization this member belongs to
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get the user that is a member
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
