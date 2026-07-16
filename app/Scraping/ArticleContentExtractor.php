<?php

namespace App\Scraping;

use fivefilters\Readability\Configuration;
use fivefilters\Readability\Readability;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Downloads a full article page and extracts the main content
 * using the Readability algorithm (no per-site selectors needed).
 */
class ArticleContentExtractor
{
    /**
     * @return array{body:string, image:?string, excerpt:?string}|null
     *         null when the page can't be fetched or parsed.
     */
    public function extract(string $url): ?array
    {
        $html = $this->download($url);

        if ($html === null) {
            return null;
        }

        try {
            $config = new Configuration();
            $config->setFixRelativeURLs(true);
            $config->setOriginalURL($url);
            $config->setNormalizeEntities(true);

            $readability = new Readability($config);
            $readability->parse($html);

            $body = trim((string) $readability->getContent());

            if ($body === '') {
                return null;
            }

            return [
                'body'    => $this->clean($body),
                'image'   => $readability->getImage() ?: null,
                'excerpt' => $readability->getExcerpt()
                    ? Str::limit(trim($readability->getExcerpt()), 300)
                    : null,
            ];
        } catch (\Throwable $e) {
            Log::info("Readability failed for {$url}: {$e->getMessage()}");

            return null;
        }
    }

    private function download(string $url): ?string
    {
        try {
            $response = Http::withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (compatible; NewsPaperBot/1.0)',
                    'Accept'     => 'text/html,application/xhtml+xml',
                ])
                ->timeout(20)
                ->retry(2, 400)
                ->get($url);

            if (! $response->successful()) {
                return null;
            }

            return $response->body();
        } catch (\Throwable $e) {
            Log::info("Fetch failed for {$url}: {$e->getMessage()}");

            return null;
        }
    }

    /**
     * Readability already removes scripts; strip the wrapper div it adds
     * and any stray inline styles.
     */
    private function clean(string $html): string
    {
        // Readability wraps content in <div id="readability-page-1" ...>.
        $html = preg_replace('#<div[^>]*id=["\']readability-page-\d+["\'][^>]*>#i', '', $html) ?? $html;
        $html = preg_replace('#\s(style|class)="[^"]*"#i', '', $html) ?? $html;

        return trim($html);
    }
}
