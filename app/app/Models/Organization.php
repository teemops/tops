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
}
