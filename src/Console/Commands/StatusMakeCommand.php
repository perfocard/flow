<?php

namespace Perfocard\Flow\Console\Commands;

use Illuminate\Console\GeneratorCommand;
use Symfony\Component\Console\Input\InputArgument;

class StatusMakeCommand extends GeneratorCommand
{
    /**
     * The name of the console command.
     *
     * @var string
     */
    protected $name = 'make:status';

    /**
     * The description of the console command.
     *
     * @var string
     */
    protected $description = 'Create a new Status enum for model status management.';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Status';

    /**
     * Replace the class name in the stub.
     *
     * @param  string  $stub
     * @param  string  $name
     * @return string
     */
    protected function replaceClass($stub, $name)
    {
        $stub = parent::replaceClass($stub, $name);

        return str_replace('class', $this->argument('name'), $stub);
    }

    /**
     * Get the path to the stub file.
     *
     * @return string
     */
    protected function getStub()
    {
        $publishedStub = base_path('stubs/status.stub');
        $packageStub = __DIR__.'/../../../stubs/status.stub';

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
        return $rootNamespace.'\Models';
    }

    /**
     * Get the arguments for the console command.
     *
     * @return array
     */
    protected function getArguments()
    {
        return [
            ['name', InputArgument::REQUIRED, 'The name of the enum.'],
        ];
    }
}
