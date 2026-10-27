<?php

namespace Tests\Feature;

use App\Jobs\ScrapeSourceJob;
use App\Models\Article;
use App\Models\Category;
use App\Models\NewsSource;
use App\Models\Setting;
use App\Models\User;
use App\Scraping\SourceScraper;
use App\Support\PublishingBudget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The daily posting cap: at most so many scraped posts a day, spread across
 * the day's runs, and anything over the top is dropped rather than queued for
 * tomorrow.
 */
class DailyPostLimitTest extends TestCase
{
    use RefreshDatabase;

    private Category $category;

    private User $author;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::set('scrape_frequency', 'hourly');
        $this->category = Category::create(['name' => 'Politik', 'slug' => 'politik']);
        $this->author   = User::factory()->create();

        // Mid-morning: inside the posting window, with the day ahead.
        $this->travelTo(now()->timezone('Europe/Berlin')->setTime(9, 0));
    }

    /* ---------------------------- the budget ---------------------------- */

    public function test_it_spreads_the_day_over_the_runs_still_to_come(): void
    {
        Setting::set('scrape_daily_limit', '20');

        // 09:00 with hourly runs leaves 15 of them (09 through 23).
        $this->assertSame(15, PublishingBudget::runsLeftToday());

        // 20 posts over 15 runs is two for this one.
        $this->assertSame(2, (new PublishingBudget())->allowance());
    }

    public function test_a_late_start_catches_up_rather_than_wasting_the_day(): void
    {
        Setting::set('scrape_daily_limit', '20');
        $this->travelTo(now()->timezone('Europe/Berlin')->setTime(22, 0));

        // Two runs left for the whole day's twenty.
        $this->assertSame(10, (new PublishingBudget())->allowance());
    }

    public function test_nothing_is_posted_outside_the_window(): void
    {
        Setting::set('scrape_daily_limit', '20');

        foreach ([3, 5, 0] as $hour) {
            $this->travelTo(now()->timezone('Europe/Berlin')->setTime($hour, 0));

            $this->assertFalse(PublishingBudget::isWithinWindow());
            $this->assertSame(0, (new PublishingBudget())->allowance());
        }
    }

    public function test_the_window_covers_six_in_the_morning_to_eleven_at_night(): void
    {
        foreach ([6, 12, 23] as $hour) {
            $this->travelTo(now()->timezone('Europe/Berlin')->setTime($hour, 30));
            $this->assertTrue(PublishingBudget::isWithinWindow(), "{$hour}:30 should be inside");
        }

        foreach ([5, 0] as $hour) {
            $this->travelTo(now()->timezone('Europe/Berlin')->setTime($hour, 30));
            $this->assertFalse(PublishingBudget::isWithinWindow(), "{$hour}:30 should be outside");
        }
    }

    public function test_a_used_up_day_leaves_nothing_for_this_run(): void
    {
        Setting::set('scrape_daily_limit', '5');
        $this->publishScraped(5);

        $this->assertSame(0, (new PublishingBudget())->allowance());
    }

    /** The cap is there to hold back the scraper, not the newsroom. */
    public function test_articles_written_by_hand_do_not_count(): void
    {
        Setting::set('scrape_daily_limit', '5');

        $this->publishScraped(2);
        $this->article(['status' => 'published', 'source_id' => null]);
        $this->article(['status' => 'published', 'source_id' => null]);

        $this->assertSame(2, PublishingBudget::publishedToday());
    }

    /** An article held back by a failed rewrite was never posted. */
    public function test_drafts_do_not_count(): void
    {
        Setting::set('scrape_daily_limit', '5');

        $this->publishScraped(2);
        $this->article(['status' => 'draft', 'source_id' => 1]);

        $this->assertSame(2, PublishingBudget::publishedToday());
    }

    public function test_yesterdays_posts_do_not_count(): void
    {
        Setting::set('scrape_daily_limit', '5');

        $this->publishScraped(3, createdAt: now()->subDay());

        $this->assertSame(0, PublishingBudget::publishedToday());
    }

    /* ---------------------------- the scraper ---------------------------- */

    public function test_the_scraper_stops_at_this_runs_share(): void
    {
        Setting::set('scrape_daily_limit', '20');

        // 09:00, hourly: two for this run. The feed offers six.
        $source = $this->source();
        $budget = new PublishingBudget();

        $this->runScrape($source, $budget, 6);

        $this->assertSame(2, Article::where('status', 'published')->count());
        $this->assertSame(2, $budget->taken());
    }

    /**
     * The point of the cap: the ones that will not be posted are dropped
     * before the AI is asked for anything, so they cost nothing.
     */
    public function test_the_extras_are_dropped_not_held_as_drafts(): void
    {
        Setting::set('scrape_daily_limit', '20');

        $this->runScrape($this->source(), new PublishingBudget(), 6);

        $this->assertSame(0, Article::where('status', 'draft')->count());
        $this->assertSame(2, Article::count(), 'the four over the share should not exist at all');
    }

    /** Tomorrow brings tomorrow's news, not what was skipped today. */
    public function test_a_skipped_item_does_not_come_back(): void
    {
        Setting::set('scrape_daily_limit', '20');
        $source = $this->source();

        $this->runScrape($source, new PublishingBudget(), 6);
        $this->assertSame(2, Article::count());

        // The cutoff has moved on, so a later run is not offered them again.
        $this->assertNotNull($source->fresh()->last_scraped_at);
    }

    public function test_one_limit_is_shared_by_every_source(): void
    {
        Setting::set('scrape_daily_limit', '20');

        $budget = new PublishingBudget();
        $this->assertSame(2, $budget->allowance());

        $this->runScrape($this->source('Feed A'), $budget, 5);
        $this->runScrape($this->source('Feed B'), $budget, 5);

        // Two for the run in total, not two each.
        $this->assertSame(2, Article::where('status', 'published')->count());
    }

    /** A feed that lands in drafts is not posting, so the cap has no say. */
    public function test_a_draft_only_source_is_not_capped(): void
    {
        Setting::set('scrape_daily_limit', '20');
        $source = $this->source(autoPublish: false);

        $this->runScrape($source, new PublishingBudget(), 5);

        $this->assertSame(5, Article::where('status', 'draft')->count());
    }

    /* ------------------------------------------------------------------ */

    private function publishScraped(int $count, $createdAt = null): void
    {
        for ($i = 0; $i < $count; $i++) {
            $this->article([
                'status'     => 'published',
                'source_id'  => 1,
                'created_at' => $createdAt ?? now(),
            ]);
        }
    }

    private function article(array $attributes = []): Article
    {
        $createdAt = $attributes['created_at'] ?? null;
        unset($attributes['created_at']);

        $article = Article::create(array_merge([
            'title'        => 'Nachricht ' . uniqid(),
            'slug'         => 'nachricht-' . uniqid(),
            'body'         => '<p>Inhalt</p>',
            'category_id'  => $this->category->id,
            'user_id'      => $this->author->id,
            'published_at' => now(),
        ], $attributes));

        // created_at is not fillable, so it has to be forced.
        if ($createdAt) {
            $article->forceFill(['created_at' => $createdAt])->saveQuietly();
        }

        return $article;
    }

    private function source(string $name = 'Test feed', bool $autoPublish = true): NewsSource
    {
        return NewsSource::create([
            'name'         => $name,
            'feed_url'     => 'https://example.com/' . uniqid(),
            'category_id'  => $this->category->id,
            'user_id'      => $this->author->id,
            'is_active'    => true,
            'auto_publish' => $autoPublish,
        ]);
    }

    /**
     * Run the real job over a feed offering the given number of items.
     */
    private function runScrape(NewsSource $source, ?PublishingBudget $budget, int $items): void
    {
        $records = [];

        for ($i = 0; $i < $items; $i++) {
            $id = uniqid();
            $records[] = [
                'title'        => 'Nachricht ' . $id,
                'slug'         => 'nachricht-' . $id,
                'body'         => '<p>Inhalt</p>',
                'excerpt'      => 'Inhalt',
                'category_id'  => $source->category_id,
                'user_id'      => $source->user_id,
                'source_id'    => $source->id,
                'source_name'  => $source->name,
                'source_url'   => 'https://example.com/story-' . $id,
                'status'       => $source->auto_publish ? 'published' : 'draft',
                'published_at' => now(),
            ];
        }

        $job = new class($source, $budget, $records) extends ScrapeSourceJob {
            public function __construct(NewsSource $source, ?PublishingBudget $budget, private array $records)
            {
                parent::__construct($source, $budget);
            }

            protected function resolveScraper(NewsSource $source): SourceScraper
            {
                return new class($this->records) implements SourceScraper {
                    public function __construct(private array $records)
                    {
                    }

                    public function fetch(NewsSource $source): string
                    {
                        return '';
                    }

                    public function parse(string $raw): array
                    {
                        return [[]];
                    }

                    public function normalize(array $items, NewsSource $source): array
                    {
                        return $this->records;
                    }
                };
            }
        };

        $job->handle();
    }
}
