<?php

namespace App\Console\Commands;

use App\Jobs\ScrapeSourceJob;
use App\Mail\ScrapeReport;
use App\Models\NewsSource;
use App\Support\SiteSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

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

        $report = [];

        foreach ($sources as $source) {
            if ($this->option('sync')) {
                try {
                    $result = (new ScrapeSourceJob($source))->handle();
                    $this->info("✓ {$source->name}: +{$result['created']} new, {$result['updated']} updated");
                    $report[] = ['source' => $source->name, 'created' => $result['created'], 'updated' => $result['updated'], 'error' => null];
                } catch (\Throwable $e) {
                    $this->error("✗ {$source->name}: {$e->getMessage()}");
                    $report[] = ['source' => $source->name, 'created' => 0, 'updated' => 0, 'error' => $e->getMessage()];
                }
            } else {
                ScrapeSourceJob::dispatch($source);
                $this->line("→ queued: {$source->name}");
            }
        }

        // Email a summary — only for sync runs (queued jobs finish later, so
        // there are no results to report here) and only when the admin opted in.
        if ($this->option('sync') && $report !== [] && SiteSettings::scrapeNotify()) {
            $this->sendReport($report);
        }

        return self::SUCCESS;
    }

    /**
     * Mail the run summary to the admin. A mail failure is logged but never
     * fails the scrape itself.
     *
     * @param  array<int, array{source:string, created:int, updated:int, error:?string}>  $report
     */
    private function sendReport(array $report): void
    {
        $to = SiteSettings::notifyRecipient();

        if (blank($to)) {
            return;
        }

        $mail = new ScrapeReport(
            rows: $report,
            totalCreated: array_sum(array_column($report, 'created')),
            totalUpdated: array_sum(array_column($report, 'updated')),
            failures: count(array_filter($report, fn ($r) => $r['error'] !== null)),
        );

        try {
            Mail::to($to)->send($mail);
        } catch (\Throwable $e) {
            Log::warning('Could not send scrape report: ' . $e->getMessage());
        }
    }
}
