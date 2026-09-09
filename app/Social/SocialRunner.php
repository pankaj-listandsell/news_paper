<?php

namespace App\Social;

use App\Models\Article;
use App\Models\SocialAccount;
use App\Models\SocialShare;
use App\Support\SocialSettings;
use Illuminate\Support\Collection;

/**
 * One pass over everything that is still waiting to be posted.
 *
 * Shared by the scheduled command and the "post now" button in the admin
 * panel, so both decide what goes out — and in what order — the same way.
 */
class SocialRunner
{
    public function __construct(private SocialSharer $sharer = new SocialSharer())
    {
    }

    /**
     * @param  string|null  $platform  limit the run to one platform
     * @param  int|null  $limit  override the configured batch size
     */
    public function run(?string $platform = null, ?int $limit = null, bool $dryRun = false): SocialRunReport
    {
        $accounts = $this->accounts($platform);
        $limit    = $limit ?: SocialSettings::batchSize();
        $report   = new SocialRunReport();

        foreach ($accounts as $account) {
            foreach ($this->waitingFor($account->platform, $limit) as $article) {
                if ($dryRun) {
                    $report->wouldPost($account->platform, $article);
                    continue;
                }

                $share = $this->sharer->share($article, $account->platform);

                $share->wasSent()
                    ? $report->sent($account->platform, $article)
                    : $report->held($account->platform, $article, $share->error);
            }
        }

        return $report;
    }

    /**
     * Accounts the scheduled run is allowed to post to.
     *
     * @return Collection<int, SocialAccount>
     */
    public function accounts(?string $platform = null): Collection
    {
        return SocialAccount::where('is_active', true)
            ->where('auto_post', true)
            ->when($platform, fn ($q) => $q->where('platform', $platform))
            ->get();
    }

    /**
     * Articles this platform has not had yet, oldest first so nothing is left
     * behind while newer stories keep jumping the queue.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Article>
     */
    public function waitingFor(string $platform, ?int $limit = null)
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
                        // enough that asking again is pointless.
                        $q->whereIn('status', [SocialShare::SENT, SocialShare::SKIPPED])
                            ->orWhere('attempts', '>=', $maxAttempts);
                    });
            })
            ->orderBy('published_at')
            ->when($limit, fn ($q) => $q->limit($limit))
            ->get();
    }

    /**
     * How many articles are waiting for each connected platform, for the
     * admin panel to show before anything is posted.
     *
     * @return array<string, int>
     */
    public function pendingCounts(): array
    {
        $counts = [];

        foreach ($this->accounts() as $account) {
            $counts[$account->platform] = $this->waitingFor($account->platform)->count();
        }

        return $counts;
    }
}
