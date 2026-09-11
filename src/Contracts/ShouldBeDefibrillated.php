<?php

namespace Perfocard\Flow\Contracts;

/**
 * Contract for status enums that support defibrillation.
 */
interface ShouldBeDefibrillated
{
    /**
     * Defibrillate the current status and return the new status.
     */
    public function defibrillate(): ?self;
}
