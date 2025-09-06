<?php

namespace Perfocard\Flow\Console\Commands;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\InputOption;

class CallbackMakeCommand extends GeneratorCommand
{
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

        // additional replacements for statuses if -m|--model was provided
        [$statusUse, $statusInitial, $statusProcessing, $statusError, $statusComplete] = $this->buildStatusReplacements();

        $stub = str_replace(
            ['{{ statusUse }}', '{{ statusInitial }}', '{{ statusProcessing }}', '{{ statusError }}', '{{ statusComplete }}'],
            [$statusUse, $statusInitial, $statusProcessing, $statusError, $statusComplete],
            $stub
        );

        return $stub;
    }

    /**
     * Returns strings for use statement and method bodies for processing/complete.
     *
     * @return array{string,string,string}
     */
    protected function buildStatusReplacements(): array
    {
        $model = $this->option('model');

        if (! $model) {
            // if no model provided — remove use and leave TODOs in methods
            return [
                '', // {{ statusUse }}
                '/* TODO: return YourStatusEnum::PENDING; */',
                '/* TODO: return YourStatusEnum::PROCESSING; */',
                '/* TODO: return YourStatusEnum::ERROR; */',
                '/* TODO: return YourStatusEnum::COMPLETE; */',
            ];
        }

        // Normalize the path (Foo/Bar -> Foo\Bar)
        $model = Str::replace('/', '\\', trim($model, '\\'));

        // Determine the root namespace for models (App\Models or App\)
        $rootNamespace = $this->laravel->getNamespace();
        $modelsRoot = is_dir(app_path('Models'))
            ? $rootNamespace.'Models\\'
            : $rootNamespace;

        // If the user already provided a full FQCN — do not prefix
        $qualifiedModel = Str::startsWith($model, $rootNamespace) ? $model : $modelsRoot.$model;

        // Status class: <LastSegment>Status (Foo\Bar -> Foo\BarStatus)
        $statusFqcn = preg_replace('/\\\\([^\\\\]+)$/', '\\\\$1Status', $qualifiedModel);
        $statusClass = class_basename($statusFqcn);

        $statusUse = 'use '.$statusFqcn.";\n";
        $statusInitial = 'return '.$statusClass.'::PENDING;';
        $statusProcessing = 'return '.$statusClass.'::PROCESSING;';
        $statusError = 'return '.$statusClass.'::ERROR;';
        $statusComplete = 'return '.$statusClass.'::COMPLETE;';

        return [$statusUse, $statusInitial, $statusProcessing, $statusError, $statusComplete];
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
     * @return array<int, \Symfony\Component\Console\Input\InputOption>
     */
    protected function getOptions()
    {
        return array_merge(parent::getOptions(), [
            ['model', 'm', InputOption::VALUE_OPTIONAL, 'The model class (e.g. Foo/Bar or App\\Models\\Foo\\Bar)'],
        ]);
    }
}
