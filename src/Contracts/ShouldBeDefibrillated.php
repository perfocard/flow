<?php

namespace Perfocard\Flow\Contracts;

/**
 * Contract for models that support defibrillation of status.
 */
interface ShouldBeDefibrillated
{
    /**
     * Defibrillate the current status and return the new status.
     */
    public function defibrillate(): ?self;
}
