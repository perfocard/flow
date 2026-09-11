<?php

namespace Perfocard\Flow\Console\Commands;

use Illuminate\Console\GeneratorCommand;
use Perfocard\Flow\Console\Concerns\ResolvesModelOption;
use Symfony\Component\Console\Input\InputOption;

class CallbackMakeCommand extends GeneratorCommand
{
    use ResolvesModelOption;

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'make:callback';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new callback class.';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Callback';

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

        [$statusUse, $statusInitial, $statusProcessing, $statusError, $statusComplete] = $this->buildStatusReplacements();
        [$modelUse, $modelType, $modelVar] = $this->buildModelReplacements();

        $stub = str_replace(
            [
                '{{ statusUse }}',
                '{{ statusInitial }}',
                '{{ statusProcessing }}',
                '{{ statusError }}',
                '{{ statusComplete }}',
                '{{ modelUse }}',
                '{{ modelType }}',
                '{{ modelVar }}',
            ],
            [
                $statusUse,
                $statusInitial,
                $statusProcessing,
                $statusError,
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
     * Returns strings for use statement and method bodies for statuses.
     *
     * @return array{string,string,string,string,string}
     */
    protected function buildStatusReplacements(): array
    {
        $model = $this->option('model');

        if (! $model) {
            return [
                '',
                '/* TODO: return YourStatusEnum::PENDING; */',
                '/* TODO: return YourStatusEnum::PROCESSING; */',
                '/* TODO: return YourStatusEnum::ERROR; */',
                '/* TODO: return YourStatusEnum::COMPLETE; */',
            ];
        }

        [$statusFqcn, $statusClass] = $this->resolveStatusClass($model);

        return [
            'use '.$statusFqcn.";\n",
            'return '.$statusClass.'::PENDING;',
            'return '.$statusClass.'::PROCESSING;',
            'return '.$statusClass.'::ERROR;',
            'return '.$statusClass.'::COMPLETE;',
        ];
    }

    protected function getStub()
    {
        $publishedStub = base_path('stubs/callback.stub');
        $packageStub = __DIR__.'/../../../stubs/callback.stub';

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
        return $rootNamespace.'\\Callbacks';
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
