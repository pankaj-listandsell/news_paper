<?php

namespace App\Jobs;

use App\Models\Article;
use App\Models\NewsSource;
use App\Scraping\AiRewriterFactory;
use App\Scraping\ArticleContentExtractor;
use App\Scraping\RssScraper;
use App\Scraping\SourceScraper;
use App\Support\AiConfig;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ScrapeSourceJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 180;
    public int $tries = 2;

    public function __construct(public NewsSource $source)
    {
    }

    /**
     * @return array{created:int, updated:int}
     */
    public function handle(): array
    {
        $scraper = $this->resolveScraper($this->source);
        $created = 0;
        $updated = 0;

        try {
            $raw     = $scraper->fetch($this->source);
            $items   = $scraper->parse($raw);
            $records = $scraper->normalize($items, $this->source);

            if ($this->source->fetch_full_content) {
                $records = $this->enrichWithFullContent($records);
            }

            if ($this->source->ai_rewrite) {
                $records = $this->enrichWithAi($records, $this->source);
            }

            if ($this->source->ai_image) {
                $records = $this->enrichWithAiImage($records);
            }

            foreach ($records as $data) {
                $article = Article::updateOrCreate(
                    ['source_url' => $data['source_url']], // dedup key
                    $data
                );

                $article->wasRecentlyCreated ? $created++ : $updated++;
            }

            $this->source->update([
                'last_scraped_at' => now(),
                'last_error'      => null,
            ]);
        } catch (\Throwable $e) {
            $this->source->update(['last_error' => $e->getMessage()]);
            Log::warning("Scrape failed for source {$this->source->id}: {$e->getMessage()}");

            throw $e;
        }

        return ['created' => $created, 'updated' => $updated];
    }

    private function resolveScraper(NewsSource $source): SourceScraper
    {
        // RSS-only for now; swap here when HTML/API scrapers are added.
        return new RssScraper();
    }

    /**
     * Replace the RSS summary with the full article body pulled from
     * each article's own page (Readability extraction).
     *
     * @param  array<int, array<string, mixed>>  $records
     * @return array<int, array<string, mixed>>
     */
    private function enrichWithFullContent(array $records): array
    {
        $extractor = new ArticleContentExtractor();

        foreach ($records as $i => $data) {
            $extracted = $extractor->extract($data['source_url']);

            if ($extracted === null) {
                continue; // keep RSS summary as fallback
            }

            $records[$i]['body'] = $extracted['body'];

            // Prefer the RSS image; fall back to the one Readability found.
            if (empty($data['featured_image']) && $extracted['image']) {
                $records[$i]['featured_image'] = $extracted['image'];
            }

            // Fill excerpt only if the feed didn't provide one.
            if (empty($data['excerpt']) && $extracted['excerpt']) {
                $records[$i]['excerpt'] = $extracted['excerpt'];
            }

            usleep(300_000); // 0.3s politeness delay between page fetches
        }

        return $records;
    }

    /**
     * Rewrite each record's title + excerpt with AI (Claude or OpenAI).
     * Falls back to the original text when the provider isn't configured
     * or a call fails.
     *
     * @param  array<int, array<string, mixed>>  $records
     * @return array<int, array<string, mixed>>
     */
    private function enrichWithAi(array $records, NewsSource $source): array
    {
        $rewriter = AiRewriterFactory::make($source->ai_provider);

        if (! $rewriter->isConfigured()) {
            Log::info("AI rewrite skipped for source {$source->id}: provider not configured.");

            return $records;
        }

        $language = AiConfig::language();

        foreach ($records as $i => $data) {
            $result = $rewriter->rewrite($data['title'], $data['body'] ?? '', $language);

            if ($result === null) {
                continue; // keep original title/excerpt
            }

            $records[$i]['title']   = $result['title'];
            $records[$i]['excerpt'] = $result['excerpt'] ?: ($data['excerpt'] ?? null);

            // Replace the full body only when the model returned one.
            if (! empty($result['body'])) {
                $records[$i]['body'] = $result['body'];
            }

            usleep(200_000); // gentle pacing between API calls
        }

        return $records;
    }

    /**
     * Generate a fresh AI illustration per article and store it locally,
     * replacing the hotlinked source image.
     *
     * @param  array<int, array<string, mixed>>  $records
     * @return array<int, array<string, mixed>>
     */
    private function enrichWithAiImage(array $records): array
    {
        $generator = new \App\Scraping\AiImageGenerator();

        if (! $generator->isConfigured()) {
            Log::info('AI image skipped: OpenAI key not configured.');

            return $records;
        }

        $context = $this->source->category?->name;

        foreach ($records as $i => $data) {
            $path = $generator->generate($data['title'], $context);

            if ($path !== null) {
                $records[$i]['featured_image'] = $path; // local disk path
            }

            usleep(300_000);
        }

        return $records;
    }
}
