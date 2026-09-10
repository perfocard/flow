<?php

namespace Perfocard\Flow\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $hash
 * @property \Illuminate\Support\Carbon $expires_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class IdempotencyKey extends Model
{
    protected $fillable = [
        'hash',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    /**
     * Get the table associated with the model.
     *
     * @return string
     */
    public function getTable()
    {
        return config('flow.idempotency.table');
    }
}
