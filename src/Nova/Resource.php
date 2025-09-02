<?php

namespace Perfocard\Flow\Nova;

use Laravel\Nova\Fields\HasMany;
use Laravel\Nova\Http\Requests\ActionRequest;
use Laravel\Nova\Resource as NovaResource;
use Perfocard\Flow\Contracts\ShouldBeCompressed;
use Perfocard\Flow\Contracts\ShouldBeDefibrillated;
use Perfocard\Flow\Contracts\ShouldBeTouched;
use Perfocard\Flow\Contracts\ShouldCollectStatus;
use Perfocard\Flow\Nova\Actions\CompressResource;
use Perfocard\Flow\Nova\Actions\DefibrillateStatus;
use Perfocard\Flow\Nova\Actions\ExtractResource;
use Perfocard\Flow\Nova\Actions\PurgeResource;
use Perfocard\Flow\Nova\Actions\TouchResource;
use Perfocard\Flow\Nova\Fields\DateTime;
use Perfocard\Flow\Nova\Fields\Status;
use Perfocard\Flow\Nova\Resources\Status as StatusResource;

abstract class Resource extends NovaResource
{
    public function mergeFields(array $fields)
    {
        $fields = [
            ...$fields,
            DateTime::make(__('Created At'), 'created_at')->exceptOnForms()->showOnPreview(),
        ];

        if ($this->resource instanceof ShouldCollectStatus and $this->resource->status) {
            $fields = [
                Status::make(get_class($this->resource->status)),
                ...$fields,
                HasMany::make(__('Statuses history'), 'statuses', StatusResource::class),
            ];
        }

        return $fields;
    }

    public function mergeActions(array $actions)
    {
        return [
            ...$actions,
            TouchResource::make()
                ->size('sm')
                ->confirmButtonText(__('Touch'))
                ->confirmText(__('Are you sure you want to touch this resource? This will update its timestamp, duplicate the current status, and dispatch related events.'))
                ->canSee(fn ($request) => $this->canBeTouched($request))
                ->sole(),

            DefibrillateStatus::make()
                ->size('sm')
                ->confirmButtonText(__('Defibrillate'))
                ->confirmText(__('Are you sure you want to defibrillate this resource? This will reset its status to the initial state and restart the process.'))
                ->canSee(fn ($request) => $this->canBeDefibrillated($request))
                ->sole(),

            CompressResource::make()
                ->size('sm')
                ->confirmButtonText(__('Compress Resource'))
                ->confirmText(__('Are you sure you want to compress this resource? The existing payload will be moved from the database to remote storage, written into a file, and archived. You will be able to restore the payload later.'))
                ->canSee(fn ($request) => $this->canBeCompressed($request))
                ->sole(),

            ExtractResource::make()
                ->size('sm')
                ->confirmButtonText(__('Extract Resource'))
                ->confirmText(__('Are you sure you want to extract this resource? The payload will be restored to the database for a limited time and will then be automatically cleared.'))
                ->canSee(fn ($request) => $this->canBeExtracted($request))
                ->sole(),

            PurgeResource::make()
                ->size('sm')
                ->confirmButtonText(__('Purge Resource'))
                ->confirmText(__('Are you sure you want to purge this resource? This will immediately remove the restored payload from the database instead of waiting for automatic cleanup.'))
                ->canSee(fn ($request) => $this->canBePurged($request))
                ->sole(),
        ];
    }

    private function canBeTouched($request)
    {
        if ($request instanceof ActionRequest) {
            return true;
        }

        return $this->resource->status instanceof ShouldBeTouched;
    }

    private function canBeDefibrillated($request)
    {
        if ($request instanceof ActionRequest) {
            return true;
        }

        return $this->resource->status instanceof ShouldBeDefibrillated
        and $this->resource->status->defibrillate();
    }

    private function canBeCompressed($request)
    {
        if ($request instanceof ActionRequest) {
            return true;
        }

        return $this->resource instanceof ShouldBeCompressed
        and ! $this->resource->compressed_at;
    }

    private function canBeExtracted($request)
    {
        if ($request instanceof ActionRequest) {
            return true;
        }

        return ($this->resource instanceof ShouldBeCompressed)
        && $this->resource->compressed_at
        && ! $this->resource->extracted_at;
    }

    private function canBePurged($request)
    {
        if ($request instanceof ActionRequest) {
            return true;
        }

        return $this->resource instanceof ShouldBeCompressed
        and $this->resource->compressed_at
        and $this->resource->extracted_at;
    }
}
