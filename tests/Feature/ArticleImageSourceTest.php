<?php

namespace Tests\Feature;

use App\Jobs\ScrapeSourceJob;
use App\Models\Article;
use App\Models\Category;
use App\Models\NewsSource;
use App\Models\Setting;
use App\Models\User;
use App\Scraping\SourceImageFetcher;
use App\Scraping\SourceScraper;
use App\Support\ImagePlan;
use App\Support\PublishingBudget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Which picture an article ends up with: the publisher's own photograph or
 * one drawn for it, taking turns through the day — and either way, a copy on
 * our own disk rather than a link to someone else's server.
 */
class ArticleImageSourceTest extends TestCase
{
    use RefreshDatabase;

    private Category $category;

    private User $author;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Setting::set('scrape_frequency', 'hourly');
        Setting::set('scrape_daily_limit', '20');
        Setting::set('scrape_ai_image_limit', '10');

        $this->category = Category::create(['name' => 'Politik', 'slug' => 'politik']);
        $this->author   = User::factory()->create();

        $this->travelTo(now()->timezone('Europe/Berlin')->setTime(9, 0));
    }

    /* ------------------------- taking turns ------------------------- */

    public function test_the_first_post_keeps_its_photograph_and_the_second_is_drawn(): void
    {
        $plan = new ImagePlan();

        $this->assertFalse($plan->wantsAi(postNumber: 1, hasOwnPicture: true));
        $this->assertTrue($plan->wantsAi(postNumber: 2, hasOwnPicture: true));
        $this->assertFalse($plan->wantsAi(postNumber: 3, hasOwnPicture: true));
        $this->assertTrue($plan->wantsAi(postNumber: 4, hasOwnPicture: true));
    }

    /**
     * Twenty posts taking turns comes out ten and ten — and it has to be
     * every other one, not the first ten in a row. Counting them alone would
     * pass even with the turn order broken, because the daily limit stops at
     * ten either way.
     */
    public function test_a_full_day_alternates_rather_than_front_loading(): void
    {
        $plan  = new ImagePlan();
        $drawn = [];

        for ($post = 1; $post <= 20; $post++) {
            if ($plan->wantsAi($post, hasOwnPicture: true)) {
                $drawn[] = $post;
                $plan->drew();
            }
        }

        $this->assertSame([2, 4, 6, 8, 10, 12, 14, 16, 18, 20], $drawn);
    }

    /** No photograph to fall back on, so draw one whatever the turn says. */
    public function test_an_article_without_a_photograph_is_drawn_out_of_turn(): void
    {
        $plan = new ImagePlan();

        $this->assertTrue($plan->wantsAi(postNumber: 1, hasOwnPicture: false));
        $this->assertTrue($plan->wantsAi(postNumber: 3, hasOwnPicture: false));
    }

    public function test_once_the_days_drawings_are_spent_the_photograph_stands(): void
    {
        $plan = new ImagePlan(limit: 2);

        $plan->drew();
        $plan->drew();

        $this->assertSame(0, $plan->remaining());
        $this->assertFalse($plan->wantsAi(postNumber: 2, hasOwnPicture: true));
        // Even an article with no photograph gets none once the budget is gone.
        $this->assertFalse($plan->wantsAi(postNumber: 2, hasOwnPicture: false));
    }

    public function test_a_limit_of_zero_means_photographs_only(): void
    {
        $plan = new ImagePlan(limit: 0);

        $this->assertFalse($plan->wantsAi(postNumber: 2, hasOwnPicture: true));
    }

    public function test_drawings_made_earlier_today_count(): void
    {
        $this->articleWithImage(ImagePlan::AI_DIR . 'one.webp');
        $this->articleWithImage(ImagePlan::AI_DIR . 'two.webp');
        $this->articleWithImage(ImagePlan::SOURCE_DIR . 'photo.webp');

        $this->assertSame(2, ImagePlan::drawnToday());
        $this->assertSame(8, (new ImagePlan())->remaining());
    }

    public function test_yesterdays_drawings_do_not_count(): void
    {
        $this->articleWithImage(ImagePlan::AI_DIR . 'old.webp', createdAt: now()->subDay());

        $this->assertSame(0, ImagePlan::drawnToday());
    }

    /* ------------------------- the download ------------------------- */

    public function test_a_photograph_is_copied_onto_our_own_disk(): void
    {
        Http::fake(['*' => Http::response($this->pngBytes(), 200, ['Content-Type' => 'image/png'])]);

        $path = (new SourceImageFetcher())->fetch('https://www.morgenpost.de/bild.jpg');

        $this->assertNotNull($path);
        $this->assertStringStartsWith(ImagePlan::SOURCE_DIR, $path);
        Storage::disk('public')->assertExists($path);
    }

    /**
     * A publisher blocking hotlinks often answers with a page, not an error,
     * so the body has to be checked rather than just the status.
     */
    public function test_an_html_page_is_not_mistaken_for_a_photograph(): void
    {
        Http::fake(['*' => Http::response('<html>Zugriff verweigert</html>', 200, ['Content-Type' => 'text/html'])]);

        $this->assertNull((new SourceImageFetcher())->fetch('https://www.morgenpost.de/bild.jpg'));
    }

    public function test_a_refused_download_gives_up_quietly(): void
    {
        Http::fake(['*' => Http::response('', 403)]);

        $this->assertNull((new SourceImageFetcher())->fetch('https://www.morgenpost.de/bild.jpg'));
    }

    public function test_a_connection_failure_does_not_throw(): void
    {
        Http::fake(fn () => throw new \RuntimeException('connection reset'));

        $this->assertNull((new SourceImageFetcher())->fetch('https://www.morgenpost.de/bild.jpg'));
    }

    public function test_something_that_is_not_a_url_is_left_alone(): void
    {
        $fetcher = new SourceImageFetcher();

        $this->assertNull($fetcher->fetch(null));
        $this->assertNull($fetcher->fetch(''));
        $this->assertNull($fetcher->fetch('articles/ai/already-ours.webp'));
    }

    /* ------------------------- end to end ------------------------- */

    public function test_a_scraped_photograph_ends_up_on_our_disk_not_theirs(): void
    {
        Http::fake(['*' => Http::response($this->pngBytes(), 200, ['Content-Type' => 'image/png'])]);

        // No AI at all, so the photograph is the only candidate.
        $this->runScrape(limit: 0, items: 1);

        $image = Article::first()->featured_image;

        $this->assertStringStartsWith(ImagePlan::SOURCE_DIR, $image);
        $this->assertStringNotContainsString('morgenpost.de', $image);
        Storage::disk('public')->assertExists($image);
    }

    /** Better a link than an article with no picture at all. */
    public function test_a_photograph_that_cannot_be_copied_stays_linked(): void
    {
        Http::fake(['*' => Http::response('', 403)]);

        $this->runScrape(limit: 0, items: 1);

        $this->assertStringStartsWith('https://', Article::first()->featured_image);
    }

    /* ------------------------------------------------------------------ */

    private function pngBytes(): string
    {
        $image = imagecreatetruecolor(40, 30);
        ob_start();
        imagepng($image);
        $bytes = ob_get_clean();
        imagedestroy($image);

        return $bytes;
    }

    private function articleWithImage(string $path, $createdAt = null): Article
    {
        $article = Article::create([
            'title'          => 'Nachricht ' . uniqid(),
            'slug'           => 'nachricht-' . uniqid(),
            'body'           => '<p>Inhalt</p>',
            'featured_image' => $path,
            'category_id'    => $this->category->id,
            'user_id'        => $this->author->id,
            'status'         => 'published',
            'published_at'   => now(),
        ]);

        if ($createdAt) {
            $article->forceFill(['created_at' => $createdAt])->saveQuietly();
        }

        return $article;
    }

    private function runScrape(int $limit, int $items): void
    {
        $source = NewsSource::create([
            'name'         => 'Test feed',
            'feed_url'     => 'https://example.com/feed',
            'category_id'  => $this->category->id,
            'user_id'      => $this->author->id,
            'is_active'    => true,
            'auto_publish' => true,
            'ai_image'     => false,
        ]);

        $records = [];

        for ($i = 0; $i < $items; $i++) {
            $id = uniqid();
            $records[] = [
                'title'          => 'Nachricht ' . $id,
                'slug'           => 'nachricht-' . $id,
                'body'           => '<p>Inhalt</p>',
                'featured_image' => 'https://www.morgenpost.de/bild-' . $id . '.jpg',
                'category_id'    => $source->category_id,
                'user_id'        => $source->user_id,
                'source_id'      => $source->id,
                'source_name'    => $source->name,
                'source_url'     => 'https://example.com/story-' . $id,
                'status'         => 'published',
                'published_at'   => now(),
            ];
        }

        $job = new class($source, new PublishingBudget(), new ImagePlan($limit), $records) extends ScrapeSourceJob {
            public function __construct(NewsSource $source, PublishingBudget $budget, ImagePlan $images, private array $records)
            {
                parent::__construct($source, $budget, $images);
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
