<?php

namespace App\Social;

use App\Models\Article;
use App\Models\SocialAccount;
use Illuminate\Support\Facades\Http;

/**
 * Posts to a Facebook Page through the Graph API.
 *
 * Needs a Page ID and a Page access token with pages_manage_posts. The token
 * is the fiddly part: a short-lived user token has to be exchanged for a
 * long-lived one and then for a page token, and Facebook expects the app to
 * have passed App Review before it will do this for a page you do not
 * administer yourself.
 */
class FacebookPublisher implements SocialPublisher
{
    private const VERSION = 'v21.0';

    public function isConfigured(SocialAccount $account): bool
    {
        return $this->missing($account) === [];
    }

    public function missing(SocialAccount $account): array
    {
        $missing = [];

        foreach (['page_id' => 'Page ID', 'access_token' => 'page access token'] as $key => $label) {
            if (blank($account->credential($key))) {
                $missing[] = $label;
            }
        }

        return $missing;
    }

    public function publish(SocialAccount $account, Article $article, SocialMessage $message): SocialResult
    {
        $pageId = $account->credential('page_id');

        $response = Http::asForm()
            ->timeout(30)
            ->post(sprintf('https://graph.facebook.com/%s/%s/feed', self::VERSION, $pageId), [
                'message'      => $message->text,
                'link'         => $message->url,
                'access_token' => $account->credential('access_token'),
            ]);

        if ($response->failed()) {
            return SocialResult::failed($this->errorFrom($response));
        }

        // Graph returns "{page-id}_{post-id}".
        $id   = (string) $response->json('id', '');
        $post = str_contains($id, '_') ? explode('_', $id)[1] : $id;

        return SocialResult::sent(
            $id ?: null,
            $post ? "https://www.facebook.com/{$pageId}/posts/{$post}" : null,
        );
    }

    private function errorFrom($response): string
    {
        $message = $response->json('error.message');
        $type    = $response->json('error.type');

        if ($message) {
            return trim(($type ? "{$type}: " : '') . $message);
        }

        return 'Facebook returned HTTP ' . $response->status() . '.';
    }
}
