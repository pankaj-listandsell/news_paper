<?php

namespace App\Jobs;

use App\Models\Article;
use App\Models\NewsSource;
use App\Scraping\AiRewriterFactory;
use App\Scraping\ArticleContentExtractor;
use App\Scraping\RssScraper;
use App\Scraping\SourceScraper;
use App\Support\AiConfig;
use App\Support\ImagePlan;
use App\Support\PublishingBudget;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ScrapeSourceJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 180;
    public int $tries = 2;

    /**
     * @param  PublishingBudget|null  $budget  shared across every source in a
     *         run, so the daily cap belongs to the site rather than to a feed.
     *         Null means "no cap" — a one-off run started by hand.
     */
    public function __construct(
        public NewsSource $source,
        public ?PublishingBudget $budget = null,
        public ?ImagePlan $images = null,
    ) {
    }

    /**
     * @return array{created:int, updated:int}
     */
    public function handle(): array
    {
        $scraper = $this->resolveScraper($this->source);
        $created = 0;
        $updated = 0;

        // Capture the cutoff BEFORE fetching so we don't miss anything.
        $cutoff = $this->source->last_scraped_at;

        try {
            $raw     = $scraper->fetch($this->source);
            $items   = $scraper->parse($raw);
            $records = $scraper->normalize($items, $this->source);

            // "Only new" — keep articles published after the previous run.
            // Done BEFORE the AI/full-content steps so old posts cost nothing.
            if ($this->source->import_new_only && $cutoff) {
                $records = array_values(array_filter(
                    $records,
                    fn ($r) => isset($r['published_at']) && $r['published_at']->gt($cutoff)
                ));
            }

            // Prepare the enrichment helpers once, up front. The costly per-
            // article API calls happen INSIDE the loop below so that every
            // article is saved the moment it's ready — a mid-run stop keeps
            // (and bills for) only what actually got imported.
            $extractor = $this->source->fetch_full_content
                ? new ArticleContentExtractor()
                : null;

            [$rewriter, $language, $categories] = $this->prepareAiRewriter($this->source);

            $imageGenerator = $this->prepareAiImageGenerator();

            $detector  = new \App\Support\DuplicateDetector();
            $skipped   = 0;

            // Which author each category publishes under. Looked up once so the
            // loop below costs no extra queries.
            $categoryAuthors = \App\Models\Category::whereNotNull('user_id')
                ->pluck('user_id', 'id');

            foreach ($records as $data) {
                $isNew = ! Article::where('source_url', $data['source_url'])->exists();

                // Skip a brand-new article that duplicates a recent story from
                // another source — BEFORE spending any money enriching it.
                // Existing articles are still updated.
                if ($isNew && $detector->isDuplicate($data['title'], $data['source_url'])) {
                    $skipped++;
                    continue;
                }

                // Checked before the costly bits below, not after: an article
                // over the day's limit is never going to be published, so it
                // must not be enriched either. Dropped rather than held as a
                // draft — the scraper's cutoff moves on regardless, so this
                // one will not come back tomorrow.
                if ($isNew && $this->overDailyLimit($this->source)) {
                    $skipped++;
                    continue;
                }

                // Enrich just this one article, then persist it immediately.
                if ($extractor) {
                    $data = $this->applyFullContent($extractor, $data);
                }
                if ($rewriter) {
                    $data = $this->applyAiRewrite($rewriter, $data, $language, $categories);
                }
                $data = $this->applyImage($imageGenerator, $data);

                // The author follows the category — and the category is only
                // settled once AI categorisation has had its say, so it is
                // resolved here rather than back in the scraper. Without a
                // category author the news source's own author stands.
                if ($isNew) {
                    $data['user_id'] = $categoryAuthors[$data['category_id']] ?? $data['user_id'];
                } else {
                    // An article we already have keeps the author it went out with.
                    unset($data['user_id']);
                }

                $article = Article::updateOrCreate(
                    ['source_url' => $data['source_url']], // dedup key
                    $data
                );

                // Only a published article spends the day's budget; one held
                // as a draft by a failed rewrite is not a post.
                if ($article->wasRecentlyCreated && $article->status === 'published') {
                    $this->budget?->take();
                }

                $article->wasRecentlyCreated ? $created++ : $updated++;
            }

            $this->source->update([
                'last_scraped_at' => now(),
                'last_error'      => null,
            ]);

            if ($skipped > 0) {
                Log::info("Skipped {$skipped} duplicate article(s) from source {$this->source->id}.");
            }
        } catch (\Throwable $e) {
            $this->source->update(['last_error' => $e->getMessage()]);
            Log::warning("Scrape failed for source {$this->source->id}: {$e->getMessage()}");

            $this->alertAdmin($e->getMessage());

            throw $e;
        }

        return ['created' => $created, 'updated' => $updated];
    }

    /**
     * Has the site posted as much as it may today?
     *
     * Only applies to sources that publish on their own. A feed set to land
     * in drafts is not posting, so the cap has nothing to hold back.
     */
    private function overDailyLimit(NewsSource $source): bool
    {
        if ($this->budget === null || ! $source->auto_publish) {
            return false;
        }

        return ! $this->budget->hasRoom();
    }

    protected function resolveScraper(NewsSource $source): SourceScraper
    {
        // RSS-only for now; swap here when HTML/API scrapers are added.
        return new RssScraper();
    }

    /**
     * Email the admin when a source breaks — at most once per hour per
     * source, so a permanently broken feed can't flood the inbox.
     */
    private function alertAdmin(string $error): void
    {
        $key = "scrape-alert-sent:{$this->source->id}";

        if (\Illuminate\Support\Facades\Cache::has($key)) {
            return;
        }

        $to = \App\Support\SiteSettings::get('contact_email')
            ?: \App\Models\User::query()->oldest('id')->value('email');

        if (blank($to)) {
            return;
        }

        try {
            \Illuminate\Support\Facades\Mail::to($to)
                ->send(new \App\Mail\ScrapeFailed($this->source, $error));

            \Illuminate\Support\Facades\Cache::put($key, true, now()->addHour());
        } catch (\Throwable $e) {
            // Never let a mail problem mask the original scrape error.
            Log::warning('Could not send scrape alert: ' . $e->getMessage());
        }
    }

    /**
     * Pull the full article body from its own page (Readability) for a
     * single record. Returns the record unchanged when extraction fails.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function applyFullContent(ArticleContentExtractor $extractor, array $data): array
    {
        $extracted = $extractor->extract($data['source_url']);

        if ($extracted === null) {
            return $data; // keep RSS summary as fallback
        }

        $data['body'] = $extracted['body'];

        // Prefer the RSS image; fall back to the one Readability found.
        if (empty($data['featured_image']) && $extracted['image']) {
            $data['featured_image'] = $extracted['image'];
        }

        // Fill excerpt only if the feed didn't provide one.
        if (empty($data['excerpt']) && $extracted['excerpt']) {
            $data['excerpt'] = $extracted['excerpt'];
        }

        usleep(300_000); // 0.3s politeness delay between page fetches

        return $data;
    }

    /**
     * Build the AI rewriter once, resolving language + category list. Returns
     * [null, null, empty] when the provider isn't configured, so the caller
     * simply skips rewriting.
     *
     * @return array{0: mixed, 1: ?string, 2: \Illuminate\Support\Collection}
     */
    private function prepareAiRewriter(NewsSource $source): array
    {
        if (! $source->ai_rewrite) {
            return [null, null, collect()];
        }

        $rewriter = AiRewriterFactory::make($source->ai_provider);

        if (! $rewriter->isConfigured()) {
            Log::info("AI rewrite skipped for source {$source->id}: provider not configured.");

            return [null, null, collect()];
        }

        // When AI categorisation is on, offer the model the site's categories.
        $categories = $source->ai_category
            ? \App\Models\Category::where('is_active', true)->pluck('id', 'name')
            : collect();

        return [$rewriter, AiConfig::language(), $categories];
    }

    /**
     * Rewrite a single record's title + excerpt (and SEO fields) with AI.
     * Falls back to the original text when the call fails.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function applyAiRewrite($rewriter, array $data, ?string $language, \Illuminate\Support\Collection $categories): array
    {
        $result = $rewriter->rewrite(
            $data['title'],
            $data['body'] ?? '',
            $language,
            $categories->keys()->all()
        );

        if ($result === null) {
            // AI rewrite failed (e.g. quota/billing exhausted). We must NOT
            // let the raw scraped copy go live, so force the article to draft
            // regardless of the source's auto_publish setting. It stays in the
            // admin panel for review / manual publish once AI is working again.
            $data['status'] = 'draft';

            Log::info(
                "AI rewrite failed for source {$this->source->id}; article held as draft: {$data['source_url']}"
            );

            return $data; // keep original title/excerpt, but unpublished
        }

        // Map the model's chosen category name back to an id (case-insensitive).
        if (! empty($result['category'])) {
            $matched = $categories->first(
                fn ($id, $name) => mb_strtolower($name) === mb_strtolower($result['category'])
            );

            if ($matched !== null) {
                $data['category_id'] = $matched;
            }
        }

        $data['title']   = $result['title'];
        $data['excerpt'] = $result['excerpt'] ?: ($data['excerpt'] ?? null);

        // Replace the full body only when the model returned one.
        if (! empty($result['body'])) {
            $data['body'] = $result['body'];
        }

        // SEO fields
        $data['meta_title']       = $result['meta_title'];
        $data['meta_description'] = $result['meta_description'];

        usleep(200_000); // gentle pacing between API calls

        return $data;
    }

    /**
     * Build the AI image generator, or null when the OpenAI key is missing.
     */
    private function prepareAiImageGenerator(): ?\App\Scraping\AiImageGenerator
    {
        if (! $this->source->ai_image) {
            return null;
        }

        $generator = new \App\Scraping\AiImageGenerator();

        if (! $generator->isConfigured()) {
            Log::info('AI image skipped: OpenAI key not configured.');

            return null;
        }

        return $generator;
    }

    /**
     * Generate a fresh AI illustration for one article and store it locally,
     * replacing the hotlinked source image.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    /**
     * Settle this article's picture: the publisher's own photograph, or one
     * drawn for it.
     *
     * The two alternate through the day, so the front page carries a mix. An
     * article the feed gave no picture for takes a drawn one out of turn,
     * since the alternative is no image at all — and when the day's drawing
     * budget is spent, the photograph stands.
     *
     * Whichever wins, the bytes end up on our own disk. A photograph left as
     * a link to the publisher's server is theirs to move or delete, and a
     * months-old article would quietly lose its image.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function applyImage(?\App\Scraping\AiImageGenerator $generator, array $data): array
    {
        $ownPicture = $data['featured_image'] ?? null;
        $wantsAi    = $generator !== null
            && ($this->images?->wantsAi($this->nextPostNumber(), filled($ownPicture)) ?? true);

        if ($wantsAi) {
            $drawn = $this->applyAiImage($generator, $data);

            // Only a drawn image that actually arrived spends a slot; a failed
            // one falls through to the photograph below.
            if (($drawn['featured_image'] ?? null) !== $ownPicture) {
                $this->images?->drew();

                return $drawn;
            }
        }

        if (filled($ownPicture)) {
            $data['featured_image'] = (new \App\Scraping\SourceImageFetcher())->fetch($ownPicture)
                // Keeping the link is worse than owning the file, but better
                // than an article with no picture at all.
                ?? $ownPicture;
        }

        return $data;
    }

    /**
     * Which post of the day this one will be, counting from one.
     */
    private function nextPostNumber(): int
    {
        if ($this->budget === null) {
            return PublishingBudget::publishedToday() + 1;
        }

        return $this->budget->publishedBefore() + $this->budget->taken() + 1;
    }

    private function applyAiImage(\App\Scraping\AiImageGenerator $generator, array $data): array
    {
        $path = $generator->generate($data['title'], $this->source->category?->name);

        if ($path !== null) {
            $data['featured_image'] = $path; // local disk path
        }

        usleep(300_000);

        return $data;
    }
}
