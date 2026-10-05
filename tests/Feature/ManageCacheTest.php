<?php

namespace Tests\Feature;

use App\Filament\Pages\ManageCache;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The cache buttons in the admin panel. Deploys here go over FTP with no
 * terminal, so these are the only way to clear a cache on the server.
 */
class ManageCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_page_opens_for_an_admin(): void
    {
        $this->actingAs($this->user('admin'))
            ->get('/admin/' . ManageCache::getSlug())
            ->assertOk()
            ->assertSee('Clear page templates')
            ->assertSee('Clear everything');
    }

    /** Clearing caches is a deployment job, not an editor's. */
    public function test_an_editor_cannot_reach_it(): void
    {
        $this->actingAs($this->user('editor'))
            ->get('/admin/' . ManageCache::getSlug())
            ->assertForbidden();

        $this->assertFalse(ManageCache::canAccess());
    }

    public function test_clearing_page_templates_runs_the_right_command(): void
    {
        Artisan::spy();

        Livewire::actingAs($this->user('admin'))
            ->test(ManageCache::class)
            ->callAction('clearViews')
            ->assertHasNoActionErrors();

        Artisan::shouldHaveReceived('call')->with('view:clear')->once();
    }

    public function test_clearing_stored_data_really_empties_the_cache(): void
    {
        Cache::put('weather.berlin', ['temperature' => 16], now()->addHour());

        Livewire::actingAs($this->user('admin'))
            ->test(ManageCache::class)
            ->callAction('clearData');

        $this->assertNull(Cache::get('weather.berlin'));
    }

    public function test_clear_everything_covers_all_of_them(): void
    {
        Artisan::spy();

        Livewire::actingAs($this->user('admin'))
            ->test(ManageCache::class)
            ->callAction('clearEverything');

        foreach (['view:clear', 'cache:clear', 'config:clear', 'route:clear', 'filament:optimize-clear'] as $command) {
            Artisan::shouldHaveReceived('call')->with($command)->once();
        }
    }

    /**
     * On shared hosting one command can fail on a file permission while the
     * rest are fine. The others must still run, and the failure must be said
     * out loud rather than reported as a clean sweep.
     */
    public function test_one_command_failing_does_not_stop_the_others(): void
    {
        Artisan::shouldReceive('call')->with('view:clear')->once()
            ->andThrow(new \RuntimeException('permission denied'));
        Artisan::shouldReceive('call')->with('cache:clear')->once();
        Artisan::shouldReceive('call')->with('config:clear')->once();
        Artisan::shouldReceive('call')->with('route:clear')->once();
        Artisan::shouldReceive('call')->with('filament:optimize-clear')->once();

        Livewire::actingAs($this->user('admin'))
            ->test(ManageCache::class)
            ->callAction('clearEverything')
            ->assertHasNoActionErrors();
    }

    private function user(string $role): User
    {
        Role::findOrCreate($role, 'web');

        return tap(User::factory()->create())->assignRole($role);
    }
}
