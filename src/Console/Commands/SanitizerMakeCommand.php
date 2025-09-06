<?php

namespace Perfocard\Flow\Console\Commands;

use Illuminate\Console\GeneratorCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class SanitizerMakeCommand extends GeneratorCommand
{
    /**
     * The name of the console command.
     *
     * @var string
     */
    protected $name = 'make:sanitizer';

    /**
     * The description of the console command.
     *
     * @var string
     */
    protected $description = 'Create a new Sanitizer class.';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Sanitizer';

    /**
     * Get the path to the stub file.
     *
     * @return string
     */
    protected function getStub()
    {
        $publishedStub = base_path('stubs/sanitizer.stub');
        $packageStub = __DIR__.'/../../../stubs/sanitizer.stub';

        return file_exists($publishedStub)
            ? $publishedStub
            : $packageStub;
    }

    /**
     * Get the default namespace for the generated class.
     *
     * @param  string  $rootNamespace
     * @return string
     */
    protected function getDefaultNamespace($rootNamespace)
    {
        if ($this->option('endpoint')) {
            return $rootNamespace.'\Endpoints';
        }

        if ($this->option('callback')) {
            return $rootNamespace.'\Callbacks';
        }

        return $rootNamespace.'\Sanitizers';
    }

    /**
     * Get the arguments for the console command.
     *
     * @return array
     */
    protected function getArguments()
    {
        return [
            ['name', InputArgument::REQUIRED, 'The name of the sanitizer.'],
        ];
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return [
            ['endpoint', 'e', InputOption::VALUE_NONE, 'Generate the sanitizer in the Endpoints directory'],
            ['callback', 'c', InputOption::VALUE_NONE, 'Generate the sanitizer in the Callbacks directory'],
        ];
    }
}
