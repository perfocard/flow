<?php

namespace Perfocard\Flow\Models;

use Illuminate\Database\Eloquent\Relations\MorphTo;
use Perfocard\Flow\Contracts\ShouldBeCompressed;

/**
 * @property string|null $payload
 * @property \Illuminate\Support\Carbon|null $compressed_at
 * @property \Illuminate\Support\Carbon|null $extracted_at
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property \Illuminate\Database\Eloquent\Model|null $statusable
 */
class Status extends FlowModel implements ShouldBeCompressed
{
    protected $fillable = [
        'type',
        'statusable_type',
        'statusable_id',
        'status',
        'payload',
        'compressed_at',
        'extracted_at',
    ];

    protected $casts = [
        'type' => StatusType::class,
        'compressed_at' => 'datetime',
        'extracted_at' => 'datetime',
    ];

    /**
     * Get the table associated with the model.
     *
     * @return string
     */
    public function getTable()
    {
        return config('flow.status.table');
    }

    public function statusable(): MorphTo
    {
        return $this->morphTo();
    }
}
