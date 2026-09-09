<?php

namespace App\Social;

use App\Models\Article;
use App\Models\SocialAccount;
use App\Models\SocialShare;
use App\Support\SocialSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Posts one article to one platform, and writes down what happened.
 *
 * Everything that is the same for every platform lives here: deciding what
 * may go out, keeping the ledger, counting attempts, and above all making
 * sure a story is never posted twice. The drivers only speak HTTP.
 */
class SocialSharer
{
    public function __construct(private PublisherFactory $publishers = new PublisherFactory())
    {
    }

    /**
     * Send one article to one platform. Safe to call again: an article that
     * already went out is returned untouched rather than posted a second time.
     */
    public function share(Article $article, string $platform): SocialShare
    {
        $share = $this->claim($article, $platform);

        // Already out there, or deliberately excluded — nothing to do.
        if ($share === null) {
            return SocialShare::where('article_id', $article->getKey())
                ->where('platform', $platform)
                ->first();
        }

        if ($reason = $this->refuse($article, $platform)) {
            return $this->hold($share, $reason);
        }

        $account   = $this->account($platform);
        $publisher = $this->publishers->for($platform);

        try {
            $result = $publisher->publish($account, $article, SocialMessage::for($article, $platform));
        } catch (\Throwable $e) {
            // A driver blowing up must never take the caller down with it —
            // a scheduled run has other articles to get through.
            Log::warning("Social post to {$platform} threw: {$e->getMessage()}");
            $result = SocialResult::failed($e->getMessage());
        }

        return $this->record($share, $account, $result);
    }

    /**
     * Take ownership of this article/platform pair, or return null when there
     * is nothing to do.
     *
     * The unique index on (article_id, platform) is what makes this safe: two
     * runs racing each other cannot both end up posting.
     */
    private function claim(Article $article, string $platform): ?SocialShare
    {
        return DB::transaction(function () use ($article, $platform) {
            $share = SocialShare::where('article_id', $article->getKey())
                ->where('platform', $platform)
                ->lockForUpdate()
                ->first();

            if ($share === null) {
                return SocialShare::create([
                    'article_id' => $article->getKey(),
                    'platform'   => $platform,
                    'status'     => SocialShare::PENDING,
                ]);
            }

            // Sent stays sent, and a deliberately skipped article stays skipped.
            if (in_array($share->status, [SocialShare::SENT, SocialShare::SKIPPED], true)) {
                return null;
            }

            return $share;
        });
    }

    /**
     * Why this article may not go out right now, or null when it may.
     */
    private function refuse(Article $article, string $platform): ?string
    {
        if ($article->status !== 'published' || $article->published_at > now()) {
            return 'The article is not published.';
        }

        // A 301 article is a signpost and a 304 one is frozen — neither is a
        // page worth sending readers to.
        if ($article->http_status !== 200) {
            return "The article's page answers with {$article->http_status}.";
        }

        $account = $this->account($platform);

        if (! $account->exists || ! $account->isUsable()) {
            return $account->tokenHasExpired()
                ? 'The access token has expired. Reconnect the account.'
                : 'The account is switched off.';
        }

        $publisher = $this->publishers->for($platform);

        if ($publisher === null) {
            return "No driver is registered for {$platform}.";
        }

        if (! $publisher->isConfigured($account)) {
            return 'Missing credentials: ' . implode(', ', $publisher->missing($account)) . '.';
        }

        return null;
    }

    /**
     * Leave the share waiting, with a note about why it did not go.
     */
    private function hold(SocialShare $share, string $reason): SocialShare
    {
        $share->update([
            'status' => SocialShare::PENDING,
            'error'  => $reason,
        ]);

        return $share;
    }

    private function record(SocialShare $share, SocialAccount $account, SocialResult $result): SocialShare
    {
        $attempts = $share->attempts + 1;

        if ($result->ok) {
            $share->update([
                'status'     => SocialShare::SENT,
                'remote_id'  => $result->remoteId,
                'remote_url' => $result->remoteUrl,
                'error'      => null,
                'attempts'   => $attempts,
                'posted_at'  => now(),
            ]);

            $account->forceFill(['last_posted_at' => now(), 'last_error' => null])->save();

            return $share;
        }

        // Keep retrying until the budget runs out, then stop and let the admin
        // see it rather than hammering the platform forever.
        $share->update([
            'status'   => $attempts >= SocialSettings::maxAttempts()
                ? SocialShare::FAILED
                : SocialShare::PENDING,
            'error'    => $result->error,
            'attempts' => $attempts,
        ]);

        $account->forceFill(['last_error' => $result->error])->save();

        return $share;
    }

    private function account(string $platform): SocialAccount
    {
        return SocialAccount::firstOrNew(['platform' => $platform]);
    }
}
