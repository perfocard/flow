<?php

namespace Perfocard\Flow\Nova\Fields;

class Status extends \Laravel\Nova\Fields\Status
{
    public function __construct(string $statusEnum, ?string $name = null, ?string $attribute = null, ?callable $resolveCallback = null)
    {
        parent::__construct($name ?? __('Status'), $attribute ?? 'status', $resolveCallback);

        $loading = [];
        foreach ($statusEnum::loading() as $status) {
            $loading = [
                ...$loading,
                $status->value,
            ];
        }

        $failed = [];
        foreach ($statusEnum::failed() as $status) {
            $failed = [
                ...$failed,
                $status->value,
            ];
        }

        $this->loadingWhen($loading)
            ->failedWhen($failed)
            ->displayUsing(fn ($value) => $statusEnum::tryFrom($value)->label());
    }
}
