<?php

namespace App\Scraping;

use App\Support\AiConfig;

class AiRewriterFactory
{
    /**
     * Resolve a rewriter for the given provider, or the configured default.
     */
    public static function make(?string $provider = null): AiRewriter
    {
        $provider = $provider ?: AiConfig::provider();

        return match ($provider) {
            'openai' => new OpenAiRewriter(),
            default  => new ClaudeRewriter(),
        };
    }
}
