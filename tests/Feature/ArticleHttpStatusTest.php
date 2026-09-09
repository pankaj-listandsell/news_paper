<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Category;
use App\Models\Comment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArticleHttpStatusTest extends TestCase
{
    use RefreshDatabase;

    private function article(array $attributes = []): Article
    {
        $category = Category::create([
            'name' => 'Politik',
            'slug' => 'politik-' . uniqid(),
        ]);

        $user = User::factory()->create();

        return Article::create(array_merge([
            'title'        => 'Test article',
            'slug'         => 'test-article-' . uniqid(),
            'body'         => '<p>Body</p>',
            'category_id'  => $category->id,
            'user_id'      => $user->id,
            'status'       => 'published',
            'published_at' => now()->subDay(),
        ], $attributes));
    }

    public function test_a_normal_article_is_served_with_200(): void
    {
        $article = $this->article();

        $this->get(route('article.show', $article))->assertOk();
        $this->assertSame(200, $article->fresh()->http_status);
    }

    public function test_301_redirects_to_the_configured_url(): void
    {
        $article = $this->article([
            'http_status'  => 301,
            'redirect_url' => 'https://hauptstadt-report.de/nachrichten/neuer-artikel',
        ]);

        $this->get(route('article.show', $article))
            ->assertStatus(301)
            ->assertRedirect('https://hauptstadt-report.de/nachrichten/neuer-artikel');
    }

    public function test_301_without_a_target_falls_back_to_the_normal_page(): void
    {
        $article = $this->article(['http_status' => 301]);

        $this->get(route('article.show', $article))->assertOk();
    }

    public function test_a_frozen_article_still_serves_the_full_page_to_a_reader(): void
    {
        $article = $this->article(['http_status' => 304, 'title' => 'Eingefrorener Artikel']);

        // A reader arrives with nothing cached, so sends no conditional headers.
        $this->get(route('article.show', $article))
            ->assertOk()
            ->assertSee('Eingefrorener Artikel');
    }

    public function test_a_frozen_article_answers_304_to_a_crawler(): void
    {
        $article = $this->article(['http_status' => 304]);

        $response = $this->withHeaders(['If-Modified-Since' => now()->subYear()->toRfc7231String()])
            ->get(route('article.show', $article));

        $response->assertStatus(304);
        $this->assertSame('', $response->getContent());
    }

    public function test_a_frozen_article_stays_304_even_after_an_edit(): void
    {
        $article = $this->article(['http_status' => 304]);

        $this->travel(1)->hour();
        $article->update(['title' => 'Neuer Titel']);

        // A normal article would go back to 200 here; a frozen one must not.
        $this->withHeaders(['If-Modified-Since' => now()->subYear()->toRfc7231String()])
            ->get(route('article.show', $article))
            ->assertStatus(304);
    }

    public function test_a_frozen_article_does_not_count_a_crawler_visit(): void
    {
        $article = $this->article(['http_status' => 304, 'views' => 7]);

        $this->withHeaders(['If-Modified-Since' => now()->subYear()->toRfc7231String()])
            ->get(route('article.show', $article));

        $this->assertSame(7, (int) $article->fresh()->views);
    }

    public function test_only_redirected_articles_drop_out_of_listings_and_sitemap(): void
    {
        $visible = $this->article(['title' => 'Visible one']);
        $moved   = $this->article(['title' => 'Moved one', 'http_status' => 301, 'redirect_url' => '/nachrichten/x']);
        $frozen  = $this->article(['title' => 'Frozen one', 'http_status' => 304]);

        // A frozen article is a normal page for readers, so it stays listed.
        $this->get('/')->assertOk()
            ->assertSee('Visible one')
            ->assertSee('Frozen one')
            ->assertDontSee('Moved one');

        $this->get('/sitemap.xml')->assertOk()
            ->assertSee($visible->slug)
            ->assertSee($frozen->slug)
            ->assertDontSee($moved->slug);

        $this->get('/feed')->assertOk()
            ->assertSee('Visible one')
            ->assertSee('Frozen one')
            ->assertDontSee('Moved one');
    }

    public function test_a_page_view_does_not_touch_updated_at(): void
    {
        $article = $this->article();
        $before  = $article->updated_at;

        $this->travel(2)->hours();
        $this->get(route('article.show', $article))->assertOk();

        $article->refresh();
        $this->assertSame(1, (int) $article->views, 'the view should still be counted');
        $this->assertTrue(
            $before->equalTo($article->updated_at),
            'a page view must not look like an edit'
        );
    }

    public function test_an_unchanged_article_answers_304_to_a_conditional_request(): void
    {
        $article = $this->article();

        $first = $this->get(route('article.show', $article));
        $first->assertOk();
        $this->assertNotNull($first->headers->get('Last-Modified'));
        $this->assertNotNull($first->headers->get('ETag'));

        // What a crawler sends on its next visit.
        $second = $this->withHeaders([
            'If-Modified-Since' => $first->headers->get('Last-Modified'),
            'If-None-Match'     => $first->headers->get('ETag'),
        ])->get(route('article.show', $article));

        $second->assertStatus(304);
        $this->assertSame('', $second->getContent());
    }

    public function test_304_revalidation_does_not_count_a_second_view(): void
    {
        $article = $this->article();

        $first = $this->get(route('article.show', $article));

        $this->withHeaders(['If-Modified-Since' => $first->headers->get('Last-Modified')])
            ->get(route('article.show', $article))
            ->assertStatus(304);

        $this->assertSame(1, (int) $article->fresh()->views);
    }

    public function test_an_edited_article_is_served_again_with_200(): void
    {
        $article = $this->article();
        $first   = $this->get(route('article.show', $article));

        $this->travel(1)->hour();
        $article->update(['title' => 'Edited title']);

        $this->withHeaders(['If-Modified-Since' => $first->headers->get('Last-Modified')])
            ->get(route('article.show', $article))
            ->assertOk()
            ->assertSee('Edited title');
    }

    public function test_a_new_approved_comment_makes_the_page_fresh_again(): void
    {
        $article = $this->article();
        $first   = $this->get(route('article.show', $article));

        $this->travel(1)->hour();
        Comment::create([
            'article_id' => $article->id,
            'author_name'  => 'Leser',
            'author_email' => 'leser@example.com',
            'body'       => 'Guter Artikel',
            'is_approved' => true,
        ]);

        $this->withHeaders(['If-Modified-Since' => $first->headers->get('Last-Modified')])
            ->get(route('article.show', $article))
            ->assertOk();
    }

    public function test_the_page_is_never_cached_by_shared_proxies(): void
    {
        $article = $this->article();

        $cacheControl = $this->get(route('article.show', $article))
            ->headers->get('Cache-Control');

        // The page carries a CSRF token, so it must stay out of shared caches.
        $this->assertStringContainsString('private', $cacheControl);
        $this->assertStringContainsString('must-revalidate', $cacheControl);
        $this->assertStringNotContainsString('public', $cacheControl);
    }

    public function test_a_draft_is_still_a_404_whatever_the_http_status_says(): void
    {
        $article = $this->article(['status' => 'draft', 'http_status' => 304]);


        $this->get(route('article.show', $article))->assertNotFound();
    }
}
