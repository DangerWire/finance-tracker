<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PruneStaleImports extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'finance:prune-stale-imports {--hours=24 : Delete staged import files older than this many hours}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete abandoned staged CSV import files older than the retention window';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $hours = max(1, (int) $this->option('hours'));
        $cutoff = now()->subHours($hours)->getTimestamp();
        $disk = Storage::disk('local');

        $files = $disk->files('imports');
        $deleted = 0;

        foreach ($files as $file) {
            if ($disk->lastModified($file) < $cutoff) {
                if ($disk->delete($file)) {
                    $deleted++;
                }
            }
        }

        $this->info("Pruned {$deleted} stale import file(s) older than {$hours} hour(s).");

        return self::SUCCESS;
    }
}
