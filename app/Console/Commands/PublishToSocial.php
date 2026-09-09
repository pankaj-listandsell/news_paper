<?php

namespace App\Console\Commands;

use App\Social\SocialRunner;
use App\Support\SocialSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Pushes out whatever is still waiting, a handful at a time.
 *
 * Runs inline like the scrape command, because there is no queue worker on
 * the host. The work itself lives in SocialRunner, which the "post now"
 * button in the admin panel calls too, so both behave identically.
 */
class PublishToSocial extends Command
{
    protected $signature = 'social:publish
                            {--platform= : Only this platform (facebook, linkedin, x)}
                            {--limit= : Override how many posts per platform}
                            {--dry-run : Show what would go out, post nothing}';

    protected $description = 'Post published articles that have not been shared yet';

    public function handle(SocialRunner $runner): int
    {
        if ($runner->accounts($this->option('platform'))->isEmpty()) {
            $this->warn('No account is switched on for scheduled posting.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');

        if (SocialSettings::practiceMode() && ! $dryRun) {
            $this->warn('Practice mode is on — nothing will actually leave the server.');
        }

        $report = $runner->run(
            $this->option('platform'),
            $this->option('limit') ? (int) $this->option('limit') : null,
            $dryRun,
        );

        foreach ($report->planned as $row) {
            $this->line("  would post to {$row['platform']}: {$row['title']}");
        }

        foreach ($report->posted as $row) {
            $this->info("  sent to {$row['platform']}: {$row['title']}");
        }

        foreach ($report->blocked as $row) {
            $this->error("  held ({$row['platform']}): {$row['title']} — {$row['reason']}");
            Log::warning("Social post held for {$row['platform']}: {$row['reason']}");
        }

        if ($report->didNothing()) {
            $this->line('Nothing waiting.');
        }

        return self::SUCCESS;
    }
}
