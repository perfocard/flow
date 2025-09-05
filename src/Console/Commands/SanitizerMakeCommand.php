<?php

namespace Perfocard\Flow\Console\Commands;

use Illuminate\Console\GeneratorCommand;
use Symfony\Component\Console\Input\InputArgument;

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
    protected $description = 'Create a new Sanitizer class for Endpoint sanitization.';

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
        return $rootNamespace.'\Endpoints';
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
}
