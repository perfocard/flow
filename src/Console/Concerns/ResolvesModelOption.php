<?php

namespace Perfocard\Flow\Console\Concerns;

use Illuminate\Support\Str;

/**
 * Shared handling of the -m|--model option for the Callback, Endpoint and
 * Task generators.
 */
trait ResolvesModelOption
{
    /**
     * Return model use / type / variable for the generated constructor.
     *
     * @return array{string,string,string}
     */
    protected function buildModelReplacements(): array
    {
        $model = $this->option('model');

        if (! $model) {
            return [
                "use Perfocard\\Flow\\Models\\FlowModel;\n",
                'FlowModel',
                'model',
            ];
        }

        $qualifiedModel = $this->qualifyModelClass($model);
        $modelClass = class_basename($qualifiedModel);

        return [
            'use '.$qualifiedModel.";\n",
            $modelClass,
            Str::camel($modelClass),
        ];
    }

    /**
     * Return the status class FQCN and basename derived from the model option.
     *
     * @return array{string,string}
     */
    protected function resolveStatusClass(string $model): array
    {
        $qualifiedModel = $this->qualifyModelClass($model);
        $statusFqcn = preg_replace('/\\\\([^\\\\]+)$/', '\\\\$1Status', $qualifiedModel);

        return [$statusFqcn, class_basename($statusFqcn)];
    }

    /**
     * Resolve a model option to a fully-qualified class name.
     */
    protected function qualifyModelClass(string $model): string
    {
        $model = Str::replace('/', '\\', trim($model, '\\'));

        $rootNamespace = $this->laravel->getNamespace();
        $modelsRoot = is_dir(app_path('Models'))
            ? $rootNamespace.'Models\\'
            : $rootNamespace;

        return Str::startsWith($model, $rootNamespace) ? $model : $modelsRoot.$model;
    }
}
