<?php

namespace App\Social;

use App\Models\Article;
use App\Models\SocialAccount;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Posts to X through API v2.
 *
 * Authenticated with OAuth 1.0a user context, which is the practical choice
 * for a server that posts as one fixed account: the four credentials do not
 * expire, so there is no refresh dance to keep alive. The trade-off is that
 * the request has to be signed by hand — see signature().
 *
 * Posting is not on the free tier in any useful quantity, so this needs a
 * paid X API plan.
 */
class XPublisher implements SocialPublisher
{
    private const ENDPOINT = 'https://api.twitter.com/2/tweets';

    public function isConfigured(SocialAccount $account): bool
    {
        return $this->missing($account) === [];
    }

    public function missing(SocialAccount $account): array
    {
        $labels = [
            'api_key'             => 'API key',
            'api_secret'          => 'API key secret',
            'access_token'        => 'access token',
            'access_token_secret' => 'access token secret',
        ];

        $missing = [];

        foreach ($labels as $key => $label) {
            if (blank($account->credential($key))) {
                $missing[] = $label;
            }
        }

        return $missing;
    }

    public function publish(SocialAccount $account, Article $article, SocialMessage $message): SocialResult
    {
        $response = Http::withHeaders([
            'Authorization' => $this->authorizationHeader($account),
            'Content-Type'  => 'application/json',
        ])
            ->timeout(30)
            ->post(self::ENDPOINT, ['text' => $message->text]);

        if ($response->failed()) {
            return SocialResult::failed($this->errorFrom($response));
        }

        $id = $response->json('data.id');

        return SocialResult::sent(
            $id,
            $id ? "https://x.com/i/web/status/{$id}" : null,
        );
    }

    /**
     * Build the OAuth 1.0a Authorization header.
     *
     * The JSON body is deliberately left out of the signature: OAuth 1.0a only
     * signs the query string and form fields, and X signs v2 JSON requests
     * with the oauth parameters alone.
     */
    private function authorizationHeader(SocialAccount $account): string
    {
        $oauth = [
            'oauth_consumer_key'     => (string) $account->credential('api_key'),
            'oauth_nonce'            => Str::random(32),
            'oauth_signature_method' => 'HMAC-SHA1',
            'oauth_timestamp'        => (string) time(),
            'oauth_token'            => (string) $account->credential('access_token'),
            'oauth_version'          => '1.0',
        ];

        $oauth['oauth_signature'] = $this->signature(
            $oauth,
            (string) $account->credential('api_secret'),
            (string) $account->credential('access_token_secret'),
        );

        $parts = [];

        foreach ($oauth as $key => $value) {
            $parts[] = rawurlencode($key) . '="' . rawurlencode($value) . '"';
        }

        return 'OAuth ' . implode(', ', $parts);
    }

    /**
     * @param  array<string, string>  $oauth
     */
    private function signature(array $oauth, string $consumerSecret, string $tokenSecret): string
    {
        ksort($oauth);

        $pairs = [];

        foreach ($oauth as $key => $value) {
            $pairs[] = rawurlencode($key) . '=' . rawurlencode($value);
        }

        $base = implode('&', [
            'POST',
            rawurlencode(self::ENDPOINT),
            rawurlencode(implode('&', $pairs)),
        ]);

        $key = rawurlencode($consumerSecret) . '&' . rawurlencode($tokenSecret);

        return base64_encode(hash_hmac('sha1', $base, $key, true));
    }

    private function errorFrom($response): string
    {
        // v2 answers with either {title, detail} or {errors: [{message}]}.
        if ($detail = $response->json('detail')) {
            return $detail;
        }

        if ($message = $response->json('errors.0.message')) {
            return $message;
        }

        if ($response->status() === 403) {
            return 'X refused the post (403). The app most likely lacks write access or a paid plan.';
        }

        if ($response->status() === 429) {
            return 'X rate limit reached. The post will be retried on the next run.';
        }

        return 'X returned HTTP ' . $response->status() . '.';
    }
}
