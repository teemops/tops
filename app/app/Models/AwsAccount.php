<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AwsAccount extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'name',
        'aws_account_id',
        'iam_role_arn',
        'external_id',
        'unique_id',
        'status',
        'last_scan_at',
    ];

    protected $casts = [
        'last_scan_at' => 'datetime',
    ];

    /**
     * Generate ExternalId on creation
     * UniqueId is derived from organization.orgId (set in controller)
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($account) {
            // Generate ExternalId for each account (unique per account)
            if (empty($account->external_id)) {
                $account->external_id = \Illuminate\Support\Str::uuid()->toString();
            }
            // UniqueId should be set from organization.orgId in the controller
            // It does not change once an organization is created
        });
    }

    /*
     * iam_role_arn is stored plaintext — see roadmap decision D-9.
     *
     * It was encrypted with Crypt, which meant APP_KEY could not be rotated
     * without a bespoke re-encryption command. The protection was inconsistent
     * anyway: external_id, the ExternalId that actually gates sts:AssumeRole, is
     * stored plaintext and indexed one column over, and in a single-host
     * deployment APP_KEY lives in .env beside the database. An ARN on its own
     * grants nothing — assuming the role needs sts:AssumeRole permission, a trust
     * policy naming the caller, and that ExternalId.
     *
     * Encryption at rest is now the host's job: disk encryption and database
     * permissions. Rotating APP_KEY is `php artisan key:generate` and touches no
     * application data.
     */

    /**
     * Get the organization that owns the AWS account
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get the scans for this AWS account
     */
    public function scans(): HasMany
    {
        return $this->hasMany(Scan::class);
    }
}
