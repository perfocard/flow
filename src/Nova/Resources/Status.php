<?php

namespace Perfocard\Flow\Nova\Resources;

use Illuminate\Http\Request;
use Laravel\Nova\Fields\Code;
use Laravel\Nova\Fields\MorphTo;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Panel;
use Perfocard\Flow\Nova\Fields\DateTime;
use Perfocard\Flow\Nova\Resource;

/**
 * @property \Perfocard\Flow\Models\Status $resource
 */
class Status extends Resource
{
    /**
     * The click action to use when clicking on the resource in the table.
     *
     * Can be one of: 'detail' (default), 'edit', 'select', 'preview', or 'ignore'.
     *
     * @var string
     */
    public static $clickAction = 'preview';

    /**
     * Determine if this resource is available for navigation.
     *
     * @return bool
     */
    public static function availableForNavigation(Request $request)
    {
        return config('flow.status.nova_navigation');
    }

    /**
     * Get the displayable label of the resource.
     *
     * @return string
     */
    public static function label()
    {
        return __('Statuses');
    }

    /**
     * Get the displayable singular label of the resource.
     *
     * @return string
     */
    public static function singularLabel()
    {
        return __('Status');
    }

    /**
     * The model the resource corresponds to.
     *
     * @var class-string<\Perfocard\Flow\Models\Status>
     */
    public static $model = \Perfocard\Flow\Models\Status::class;

    /**
     * The single value that should be used to represent the resource when being displayed.
     *
     * @var string
     */
    public static $title = 'id';

    /**
     * The columns that should be searched.
     *
     * @var array
     */
    public static $search = [];

    /**
     * Get the fields displayed by the resource.
     *
     * @return array
     */
    public function fields(NovaRequest $request)
    {
        return [
            Select::make(__('Status'), 'status')
                ->options(fn () => $this->resource->statusable->getAttribute('status')->map())
                ->displayUsingLabels()
                ->showOnPreview(),

            MorphTo::make(__('Resource'), 'statusable')->showOnPreview(),
            Code::make(__('Payload'), 'payload')->language('javascript')->showOnPreview(),
            DateTime::make(__('Created At'), 'created_at')->showOnPreview(),
            DateTime::make(__('Updated At'), 'updated_at')->showOnPreview()->hideFromIndex(),

            Panel::make(__('Compression'), [
                DateTime::make(__('Compressed At'), 'compressed_at')->showOnPreview(),
                DateTime::make(__('Extracted At'), 'extracted_at')->showOnPreview(),
            ]),
        ];
    }

    /**
     * Get the cards available for the request.
     *
     * @return array
     */
    public function cards(NovaRequest $request)
    {
        return [];
    }

    /**
     * Get the filters available for the resource.
     *
     * @return array
     */
    public function filters(NovaRequest $request)
    {
        return [];
    }

    /**
     * Get the lenses available for the resource.
     *
     * @return array
     */
    public function lenses(NovaRequest $request)
    {
        return [];
    }

    /**
     * Get the actions available for the resource.
     *
     * @return array
     */
    public function actions(NovaRequest $request)
    {
        return $this->mergeActions([]);
    }
}
