<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;

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

    /**
     * Encrypt IAM Role ARN when setting
     */
    public function setIamRoleArnAttribute($value)
    {
        if ($value === null) {
            $this->attributes['iam_role_arn'] = null;
            return;
        }
        $this->attributes['iam_role_arn'] = Crypt::encryptString($value);
    }

    /**
     * Decrypt IAM Role ARN when getting
     */
    public function getIamRoleArnAttribute($value)
    {
        try {
            return Crypt::decryptString($value);
        } catch (\Exception $e) {
            return $value; // Return encrypted value if decryption fails
        }
    }

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
