<?php

namespace App\Console\Commands;

use App\Jobs\ScrapeSourceJob;
use App\Mail\ScrapeReport;
use App\Mail\ScrapeStarted;
use App\Models\NewsSource;
use App\Support\ImagePlan;
use App\Support\PublishingBudget;
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
                            {--sync : Run inline instead of queueing}
                            {--no-limit : Ignore the daily posting cap}';

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

        $budget = $this->option('no-limit') ? null : new PublishingBudget();

        // One plan for the whole run, so photographs and drawn images alternate
        // across the site rather than within each feed.
        $images = new ImagePlan();

        // Outside posting hours there is nothing to do. Fetching now would
        // only move the cutoff forward and quietly drop the night's news
        // before the morning run could pick any of it up.
        if ($budget && $budget->allowance() === 0) {
            $this->line(PublishingBudget::isWithinWindow()
                ? "Today's limit is used up ({$budget->publishedBefore()}/{$budget->limit()})."
                : 'Outside posting hours (' . PublishingBudget::WINDOW_START . ':00–' . PublishingBudget::WINDOW_END . ':59).');

            return self::SUCCESS;
        }

        if ($budget) {
            $this->line("Budget: {$budget->allowance()} this run, {$budget->publishedBefore()}/{$budget->limit()} published today.");
        }

        // Notify only for sync runs (queued jobs finish later, off the console)
        // and only when the admin turned summary emails on.
        $notify = $this->option('sync') && SiteSettings::scrapeNotify();

        if ($notify) {
            $this->sendStartedNotice($sources->pluck('name')->all());
        }

        $report = [];

        foreach ($sources as $source) {
            if ($this->option('sync')) {
                try {
                    $result = (new ScrapeSourceJob($source, $budget, $images))->handle();
                    $this->info("✓ {$source->name}: +{$result['created']} new, {$result['updated']} updated");
                    $report[] = ['source' => $source->name, 'created' => $result['created'], 'updated' => $result['updated'], 'error' => null];
                } catch (\Throwable $e) {
                    $this->error("✗ {$source->name}: {$e->getMessage()}");
                    $report[] = ['source' => $source->name, 'created' => 0, 'updated' => 0, 'error' => $e->getMessage()];
                }
            } else {
                ScrapeSourceJob::dispatch($source, $budget, $images);
                $this->line("→ queued: {$source->name}");
            }
        }

        if ($budget) {
            $this->info("Posted {$budget->taken()} this run — {$budget->remainingToday()} left today.");
        }

        if ($images->drawnThisRun() > 0 || $images->limit() > 0) {
            $this->line("Drawn images: {$images->drawnThisRun()} this run, {$images->remaining()} left today.");
        }

        // Email the summary once the run is done.
        if ($notify && $report !== []) {
            $this->sendReport($report);
        }

        return self::SUCCESS;
    }

    /**
     * Mail a "scrape started" notice with the list of sources. Failures are
     * logged, never fatal.
     *
     * @param  array<int, string>  $sourceNames
     */
    private function sendStartedNotice(array $sourceNames): void
    {
        $to = SiteSettings::notifyRecipient();

        if (blank($to)) {
            return;
        }

        try {
            Mail::to($to)->send(new ScrapeStarted($sourceNames, now()->format('d M Y, H:i')));
        } catch (\Throwable $e) {
            Log::warning('Could not send scrape start notice: ' . $e->getMessage());
        }
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
