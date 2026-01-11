<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScanDetail extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'scan_id',
        'service',
        'resource_type',
        'resource_id',
        'api_method',
        'raw_data',
        'parent_resource_id',
        'region',
    ];

    protected $casts = [
        'raw_data' => 'array',
    ];

    /**
     * Get the scan that owns this detail
     */
    public function scan(): BelongsTo
    {
        return $this->belongsTo(Scan::class);
    }
}
