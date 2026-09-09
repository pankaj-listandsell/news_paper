<?php

namespace Tests\Feature;

use App\Jobs\ScrapeSourceJob;
use App\Models\Article;
use App\Models\Category;
use App\Models\NewsSource;
use App\Models\User;
use App\Scraping\SourceScraper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryAuthorTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_category_can_name_an_author(): void
    {
        $author   = User::factory()->create(['name' => 'Markus S.']);
        $category = Category::create([
            'name' => 'Politik', 'slug' => 'politik', 'user_id' => $author->id,
        ]);

        $this->assertSame('Markus S.', $category->author->name);
    }

    public function test_a_category_without_an_author_is_allowed(): void
    {
        $category = Category::create(['name' => 'Sport', 'slug' => 'sport']);

        $this->assertNull($category->user_id);
        $this->assertNull($category->author);
    }

    public function test_deleting_the_author_leaves_the_category_standing(): void
    {
        $author   = User::factory()->create();
        $category = Category::create([
            'name' => 'Technik', 'slug' => 'technik', 'user_id' => $author->id,
        ]);

        $author->delete();

        $this->assertNotNull($category->fresh());
        $this->assertNull($category->fresh()->user_id);
    }

    /**
     * The point of the whole feature: the scraper hands an article to the
     * author of whatever category it finally lands in.
     */
    public function test_a_new_article_is_filed_under_the_category_author(): void
    {
        $sourceAuthor   = User::factory()->create(['name' => 'Feed Author']);
        $categoryAuthor = User::factory()->create(['name' => 'Politik Author']);

        $category = Category::create([
            'name' => 'Politik', 'slug' => 'politik', 'user_id' => $categoryAuthor->id,
        ]);

        $source = NewsSource::create([
            'name' => 'Test feed', 'feed_url' => 'https://example.com/feed',
            'category_id' => $category->id, 'user_id' => $sourceAuthor->id,
        ]);

        $article = $this->importOne($source, ['category_id' => $category->id]);

        $this->assertSame($categoryAuthor->id, $article->user_id, 'category author must win over the feed author');
    }

    public function test_the_source_author_stands_when_the_category_names_nobody(): void
    {
        $sourceAuthor = User::factory()->create(['name' => 'Feed Author']);
        $category     = Category::create(['name' => 'Sport', 'slug' => 'sport']);

        $source = NewsSource::create([
            'name' => 'Test feed', 'feed_url' => 'https://example.com/feed',
            'category_id' => $category->id, 'user_id' => $sourceAuthor->id,
        ]);

        $article = $this->importOne($source, ['category_id' => $category->id]);

        $this->assertSame($sourceAuthor->id, $article->user_id);
    }

    /**
     * AI categorisation can move an article after the scraper set its author,
     * so the author has to be resolved from the category it ends up in.
     */
    public function test_the_author_follows_the_category_ai_moved_the_article_into(): void
    {
        $sourceAuthor = User::factory()->create(['name' => 'Feed Author']);
        $sportAuthor  = User::factory()->create(['name' => 'Sport Author']);

        $politik = Category::create(['name' => 'Politik', 'slug' => 'politik']);
        $sport   = Category::create(['name' => 'Sport', 'slug' => 'sport', 'user_id' => $sportAuthor->id]);

        $source = NewsSource::create([
            'name' => 'Test feed', 'feed_url' => 'https://example.com/feed',
            'category_id' => $politik->id, 'user_id' => $sourceAuthor->id,
        ]);

        // AI decided this one belongs in Sport, not Politik.
        $article = $this->importOne($source, ['category_id' => $sport->id]);

        $this->assertSame($sportAuthor->id, $article->user_id);
    }

    public function test_an_article_that_already_exists_keeps_its_author(): void
    {
        $original       = User::factory()->create(['name' => 'Original Author']);
        $categoryAuthor = User::factory()->create(['name' => 'Politik Author']);

        $category = Category::create([
            'name' => 'Politik', 'slug' => 'politik', 'user_id' => $categoryAuthor->id,
        ]);

        $source = NewsSource::create([
            'name' => 'Test feed', 'feed_url' => 'https://example.com/feed',
            'category_id' => $category->id, 'user_id' => $original->id,
        ]);

        $url = 'https://example.com/story-1';

        Article::create([
            'title' => 'Alter Titel', 'slug' => 'alter-titel', 'body' => '<p>x</p>',
            'category_id' => $category->id, 'user_id' => $original->id,
            'status' => 'published', 'published_at' => now()->subDay(),
            'source_url' => $url,
        ]);

        $article = $this->importOne($source, ['category_id' => $category->id, 'source_url' => $url]);

        $this->assertSame($original->id, $article->user_id, 'an existing article must keep its author');
    }

    /**
     * Runs the real ScrapeSourceJob over a single prepared record, with the
     * network step replaced by a stub. The category_id passed in stands for
     * whatever category the article ends up in once AI has had its say.
     */
    private function importOne(NewsSource $source, array $overrides = []): Article
    {
        $record = array_merge([
            'title' => 'Eine Nachricht', 'slug' => 'eine-nachricht-' . uniqid(),
            'body' => '<p>Inhalt</p>', 'excerpt' => 'Inhalt',
            'category_id' => $source->category_id,
            'user_id' => $source->user_id,
            'source_id' => $source->id, 'source_name' => $source->name,
            'source_url' => 'https://example.com/story-' . uniqid(),
            'status' => 'published', 'published_at' => now(),
        ], $overrides);

        $job = new class ($source, $record) extends ScrapeSourceJob {
            public function __construct(NewsSource $source, private array $record)
            {
                parent::__construct($source);
            }

            protected function resolveScraper(NewsSource $source): SourceScraper
            {
                return new class ($this->record) implements SourceScraper {
                    public function __construct(private array $record)
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
                        return [$this->record];
                    }
                };
            }
        };

        $job->handle();

        return Article::where('source_url', $record['source_url'])->firstOrFail();
    }
}
