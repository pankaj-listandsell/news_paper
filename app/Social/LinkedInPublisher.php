<?php

namespace App\Social;

use App\Models\Article;
use App\Models\SocialAccount;
use Illuminate\Support\Facades\Http;

/**
 * Posts to a LinkedIn company page through the Posts API.
 *
 * Needs the organisation's numeric ID and an access token carrying
 * w_organization_social. That permission comes from LinkedIn's Community
 * Management API, which is approval-gated — this driver cannot work until
 * LinkedIn has accepted the application, however correct the code is.
 */
class LinkedInPublisher implements SocialPublisher
{
    /** LinkedIn pins behaviour to a dated version rather than a path segment. */
    private const VERSION = '202411';

    public function isConfigured(SocialAccount $account): bool
    {
        return $this->missing($account) === [];
    }

    public function missing(SocialAccount $account): array
    {
        $missing = [];

        foreach (['organization_id' => 'organisation ID', 'access_token' => 'access token'] as $key => $label) {
            if (blank($account->credential($key))) {
                $missing[] = $label;
            }
        }

        return $missing;
    }

    public function publish(SocialAccount $account, Article $article, SocialMessage $message): SocialResult
    {
        $author = 'urn:li:organization:' . trim((string) $account->credential('organization_id'));

        $response = Http::withToken($account->credential('access_token'))
            ->withHeaders([
                'LinkedIn-Version'          => self::VERSION,
                'X-Restli-Protocol-Version' => '2.0.0',
            ])
            ->timeout(30)
            ->post('https://api.linkedin.com/rest/posts', [
                'author'         => $author,
                'commentary'     => $message->text,
                'visibility'     => 'PUBLIC',
                'lifecycleState' => 'PUBLISHED',
                'distribution'   => [
                    'feedDistribution'               => 'MAIN_FEED',
                    'targetEntities'                 => [],
                    'thirdPartyDistributionChannels' => [],
                ],
                'isReshareDisabledByAuthor' => false,
            ]);

        if ($response->failed()) {
            return SocialResult::failed($this->errorFrom($response));
        }

        // The new post's urn comes back in a header, not the body.
        $urn = $response->header('x-restli-id') ?: $response->json('id');

        return SocialResult::sent(
            $urn ?: null,
            $urn ? "https://www.linkedin.com/feed/update/{$urn}" : null,
        );
    }

    private function errorFrom($response): string
    {
        $message = $response->json('message');

        if ($message) {
            return $message;
        }

        if ($response->status() === 403) {
            return 'LinkedIn refused the post (403). The token is most likely missing w_organization_social.';
        }

        return 'LinkedIn returned HTTP ' . $response->status() . '.';
    }
}
