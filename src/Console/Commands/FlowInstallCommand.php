<?php

namespace Perfocard\Flow\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

class FlowInstallCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'flow:install';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install Flow package: publish migration from stub with correct timestamp.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $filesystem = new Filesystem;
        $stubPath = __DIR__.'/../../../database/migrations/create_statuses_table.stub';
        $migrationDir = database_path('migrations');
        $timestamp = now()->format('Y_m_d_His');
        $migrationFile = $migrationDir.'/'.$timestamp.'_create_statuses_table.php';

        if (! $filesystem->exists($stubPath)) {
            $this->error('Migration stub not found: '.$stubPath);

            return 1;
        }

        if (! $filesystem->exists($migrationDir)) {
            $filesystem->makeDirectory($migrationDir, 0755, true);
        }

        $filesystem->copy($stubPath, $migrationFile);
        $this->info('Migration created: '.$migrationFile);

        // Publish package assets (config, stubs, etc.) from the package service provider
        try {
            $this->call('vendor:publish', [
                '--provider' => \Perfocard\Flow\FlowServiceProvider::class,
                '--no-interaction' => true,
            ]);

            $this->info('Published configuration and stubs from Perfocard\\Flow\\FlowServiceProvider.');
        } catch (\Throwable $e) {
            $this->error('Failed to publish vendor files: '.$e->getMessage());
            // continue, do not fail installation because migration was created
        }

        return 0;
    }
}
