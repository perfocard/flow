<?php

namespace Perfocard\Flow\Console\Commands;

use Illuminate\Console\Command;
use Perfocard\Flow\Compressor;
use Perfocard\Flow\Models\Status;

class CompressCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'flow:compress {model? : Filter by model class name}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Compress uncompressed statuses. Optionally filter by model.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $model = $this->argument('model');
        $timeoutMinutes = config('flow.compression.timeout');
        $threshold = now()->subMinutes($timeoutMinutes);
        $query = Status::query()
            ->whereNull('compressed_at')
            ->where('created_at', '<', $threshold);
        if ($model) {
            $query->where('statusable_type', $model);
        }
        $count = 0;
        foreach ($query->cursor() as $status) {
            Compressor::compress($status);
            $count++;
        }
        $this->info("Compressed {$count} statuses".($model ? " for model {$model}" : ''));

        return 0;
    }
}
