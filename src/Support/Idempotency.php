<?php

namespace Perfocard\Flow\Support;

use Illuminate\Database\UniqueConstraintViolationException;
use Perfocard\Flow\Contracts\Idempotent;
use Perfocard\Flow\Models\IdempotencyKey;

class Idempotency
{
    /**
     * Build the stored hash from scope and fingerprint material.
     */
    public static function hash(Idempotent $handler, $model, mixed $source): string
    {
        $material = $handler->fingerprintScope()."\0".$handler->fingerprint($model, $source);

        return hash('sha256', $material);
    }

    /**
     * Claim an idempotency key. Returns the row on success, null on duplicate.
     */
    public static function claim(Idempotent $handler, $model, mixed $source): ?IdempotencyKey
    {
        $modelClass = config('flow.idempotency.model', IdempotencyKey::class);

        try {
            return $modelClass::query()->create([
                'hash' => static::hash($handler, $model, $source),
                'expires_at' => now()->addMinutes($handler->fingerprintLifetime()),
            ]);
        } catch (UniqueConstraintViolationException) {
            return null;
        }
    }

    /**
     * Release a previously claimed key (e.g. after a failed handler).
     */
    public static function release(IdempotencyKey $row): void
    {
        $row->delete();
    }
}
