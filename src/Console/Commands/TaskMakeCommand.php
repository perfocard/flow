<?php

namespace Perfocard\Flow\Console\Commands;

use Illuminate\Console\GeneratorCommand;
use Perfocard\Flow\Console\Concerns\ResolvesModelOption;
use Symfony\Component\Console\Input\InputOption;

class TaskMakeCommand extends GeneratorCommand
{
    use ResolvesModelOption;

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'make:task';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new task';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Task';

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

        [$statusUse, $statusProcessing, $statusComplete] = $this->buildStatusReplacements();
        [$modelUse, $modelType, $modelVar] = $this->buildModelReplacements();

        $stub = str_replace(
            [
                '{{ statusUse }}',
                '{{ statusProcessing }}',
                '{{ statusComplete }}',
                '{{ modelUse }}',
                '{{ modelType }}',
                '{{ modelVar }}',
            ],
            [
                $statusUse,
                $statusProcessing,
                $statusComplete,
                $modelUse,
                $modelType,
                $modelVar,
            ],
            $stub
        );

        return $stub;
    }

    /**
     * Returns strings for the use statement and method bodies for processing/complete.
     *
     * @return array{string,string,string}
     */
    protected function buildStatusReplacements(): array
    {
        $model = $this->option('model');

        if (! $model) {
            return [
                '',
                '/* TODO: return YourStatusEnum::PROCESSING; */',
                '/* TODO: return YourStatusEnum::COMPLETE; */',
            ];
        }

        [$statusFqcn, $statusClass] = $this->resolveStatusClass($model);

        return [
            'use '.$statusFqcn.";\n",
            'return '.$statusClass.'::PROCESSING;',
            'return '.$statusClass.'::COMPLETE;',
        ];
    }

    protected function getStub()
    {
        $publishedStub = base_path('stubs/task.stub');
        $packageStub = __DIR__.'/../../../stubs/task.stub';

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
        return $rootNamespace.'\\Tasks';
    }

    /**
     * Get the console command options.
     *
     * @return array<int, InputOption>
     */
    protected function getOptions()
    {
        return array_merge(parent::getOptions(), [
            ['model', 'm', InputOption::VALUE_OPTIONAL, 'The model class (e.g. Foo/Bar or App\\Models\\Foo\\Bar)'],
        ]);
    }
}
