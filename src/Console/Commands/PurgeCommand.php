<?php

namespace Perfocard\Flow\Console\Commands;

use Illuminate\Console\Command;
use Perfocard\Flow\Models\Status;

class PurgeCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'flow:purge';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Purge payloads from extracted statuses after a configured timeout.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $timeoutMinutes = config('flow.purge.timeout');
        $threshold = now()->subMinutes($timeoutMinutes);
        $query = Status::query()
            ->whereNotNull('extracted_at')
            ->whereNotNull('payload')
            ->where('extracted_at', '<', $threshold);
        $count = 0;
        foreach ($query->cursor() as $status) {
            $status->payload = null;
            $status->extracted_at = null;
            $status->save();
            $count++;
        }
        $this->info("Purged payloads for {$count} statuses.");

        return 0;
    }
}
