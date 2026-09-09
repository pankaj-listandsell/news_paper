<?php

namespace Tests\Feature;

use App\Filament\Pages\ManageSocialSettings;
use App\Models\Article;
use App\Models\Category;
use App\Models\Setting;
use App\Models\SocialAccount;
use App\Models\SocialShare;
use App\Models\User;
use App\Social\SocialRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The "post now" button in the admin panel, and the runner behind it that the
 * scheduled command shares.
 */
class SocialRunNowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::set('social_practice_mode', '1');
    }

    public function test_the_runner_reports_what_it_posted(): void
    {
        $this->account('facebook');
        $this->article();
        $this->article();

        $report = (new SocialRunner())->run();

        $this->assertSame(2, $report->sentCount());
        $this->assertSame(0, $report->heldCount());
        $this->assertSame('facebook', $report->posted[0]['platform']);
    }

    public function test_a_preview_run_reports_without_posting(): void
    {
        $this->account('facebook');
        $this->article();

        $report = (new SocialRunner())->run(dryRun: true);

        $this->assertSame(1, $report->plannedCount());
        $this->assertSame(0, $report->sentCount());
        $this->assertSame(0, SocialShare::count());
    }

    public function test_the_runner_counts_what_is_waiting_per_platform(): void
    {
        $this->account('facebook');
        $this->account('x');
        $this->article();
        $this->article();

        $this->assertSame(
            ['facebook' => 2, 'x' => 2],
            (new SocialRunner())->pendingCounts()
        );
    }

    public function test_the_waiting_count_drops_as_articles_go_out(): void
    {
        $this->account('facebook');
        $this->article();
        $this->article();

        $runner = new SocialRunner();
        $runner->run(limit: 1);

        $this->assertSame(['facebook' => 1], $runner->pendingCounts());
    }

    public function test_the_button_posts_the_waiting_articles(): void
    {
        $this->account('facebook');
        $this->article();

        Livewire::actingAs($this->admin())
            ->test(ManageSocialSettings::class)
            ->callAction('runNow')
            ->assertHasNoActionErrors();

        $this->assertSame(1, SocialShare::sent()->count());
    }

    public function test_the_preview_button_posts_nothing(): void
    {
        $this->account('facebook');
        $this->article();

        Livewire::actingAs($this->admin())
            ->test(ManageSocialSettings::class)
            ->callAction('preview');

        $this->assertSame(0, SocialShare::count());
    }

    public function test_the_button_does_nothing_when_no_account_is_scheduled(): void
    {
        $this->account('facebook', ['auto_post' => false]);
        $this->article();

        Livewire::actingAs($this->admin())
            ->test(ManageSocialSettings::class)
            ->callAction('runNow');

        $this->assertSame(0, SocialShare::count());
    }

    /* ------------------------------------------------------------------ */

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
