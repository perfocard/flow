<?php

namespace Perfocard\Flow\Console\Commands;

use Illuminate\Console\Command;
use Perfocard\Flow\Models\IdempotencyKey;

class IdempotencyPruneCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'flow:idempotency:prune';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete expired idempotency keys (expires_at in the past).';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $modelClass = config('flow.idempotency.model', IdempotencyKey::class);
        $deleted = 0;

        do {
            $count = $modelClass::query()
                ->where('expires_at', '<', now())
                ->limit(500)
                ->delete();

            $deleted += $count;
        } while ($count > 0);

        $this->info("Pruned {$deleted} idempotency keys.");

        return self::SUCCESS;
    }
}
