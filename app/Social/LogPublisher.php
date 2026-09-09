<?php

namespace App\Social;

use App\Models\Article;
use App\Models\SocialAccount;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Stands in for a real platform while practice mode is on.
 *
 * It accepts everything and posts nothing, writing the exact message that
 * would have gone out to the log instead. That makes the whole path —
 * button, ledger, scheduled run, retries — testable before a single API
 * credential exists.
 */
class LogPublisher implements SocialPublisher
{
    public function isConfigured(SocialAccount $account): bool
    {
        return true;
    }

    public function missing(SocialAccount $account): array
    {
        return [];
    }

    public function publish(SocialAccount $account, Article $article, SocialMessage $message): SocialResult
    {
        $id = 'practice-' . Str::lower(Str::random(12));

        Log::info("[social:practice] would post to {$account->platform}", [
            'article' => $article->getKey(),
            'message' => $message->text,
        ]);

        return SocialResult::sent($id, $message->url);
    }
}
