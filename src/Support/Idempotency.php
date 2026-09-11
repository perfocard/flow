<?php

namespace Perfocard\Flow\Support;

use BackedEnum;
use Illuminate\Database\UniqueConstraintViolationException;
use Perfocard\Flow\Contracts\Idempotent;
use Perfocard\Flow\Models\IdempotencyKey;

class Idempotency
{
    /**
     * Whether the given status is one the handler wants reached only once.
     * Unguarded statuses are processed and logged without any claim.
     */
    public static function guards(Idempotent $handler, BackedEnum $status): bool
    {
        return in_array($status, $handler->guarded(), true);
    }

    /**
     * Build the stored hash from scope, resolved status and fingerprint material.
     * The same identity landing on the same status yields the same hash.
     */
    public static function hash(Idempotent $handler, mixed $source, BackedEnum $status): string
    {
        $material = implode("\0", [
            $handler->fingerprintScope(),
            $status::class,
            (string) $status->value,
            $handler->fingerprint($source),
        ]);

        return hash('sha256', $material);
    }

    /**
     * Claim an idempotency key. Returns the row on success, null on duplicate.
     */
    public static function claim(Idempotent $handler, mixed $source, BackedEnum $status): ?IdempotencyKey
    {
        $modelClass = config('flow.idempotency.model', IdempotencyKey::class);

        try {
            return $modelClass::query()->create([
                'hash' => static::hash($handler, $source, $status),
                'expires_at' => now()->addMinutes($handler->fingerprintLifetime()),
            ]);
        } catch (UniqueConstraintViolationException) {
            return null;
        }
    }

    /**
     * Whether the handler may apply the given status: true when the status is
     * not guarded, or when the claim was taken; false on a duplicate.
     */
    public static function allows(Idempotent $handler, mixed $source, BackedEnum $status): bool
    {
        if (! static::guards($handler, $status)) {
            return true;
        }

        return static::claim($handler, $source, $status) !== null;
    }

    /**
     * Release a previously claimed key (e.g. after a failed handler).
     */
    public static function release(IdempotencyKey $row): void
    {
        $row->delete();
    }
}
