<?php

namespace Perfocard\Flow;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Perfocard\Flow\Models\Status;

class FlowServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/flow.php', 'flow');

        Gate::policy(Status::class, config('flow.status.policy'));
    }

    /**
     * Bootstrap the application events.
     *
     * @return void
     */
    public function boot()
    {
        $this->loadJsonTranslationsFrom(__DIR__.'/../lang');
        $this->loadJsonTranslationsFrom(lang_path('vendor/flow'));

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/flow.php' => config_path('flow.php'),
            ], 'config');

            $this->publishes([
                __DIR__.'/../stubs/nova/resource.stub' => base_path('stubs/nova/resource.stub'),
                __DIR__.'/../stubs/event.stub' => base_path('stubs/event.stub'),
                __DIR__.'/../stubs/enum.stub' => base_path('stubs/enum.stub'),
                __DIR__.'/../stubs/listener.queued.stub' => base_path('stubs/listener.queued.stub'),
                __DIR__.'/../stubs/listener.typed.queued.stub' => base_path('stubs/listener.typed.queued.stub'),
                __DIR__.'/../stubs/status.stub' => base_path('stubs/status.stub'),
                __DIR__.'/../stubs/model.stub' => base_path('stubs/model.stub'),
            ], 'stubs');

            $this->publishes([
                __DIR__.'/../publish/EnumMakeCommand.stub' => base_path('app/Console/Commands/EnumMakeCommand.php'),
            ], 'commands');

            $this->publishes([
                __DIR__.'/../lang' => $this->app->langPath('vendor/flow'),
            ], 'langs');

            // Register console commands
            $this->commands([
                \Perfocard\Flow\Console\Commands\StatusMakeCommand::class,
                \Perfocard\Flow\Console\Commands\EndpointMakeCommand::class,
                \Perfocard\Flow\Console\Commands\TaskMakeCommand::class,
                \Perfocard\Flow\Console\Commands\FlowInstallCommand::class,
                \Perfocard\Flow\Console\Commands\CompressCommand::class,
                \Perfocard\Flow\Console\Commands\PurgeCommand::class,
                \Perfocard\Flow\Console\Commands\SanitizerMakeCommand::class,
            ]);
        }
    }
}
