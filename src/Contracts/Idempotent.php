<?php

namespace Perfocard\Flow\Contracts;

interface Idempotent
{
    /**
     * Stable provider/service id shared by Callback and Probe for this integration.
     */
    public function fingerprintScope(): string;

    /**
     * Event identity only — no class FQCN, no scope prefix.
     *
     * @param  mixed  $source  Request (callback) or Response (probe), etc.
     */
    public function fingerprint(mixed $source): string;

    /**
     * Minutes until the idempotency key expires_at.
     */
    public function fingerprintLifetime(): int;
}
