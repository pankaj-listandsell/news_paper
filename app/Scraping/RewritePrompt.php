<?php

namespace App\Scraping;

use Illuminate\Support\Str;

/**
 * Shared prompt builder for the AI rewriters, so Claude and OpenAI
 * stay in sync. Rewrites the full article (title + excerpt + body).
 */
class RewritePrompt
{
    /** How much of the source body to send (chars). */
    public const BODY_LIMIT = 9000;

    public static function system(string $language): string
    {
        return <<<PROMPT
        You are a news editor. Rewrite the article below into ORIGINAL wording —
        do NOT copy sentences from the source — while keeping every fact, name,
        number and quote accurate. Do not add information that isn't in the source.
        Write everything in {$language}.

        Produce three things:
        - title:   a fresh, catchy headline (max 120 characters)
        - excerpt: a 2-3 sentence summary (max 300 characters)
        - body:    the FULL article rewritten, similar length and detail to the
                   source, organised into several paragraphs. Separate paragraphs
                   with a blank line. Plain text only — no markdown, no HTML.

        Respond with ONLY a JSON object, no extra text, in exactly this shape:
        {"title": "...", "excerpt": "...", "body": "..."}
        PROMPT;
    }

    public static function userMessage(string $title, string $body): string
    {
        $plain = Str::limit(trim(strip_tags($body)), self::BODY_LIMIT);

        return "TITLE:\n{$title}\n\nARTICLE:\n{$plain}";
    }
}
