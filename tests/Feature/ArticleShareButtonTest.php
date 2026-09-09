<?php

namespace Tests\Feature;

use App\Filament\Resources\ArticleResource;
use App\Filament\Resources\ArticleResource\Pages\EditArticle;
use App\Filament\Resources\ArticleResource\Pages\ListArticles;
use App\Models\Article;
use App\Models\Category;
use App\Models\Setting;
use App\Models\SocialAccount;
use App\Models\SocialShare;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The by-hand Share button, in both places it appears: the article list and
 * the article's own page.
 */
class ArticleShareButtonTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::set('social_practice_mode', '1');
    }

    public function test_the_button_on_the_article_page_posts_to_the_chosen_platforms(): void
    {
        $this->account('facebook');
        $this->account('x');
        $article = $this->article();

        Livewire::actingAs($this->admin())
            ->test(EditArticle::class, ['record' => $article->getRouteKey()])
            ->callAction('share', ['platforms' => ['facebook', 'x']])
            ->assertHasNoActionErrors();

        $this->assertSame(2, SocialShare::sent()->count());
    }

    /**
     * Driven through the handler rather than the Livewire action: Filament's
     * test harness will not override a checkbox list that already carries its
     * defaults, so a partial tick cannot be simulated from the outside.
     */
    public function test_only_the_ticked_platforms_are_posted_to(): void
    {
        $this->account('facebook');
        $this->account('x');
        $article = $this->article();

        ArticleResource::performShare($article, ['facebook']);

        $this->assertSame(['facebook'], SocialShare::sent()->pluck('platform')->all());
    }

    public function test_nothing_is_posted_when_no_platform_is_ticked(): void
    {
        $this->account('facebook');
        $article = $this->article();

        ArticleResource::performShare($article, []);

        $this->assertSame(0, SocialShare::count());
    }

    public function test_the_row_action_in_the_list_posts_too(): void
    {
        $this->account('facebook');
        $article = $this->article();

        Livewire::actingAs($this->admin())
            ->test(ListArticles::class)
            ->callTableAction('share', $article, ['platforms' => ['facebook']]);

        $this->assertSame(1, SocialShare::sent()->count());
    }

    public function test_the_button_is_hidden_when_nothing_is_connected(): void
    {
        $article = $this->article();

        Livewire::actingAs($this->admin())
            ->test(EditArticle::class, ['record' => $article->getRouteKey()])
            ->assertActionHidden('share');
    }

    public function test_a_platform_that_already_has_the_article_is_not_ticked_by_default(): void
    {
        $this->account('facebook');
        $this->account('x');
        $article = $this->article();

        SocialShare::create([
            'article_id' => $article->id,
            'platform'   => 'facebook',
            'status'     => SocialShare::SENT,
            'posted_at'  => now(),
        ]);

        $defaults = ArticleResource::sharablePlatforms(
            $article->fresh()->load('socialShares'),
            onlyUnsent: true,
        );

        $this->assertSame(['x'], array_keys($defaults));
    }

    public function test_posting_by_hand_still_cannot_repeat_itself(): void
    {
        $this->account('facebook');
        $article = $this->article();

        $page = Livewire::actingAs($this->admin())
            ->test(EditArticle::class, ['record' => $article->getRouteKey()]);

        $page->callAction('share', ['platforms' => ['facebook']]);
        $page->callAction('share', ['platforms' => ['facebook']]);

        $this->assertSame(1, SocialShare::count());
        $this->assertSame(1, SocialShare::first()->attempts);
    }

    /* ------------------------------------------------------------------- */

    private function article(array $attributes = []): Article
    {
        $category = Category::firstOrCreate(['slug' => 'politik'], ['name' => 'Politik']);

        return Article::create(array_merge([
            'title'        => 'Bundestag beschliesst neues Gesetz',
            'slug'         => 'artikel-' . uniqid(),
            'excerpt'      => 'Kurze Zusammenfassung.',
            'body'         => '<p>Inhalt</p>',
            'category_id'  => $category->id,
            'user_id'      => User::factory()->create()->id,
            'status'       => 'published',
            'published_at' => now()->subHour(),
        ], $attributes));
    }

    private function account(string $platform): SocialAccount
    {
        return SocialAccount::create([
            'platform'  => $platform,
            'name'      => 'Hauptstadt Report',
            'is_active' => true,
        ]);
    }

    private function admin(): User
    {
        Role::findOrCreate('admin', 'web');

        return tap(User::factory()->create())->assignRole('admin');
    }
}
