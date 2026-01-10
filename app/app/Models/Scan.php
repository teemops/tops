<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Scan extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'organization_id',
        'aws_account_id',
        'status',
        'started_at',
        'completed_at',
        'error_message',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Get the organization that owns the scan
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get the AWS account for this scan
     */
    public function awsAccount(): BelongsTo
    {
        return $this->belongsTo(AwsAccount::class);
    }

    /**
     * Get the scan results
     */
    public function results(): HasMany
    {
        return $this->hasMany(ScanResult::class);
    }
}
