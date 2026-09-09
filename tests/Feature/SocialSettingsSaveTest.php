<?php

namespace Tests\Feature;

use App\Filament\Pages\ManageSocialSettings;
use App\Models\Setting;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Saving the Social Posting page. Until an account is saved here nothing else
 * in the feature is reachable — the Share button hides itself and the
 * scheduled run has nowhere to post — so this is the path that has to work.
 */
class SocialSettingsSaveTest extends TestCase
{
    use RefreshDatabase;

    public function test_connecting_a_platform_creates_the_account(): void
    {
        Livewire::actingAs($this->admin())
            ->test(ManageSocialSettings::class)
            ->fillForm([
                'facebook_name'         => 'Hauptstadt Report',
                'facebook_is_active'    => true,
                'facebook_auto_post'    => true,
                'facebook_page_id'      => '99887766',
                'facebook_access_token' => 'a-real-looking-token',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $account = SocialAccount::where('platform', 'facebook')->first();

        $this->assertNotNull($account, 'saving the page must create the account');
        $this->assertTrue($account->is_active);
        $this->assertTrue($account->auto_post);
        $this->assertSame('99887766', $account->credential('page_id'));
        $this->assertSame('a-real-looking-token', $account->credential('access_token'));
    }

    public function test_a_saved_token_survives_a_save_that_did_not_retype_it(): void
    {
        $account = new SocialAccount(['platform' => 'facebook', 'is_active' => true]);
        $account->setCredentials(['page_id' => '1', 'access_token' => 'keep-me']);
        $account->save();

        // Reopening the page shows a placeholder, not the token; saving from
        // there must not wipe what is stored.
        Livewire::actingAs($this->admin())
            ->test(ManageSocialSettings::class)
            ->fillForm(['facebook_auto_post' => true])
            ->call('save')
            ->assertHasNoFormErrors();

        $account->refresh();

        $this->assertSame('keep-me', $account->credential('access_token'));
        $this->assertTrue($account->auto_post);
    }

    public function test_the_general_settings_are_saved(): void
    {
        Livewire::actingAs($this->admin())
            ->test(ManageSocialSettings::class)
            ->fillForm([
                'social_practice_mode'  => false,
                'social_batch_size'     => 7,
                'social_frequency'      => 'hourly',
                'social_backlog_cutoff' => '2026-09-01',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('0', Setting::get('social_practice_mode'));
        $this->assertSame('7', Setting::get('social_batch_size'));
        $this->assertSame('hourly', Setting::get('social_frequency'));
        $this->assertStringContainsString('2026-09-01', Setting::get('social_backlog_cutoff'));
    }

    public function test_the_share_button_appears_once_a_platform_is_connected(): void
    {
        $this->assertFalse(\App\Filament\Resources\ArticleResource::hasConnectedAccounts());

        Livewire::actingAs($this->admin())
            ->test(ManageSocialSettings::class)
            ->fillForm([
                'facebook_is_active'    => true,
                'facebook_page_id'      => '1',
                'facebook_access_token' => 'tok',
            ])
            ->call('save');

        $this->assertTrue(\App\Filament\Resources\ArticleResource::hasConnectedAccounts());
    }

    private function admin(): User
    {
        Role::findOrCreate('admin', 'web');

        return tap(User::factory()->create())->assignRole('admin');
    }
}
