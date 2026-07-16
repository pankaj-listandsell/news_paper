<?php

namespace App\Scraping;

use App\Support\AiConfig;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Rewrites article copy using Anthropic's Claude (Messages API).
 */
class ClaudeRewriter implements AiRewriter
{
    public function isConfigured(): bool
    {
        return filled(AiConfig::apiKey('claude'));
    }

    public function rewrite(string $title, string $body, string $language): ?array
    {
        $apiKey = AiConfig::apiKey('claude');

        if (blank($apiKey)) {
            return null;
        }

        try {
            $response = Http::withHeaders([
                    'x-api-key'         => $apiKey,
                    'anthropic-version' => config('ai.providers.claude.api_version', '2023-06-01'),
                    'content-type'      => 'application/json',
                ])
                ->timeout(90)
                ->retry(1, 800)
                ->post(config('ai.providers.claude.api_url'), [
                    'model'      => AiConfig::model('claude'),
                    'max_tokens' => 4096,
                    'thinking'   => ['type' => 'disabled'],
                    'system'     => RewritePrompt::system($language),
                    'messages'   => [[
                        'role'    => 'user',
                        'content' => RewritePrompt::userMessage($title, $body),
                    ]],
                ]);

            if (! $response->successful()) {
                Log::warning('Claude rewrite failed: ' . $response->status() . ' ' . $response->body());

                return null;
            }

            $data = $response->json();

            if (($data['stop_reason'] ?? null) === 'refusal') {
                Log::info('Claude refused rewrite request.');

                return null;
            }

            $text = collect($data['content'] ?? [])
                ->firstWhere('type', 'text')['text'] ?? '';

            return AiRewritePayload::parse($text);
        } catch (\Throwable $e) {
            Log::warning('Claude rewrite error: ' . $e->getMessage());

            return null;
        }
    }
}
