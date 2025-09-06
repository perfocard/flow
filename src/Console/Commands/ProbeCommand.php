<?php

namespace Perfocard\Flow\Console\Commands;

use DateInterval;
use DateTime;
use DateTimeImmutable;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Perfocard\Flow\Contracts\Probeable;

class ProbeCommand extends Command
{
    protected $signature = 'flow:probe
        {--model= : Process only a single model (FQCN)}
        {--limit=200 : Maximum records}
        {--dry : Dry run — do not create anything}';

    protected $description = 'Periodically create new Probes for models configured in config(flow.probes).';

    public function handle(): int
    {
        $map = config('flow.probes', []);
        $onlyModel = $this->option('model');
        $globalLimit = (int) $this->option('limit');
        $dry = (bool) $this->option('dry');

        if (empty($map)) {
            $this->info("config 'flow.probes' is empty — nothing to do.");

            return self::SUCCESS;
        }

        foreach ($map as $modelClass => $cfg) {
            if ($onlyModel && $onlyModel !== $modelClass) {
                continue;
            }
            if (! class_exists($modelClass)) {
                $this->warn("Model $modelClass not found.");

                continue;
            }

            $probeClass = $cfg['probe_model'] ?? null;
            $triggers = $cfg['trigger_statuses'] ?? ($cfg['trigger_status'] ?? []); // fallback
            $grace = $cfg['grace'] ?? null; // seconds or ISO8601
            $batch = (int) ($cfg['batch'] ?? $globalLimit);

            if (! $probeClass || empty($triggers) || ! $grace) {
                $this->warn("Incomplete config for $modelClass (probe_model/trigger_statuses/grace). Skipping.");

                continue;
            }

            $triggerValues = $this->normalizeTriggerValues($triggers);
            $cutoff = $this->cutoffFromGrace($grace); // Carbon

            $this->line("▶ $modelClass (cutoff: {$cutoff->toDateTimeString()})");

            // Select records in trigger statuses for which there is NO probe newer than cutoff
            /** @var \Illuminate\Database\Eloquent\Builder $q */
            $q = $modelClass::query()
                ->whereIn('status', $triggerValues)
                ->whereDoesntHave('probes', fn ($p) => $p->where('created_at', '>', $cutoff))
                ->where('created_at', '<=', $cutoff) // to avoid creating probes for too recent records
                ->orderBy('id')
                ->limit($batch);

            $created = 0;
            $q->get()->each(function (Model $m) use (&$created) {

                if ($m instanceof Probeable === false) {
                    $this->warn('Model '.get_class($m).' does not implement Probeable. Skipping.');

                    return;
                }

                $m->probes()->create();
                $created++;
            });

            $this->info("Created: $created");
        }

        return self::SUCCESS;
    }

    private function normalizeTriggerValues(array|int|string $trigger): array
    {
        $items = is_array($trigger) ? $trigger : [$trigger];

        return array_values(array_unique(array_map(function ($t) {
            return (is_object($t) && property_exists($t, 'value')) ? $t->value : $t;
        }, $items)));
    }

    private function cutoffFromGrace(int|string $grace): Carbon
    {
        if (is_numeric($grace)) {
            return now()->subSeconds((int) $grace);
        }
        $iv = new DateInterval($grace); // 'PT5M' etc.
        $dt = (new DateTimeImmutable('now'))->sub($iv);

        return Carbon::instance(DateTime::createFromImmutable($dt));
    }
}
