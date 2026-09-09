<?php

namespace Tests\Feature;

use App\Filament\Pages\ManageSocialSettings;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SocialSettingsPageTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The page reads every platform through firstOrNew, so it meets accounts
     * that have never been saved. That path is what broke in the browser
     * while every other test passed, because the tests always stored
     * credentials first.
     */
    public function test_an_account_that_was_never_saved_has_no_credentials(): void
    {
        $account = SocialAccount::firstOrNew(['platform' => 'facebook']);

        $this->assertSame([], $account->credentialBag());
        $this->assertNull($account->credential('access_token'));
        $this->assertFalse($account->isUsable());
    }

    public function test_the_settings_page_opens_with_nothing_connected(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/' . ManageSocialSettings::getSlug())
            ->assertOk()
            ->assertSee('Practice mode')
            ->assertSee('Facebook Page')
            ->assertSee('LinkedIn Page');
    }

    public function test_the_settings_page_opens_with_accounts_connected(): void
    {
        $account = new SocialAccount([
            'platform' => 'facebook', 'name' => 'Hauptstadt Report', 'is_active' => true,
        ]);
        $account->setCredentials(['page_id' => '123', 'access_token' => 'secret-token']);
        $account->save();

        $response = $this->actingAs($this->admin())
            ->get('/admin/' . ManageSocialSettings::getSlug())
            ->assertOk();

        // A stored secret must never be rendered back into the page.
        $response->assertDontSee('secret-token');
    }

    public function test_a_reader_cannot_open_the_settings_page(): void
    {
        $this->get('/admin/' . ManageSocialSettings::getSlug())
            ->assertRedirect();
    }

    private function admin(): User
    {
        Role::findOrCreate('admin', 'web');

        return tap(User::factory()->create())->assignRole('admin');
    }
}
