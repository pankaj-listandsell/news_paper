<?php

namespace App\Console\Commands;

use App\Jobs\ScrapeSourceJob;
use App\Models\NewsSource;
use Illuminate\Console\Command;

class ScrapeNews extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'news:scrape
                            {--source= : Only scrape this source id}
                            {--sync : Run inline instead of queueing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch the latest articles from configured RSS news sources';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $sources = NewsSource::active()
            ->when($this->option('source'), fn ($q, $id) => $q->whereKey($id))
            ->get();

        if ($sources->isEmpty()) {
            $this->warn('No active news sources found.');

            return self::SUCCESS;
        }

        foreach ($sources as $source) {
            if ($this->option('sync')) {
                try {
                    $result = (new ScrapeSourceJob($source))->handle();
                    $this->info("✓ {$source->name}: +{$result['created']} new, {$result['updated']} updated");
                } catch (\Throwable $e) {
                    $this->error("✗ {$source->name}: {$e->getMessage()}");
                }
            } else {
                ScrapeSourceJob::dispatch($source);
                $this->line("→ queued: {$source->name}");
            }
        }

        return self::SUCCESS;
    }
}
