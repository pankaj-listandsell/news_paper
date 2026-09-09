<?php

namespace Tests\Feature;

use App\Filament\Pages\ManageSocialSettings;
use App\Filament\Resources\SocialShareResource;
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
 * The admin's view of what has been posted: the failure list, retrying, and
 * the warnings that a token is about to lapse.
 */
class SocialShareAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::set('social_practice_mode', '1');
    }

    public function test_the_list_shows_what_has_been_posted_and_what_failed(): void
    {
        $this->account('facebook');
        $sent   = $this->share($this->article(['title' => 'Ging raus']), SocialShare::SENT);
        $failed = $this->share($this->article(['title' => 'Ging schief']), SocialShare::FAILED, 'Rate limit reached');

        Livewire::actingAs($this->admin())
            ->test(SocialShareResource\Pages\ListSocialShares::class)
            ->assertCanSeeTableRecords([$sent, $failed])
            ->assertSee('Rate limit reached');
    }

    public function test_the_navigation_badge_counts_only_failures(): void
    {
        $this->account('facebook');
        $this->share($this->article(), SocialShare::SENT);
        $this->share($this->article(), SocialShare::PENDING);

        $this->assertNull(SocialShareResource::getNavigationBadge());

        $this->share($this->article(), SocialShare::FAILED, 'nope');

        $this->assertSame('1', SocialShareResource::getNavigationBadge());
    }

    public function test_retrying_a_failure_clears_the_attempt_count_and_posts(): void
    {
        $this->account('facebook');
        $share = $this->share($this->article(), SocialShare::FAILED, 'Rate limit reached');
        $share->update(['attempts' => 3]);

        Livewire::actingAs($this->admin())
            ->test(SocialShareResource\Pages\ListSocialShares::class)
            ->callTableAction('retry', $share);

        $share->refresh();

        $this->assertSame(SocialShare::SENT, $share->status);
        $this->assertNull($share->error);
    }

    public function test_something_already_posted_cannot_be_retried(): void
    {
        $this->account('facebook');
        $share = $this->share($this->article(), SocialShare::SENT);

        Livewire::actingAs($this->admin())
            ->test(SocialShareResource\Pages\ListSocialShares::class)
            ->assertTableActionHidden('retry', $share);
    }

    public function test_marking_never_post_keeps_it_out_of_future_runs(): void
    {
        $this->account('facebook');
        $article = $this->article();
        $share   = $this->share($article, SocialShare::FAILED, 'nope');

        Livewire::actingAs($this->admin())
            ->test(SocialShareResource\Pages\ListSocialShares::class)
            ->callTableAction('skip', $share);

        $this->assertSame(SocialShare::SKIPPED, $share->fresh()->status);

        // A run must now leave it alone rather than trying again.
        $this->artisan('social:publish')->assertSuccessful();

        $this->assertSame(SocialShare::SKIPPED, $share->fresh()->status);
        $this->assertSame(0, $share->fresh()->attempts);
    }

    /* --------------------------- token expiry --------------------------- */

    public function test_an_expired_token_is_counted_on_the_settings_nav(): void
    {
        $this->account('facebook', ['token_expires_at' => now()->subDay()]);

        $this->assertSame('1', ManageSocialSettings::getNavigationBadge());
    }

    public function test_a_token_running_out_soon_is_counted_too(): void
    {
        $this->account('facebook', ['token_expires_at' => now()->addDays(5)]);

        $this->assertSame('1', ManageSocialSettings::getNavigationBadge());
    }

    public function test_a_healthy_token_raises_no_alarm(): void
    {
        $this->account('facebook', ['token_expires_at' => now()->addMonths(2)]);

        $this->assertNull(ManageSocialSettings::getNavigationBadge());
    }

    public function test_an_expired_token_stops_posting(): void
    {
        $this->account('facebook', ['token_expires_at' => now()->subDay()]);
        $this->article();

        $this->artisan('social:publish')->assertSuccessful();

        $this->assertSame(0, SocialShare::sent()->count());
        $this->assertStringContainsString('expired', SocialShare::first()->error);
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

    private function share(Article $article, string $status, ?string $error = null): SocialShare
    {
        return SocialShare::create([
            'article_id' => $article->id,
            'platform'   => 'facebook',
            'status'     => $status,
            'error'      => $error,
            'posted_at'  => $status === SocialShare::SENT ? now() : null,
        ]);
    }

    private function account(string $platform, array $attributes = []): SocialAccount
    {
        return SocialAccount::create(array_merge([
            'platform'  => $platform,
            'name'      => 'Hauptstadt Report',
            'is_active' => true,
            'auto_post' => true,
        ], $attributes));
    }

    private function admin(): User
    {
        Role::findOrCreate('admin', 'web');

        return tap(User::factory()->create())->assignRole('admin');
    }
}
