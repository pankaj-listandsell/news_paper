<?php

namespace App\Scraping;

use Illuminate\Support\Str;

/**
 * Shared prompt builder for the AI rewriters, so Claude and OpenAI
 * stay in sync. Rewrites the full article (title + excerpt + body + SEO)
 * and optionally classifies it into one of the site's categories.
 */
class RewritePrompt
{
    /** How much of the source body to send (chars). */
    public const BODY_LIMIT = 9000;

    /**
     * @param  list<string>  $categories  when non-empty, the model must pick one of these
     */
    public static function system(string $language, array $categories = []): string
    {
        $prompt = <<<PROMPT
        You are a news editor. Rewrite the article below into ORIGINAL wording —
        do NOT copy sentences from the source — while keeping every fact, name,
        number and quote accurate. Do not add information that isn't in the source.
        Write everything in {$language}.

        Produce these fields:
        - title:            a fresh, catchy headline (max 120 characters)
        - excerpt:          a 2-3 sentence summary (max 300 characters)
        - body:             the FULL article rewritten, similar length and detail to
                            the source, organised into several paragraphs. Separate
                            paragraphs with a blank line. Plain text only — no
                            markdown, no HTML.
        - meta_title:       an SEO page title, max 60 characters, front-loaded with
                            the main keyword. Concise — it must not be cut off in
                            search results.
        - meta_description: an SEO meta description, 140-155 characters, that
                            summarises the story and invites the click. One or two
                            plain sentences, no quotes, no clickbait.
        PROMPT;

        $shape = '{"title": "...", "excerpt": "...", "body": "...", "meta_title": "...", "meta_description": "..."';

        if ($categories !== []) {
            $list = implode(', ', $categories);

            $prompt .= "\n" . <<<PROMPT
        - category:         classify the article. Choose EXACTLY ONE name from this
                            list, copied verbatim: {$list}.
                            Pick the single best fit based on what the article is
                            actually about. If none of them fit, use an empty string.
        PROMPT;

            $shape .= ', "category": "..."';
        }

        $shape .= '}';

        return $prompt . "\n\n"
            . "Respond with ONLY a JSON object, no extra text, in exactly this shape:\n"
            . $shape;
    }

    public static function userMessage(string $title, string $body): string
    {
        $plain = Str::limit(trim(strip_tags($body)), self::BODY_LIMIT);

        return "TITLE:\n{$title}\n\nARTICLE:\n{$plain}";
    }
}
