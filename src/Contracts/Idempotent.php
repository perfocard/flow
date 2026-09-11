<?php

namespace Perfocard\Flow\Contracts;

interface Idempotent
{
    /**
     * Stable provider/service id shared by Callback and Probe for this integration.
     */
    public function fingerprintScope(): string;

    /**
     * Event identity only — no class FQCN, no scope prefix, no status.
     * The resolved status is mixed into the hash by Flow itself.
     *
     * @param  mixed  $source  Request (callback) or Response (probe), etc.
     */
    public function fingerprint(mixed $source): string;

    /**
     * Minutes until the idempotency key expires_at.
     */
    public function fingerprintLifetime(): int;

    /**
     * Status cases that must be reached once (final / business-affecting).
     * A hit resolving to any other case is processed and logged as usual,
     * without a fingerprint claim.
     *
     * @return array<int, BackedEnum>
     */
    public function guarded(): array;
}
