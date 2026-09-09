<?php

namespace App\Social;

use App\Models\Article;
use App\Models\SocialAccount;

/**
 * Contract for posting one article to one platform.
 *
 * A driver only has to know how to talk to its platform. Deciding what may be
 * posted, recording what went out and not posting anything twice all live in
 * SocialSharer, so every platform behaves the same way.
 */
interface SocialPublisher
{
    /**
     * Does this driver have everything it needs to post?
     */
    public function isConfigured(SocialAccount $account): bool;

    /**
     * Human-readable list of what is still missing, for the settings page.
     *
     * @return array<int, string>
     */
    public function missing(SocialAccount $account): array;

    public function publish(SocialAccount $account, Article $article, SocialMessage $message): SocialResult;
}
