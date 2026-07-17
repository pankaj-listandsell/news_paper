<?php

namespace App\Scraping;

use Illuminate\Support\Str;

/**
 * Extracts {title, excerpt, body} from a model's text response, tolerating
 * stray prose or markdown fences around the JSON.
 */
class AiRewritePayload
{
    /**
     * @return array{title:string, excerpt:string, body:string, meta_title:string, meta_description:string, category:string}|null
     */
    public static function parse(string $text): ?array
    {
        $text = trim($text);

        if ($text === '') {
            return null;
        }

        // Grab the first {...} block if the model wrapped it in prose/markdown.
        if (preg_match('/\{.*\}/s', $text, $m)) {
            $text = $m[0];
        }

        $data = json_decode($text, true);

        if (! is_array($data)) {
            return null;
        }

        $title   = trim((string) ($data['title'] ?? ''));
        $excerpt = trim((string) ($data['excerpt'] ?? ''));
        $body    = trim((string) ($data['body'] ?? ''));
        $metaT   = trim((string) ($data['meta_title'] ?? ''));
        $metaD   = trim((string) ($data['meta_description'] ?? ''));
        $cat     = trim((string) ($data['category'] ?? ''));

        if ($title === '') {
            return null;
        }

        return [
            'title'   => Str::limit($title, 250, ''),
            'excerpt' => Str::limit($excerpt, 500, ''),
            'body'    => self::toHtml($body),
            // SEO fields — fall back to the headline/summary when the model omits them.
            'meta_title'       => Str::limit($metaT !== '' ? $metaT : $title, 60, ''),
            'meta_description' => Str::limit($metaD !== '' ? $metaD : $excerpt, 160, ''),
            // Chosen category name; empty when not requested or nothing fit.
            'category'         => $cat,
        ];
    }

    /**
     * Turn plain-text paragraphs (blank-line separated) into <p> HTML.
     */
    private static function toHtml(string $body): string
    {
        if ($body === '') {
            return '';
        }

        $paragraphs = preg_split('/\n\s*\n/', $body) ?: [$body];

        return collect($paragraphs)
            ->map(fn ($p) => trim($p))
            ->filter()
            ->map(fn ($p) => '<p>' . nl2br(e($p)) . '</p>')
            ->implode("\n");
    }
}
