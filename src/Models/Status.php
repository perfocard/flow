<?php

namespace Perfocard\Flow\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Perfocard\Flow\Contracts\ShouldBeCompressed;

class Status extends Model implements ShouldBeCompressed
{
    protected $fillable = [
        'statusable_type',
        'statusable_id',
        'status',
        'payload',
        'compressed_at',
        'extracted_at',
    ];

    protected $casts = [
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
