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
    protected $description = 'Install Flow package: publish status and idempotency_keys migrations, then vendor assets.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $filesystem = new Filesystem;
        $migrationDir = database_path('migrations');

        if (! $filesystem->exists($migrationDir)) {
            $filesystem->makeDirectory($migrationDir, 0755, true);
        }

        $stubs = [
            'create_statuses_table.stub' => 'create_statuses_table.php',
            'create_idempotency_keys_table.stub' => 'create_idempotency_keys_table.php',
        ];

        $base = now();
        $index = 0;

        foreach ($stubs as $stubName => $targetName) {
            $stubPath = __DIR__.'/../../../database/migrations/'.$stubName;

            if (! $filesystem->exists($stubPath)) {
                $this->error('Migration stub not found: '.$stubPath);

                return 1;
            }

            $timestamp = $base->copy()->addSeconds($index)->format('Y_m_d_His');
            $migrationFile = $migrationDir.'/'.$timestamp.'_'.$targetName;
            $filesystem->copy($stubPath, $migrationFile);
            $this->info('Migration created: '.$migrationFile);
            $index++;
        }

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
