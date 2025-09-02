<?php

namespace Perfocard\Flow\Console\Commands;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\InputOption;

class EndpointMakeCommand extends GeneratorCommand
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'make:endpoint';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new endpoint';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Endpoint';

    /**
     * Replace the class name for the given stub.
     *
     * @param  string  $stub
     * @param  string  $name
     * @return string
     */
    protected function replaceClass($stub, $name)
    {
        $stub = parent::replaceClass($stub, $name);

        $stub = str_replace('{{ class }}', $this->argument('name'), $stub);

        // додаткові заміни для статусів, якщо передано -m|--model
        [$statusUse, $statusProcessing, $statusComplete] = $this->buildStatusReplacements();

        $stub = str_replace(
            ['{{ statusUse }}', '{{ statusProcessing }}', '{{ statusComplete }}'],
            [$statusUse, $statusProcessing, $statusComplete],
            $stub
        );

        return $stub;
    }

    /**
     * Повертає рядки для use та тіла методів processing/complete.
     *
     * @return array{string,string,string}
     */
    protected function buildStatusReplacements(): array
    {
        $model = $this->option('model');

        if (! $model) {
            // якщо модель не передана — прибираємо use і лишаємо TODO у методах
            return [
                '', // {{ statusUse }}
                '/* TODO: return YourStatusEnum::PROCESSING; */',
                '/* TODO: return YourStatusEnum::COMPLETE; */',
            ];
        }

        // Нормалізуємо шлях (Foo/Bar -> Foo\Bar)
        $model = Str::replace('/', '\\', trim($model, '\\'));

        // Визначаємо кореневий простір імен для моделей (App\Models або App\)
        $rootNamespace = $this->laravel->getNamespace();
        $modelsRoot = is_dir(app_path('Models'))
            ? $rootNamespace.'Models\\'
            : $rootNamespace;

        // Якщо юзер уже подав повний FQCN — не префіксуємо
        $qualifiedModel = Str::startsWith($model, $rootNamespace) ? $model : $modelsRoot.$model;

        // Клас статусу: <LastSegment>Status (Foo\Bar -> Foo\BarStatus)
        $statusFqcn = preg_replace('/\\\\([^\\\\]+)$/', '\\\\$1Status', $qualifiedModel);
        $statusClass = class_basename($statusFqcn);

        $statusUse = 'use '.$statusFqcn.';
';
        $statusProcessing = 'return '.$statusClass.'::PROCESSING;';
        $statusComplete = 'return '.$statusClass.'::COMPLETE;';

        return [$statusUse, $statusProcessing, $statusComplete];
    }

    protected function getStub()
    {
        $publishedStub = base_path('stubs/endpoint.stub');
        $packageStub = __DIR__.'/../../../stubs/endpoint.stub';

        return file_exists($publishedStub)
            ? $publishedStub
            : $packageStub;
    }

    /**
     * Get the default namespace for the class.
     *
     * @param  string  $rootNamespace
     * @return string
     */
    protected function getDefaultNamespace($rootNamespace)
    {
        return $rootNamespace.'\Endpoints';
    }

    /**
     * Get the console command options.
     *
     * @return array<int, \Symfony\Component\Console\Input\InputOption>
     */
    protected function getOptions()
    {
        return array_merge(parent::getOptions(), [
            ['model', 'm', InputOption::VALUE_OPTIONAL, 'The model class (e.g. Foo/Bar or App\\Models\\Foo\\Bar)'],
        ]);
    }
}
