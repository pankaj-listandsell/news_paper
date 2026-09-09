<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Models\SocialAccount;
use App\Models\SocialShare;
use App\Social\SocialSharer;
use App\Support\SocialSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Pushes out whatever is still waiting, a handful at a time.
 *
 * Runs inline like the scrape command, because there is no queue worker on
 * the host. Everything about "what may go out" lives in SocialSharer; this
 * command only decides what to offer it, and in what order.
 */
class PublishToSocial extends Command
{
    protected $signature = 'social:publish
                            {--platform= : Only this platform (facebook, linkedin, x)}
                            {--limit= : Override how many posts per platform}
                            {--dry-run : Show what would go out, post nothing}';

    protected $description = 'Post published articles that have not been shared yet';

    public function handle(): int
    {
        $accounts = SocialAccount::where('is_active', true)
            ->where('auto_post', true)
            ->when($this->option('platform'), fn ($q, $p) => $q->where('platform', $p))
            ->get();

        if ($accounts->isEmpty()) {
            $this->warn('No account is switched on for scheduled posting.');

            return self::SUCCESS;
        }

        $limit  = (int) ($this->option('limit') ?: SocialSettings::batchSize());
        $dryRun = (bool) $this->option('dry-run');
        $sharer = new SocialSharer();

        if (SocialSettings::practiceMode() && ! $dryRun) {
            $this->warn('Practice mode is on — nothing will actually leave the server.');
        }

        foreach ($accounts as $account) {
            $waiting = $this->waitingFor($account->platform, $limit);

            $this->line(sprintf(
                '%s: %d article(s) waiting.',
                $account->label(),
                $waiting->count(),
            ));

            foreach ($waiting as $article) {
                if ($dryRun) {
                    $this->line('  would post: ' . $article->title);
                    continue;
                }

                $share = $sharer->share($article, $account->platform);

                if ($share->wasSent()) {
                    $this->info('  sent: ' . $article->title);
                } else {
                    $this->error('  held: ' . $article->title . ' — ' . $share->error);
                    Log::warning("Social post held for {$account->platform}: {$share->error}", [
                        'article' => $article->getKey(),
                    ]);
                }
            }
        }

        return self::SUCCESS;
    }

    /**
     * Articles this platform has not had yet, oldest first so nothing is
     * left behind while newer stories keep jumping the queue.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Article>
     */
    private function waitingFor(string $platform, int $limit)
    {
        $cutoff      = SocialSettings::backlogCutoff();
        $maxAttempts = SocialSettings::maxAttempts();

        return Article::query()
            ->where('status', 'published')
            ->where('published_at', '<=', now())
            // A 301 article is a signpost and a 304 one is frozen; neither is
            // a page worth sending readers to.
            ->where('http_status', 200)
            ->when($cutoff, fn ($q) => $q->where('published_at', '>=', $cutoff))
            ->whereDoesntHave('socialShares', function ($q) use ($platform, $maxAttempts) {
                $q->where('platform', $platform)
                    ->where(function ($q) use ($maxAttempts) {
                        // Already out, deliberately excluded, or tried often
                        // enough that hammering the platform is pointless.
                        $q->whereIn('status', [SocialShare::SENT, SocialShare::SKIPPED])
                            ->orWhere('attempts', '>=', $maxAttempts);
                    });
            })
            ->orderBy('published_at')
            ->limit($limit)
            ->get();
    }
}
