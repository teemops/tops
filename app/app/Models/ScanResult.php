<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScanResult extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'scan_id',
        'severity',
        'service',
        'resource_type',
        'resource_id',
        'finding_type',
        'title',
        'description',
        'remediation',
        'status',
        'resolved_at',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    /**
     * Get the scan that owns this result
     */
    public function scan(): BelongsTo
    {
        return $this->belongsTo(Scan::class);
    }
}
