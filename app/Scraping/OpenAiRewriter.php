<?php

namespace App\Scraping;

use App\Support\AiConfig;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Rewrites article copy using OpenAI's Chat Completions API.
 */
class OpenAiRewriter implements AiRewriter
{
    public function isConfigured(): bool
    {
        return filled(AiConfig::apiKey('openai'));
    }

    public function rewrite(string $title, string $body, string $language, array $categories = []): ?array
    {
        $apiKey = AiConfig::apiKey('openai');

        if (blank($apiKey)) {
            return null;
        }

        try {
            $response = Http::withToken($apiKey)
                ->timeout(90)
                ->retry(1, 800)
                ->post(config('ai.providers.openai.api_url'), [
                    'model'           => AiConfig::model('openai'),
                    'max_tokens'      => 4096,
                    'response_format' => ['type' => 'json_object'],
                    'messages'        => [
                        ['role' => 'system', 'content' => RewritePrompt::system($language, $categories)],
                        ['role' => 'user',   'content' => RewritePrompt::userMessage($title, $body)],
                    ],
                ]);

            if (! $response->successful()) {
                Log::warning('OpenAI rewrite failed: ' . $response->status() . ' ' . $response->body());

                return null;
            }

            \App\Models\AiUsage::record(
                'openai',
                AiConfig::model('openai'),
                'text',
                (int) $response->json('usage.prompt_tokens', 0),
                (int) $response->json('usage.completion_tokens', 0),
            );

            $text = $response->json('choices.0.message.content', '');

            return AiRewritePayload::parse($text);
        } catch (\Throwable $e) {
            Log::warning('OpenAI rewrite error: ' . $e->getMessage());

            return null;
        }
    }
}
