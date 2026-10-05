<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Support\WeatherBar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The strip above the header: date, Berlin's weather, air quality and the
 * social accounts.
 */
class HeaderBarTest extends TestCase
{
    use RefreshDatabase;

    private function fakeWeather(int $temp = 16, int $code = 0, ?int $aqi = 44): void
    {
        Http::fake([
            '*/v1/forecast*' => Http::response([
                'current' => ['temperature_2m' => $temp, 'weather_code' => $code],
            ]),
            '*/v1/air-quality*' => Http::response([
                'current' => ['european_aqi' => $aqi],
            ]),
        ]);
    }

    public function test_it_reads_the_temperature_and_air_quality(): void
    {
        $this->fakeWeather(temp: 16, code: 0, aqi: 44);

        $weather = WeatherBar::current();

        $this->assertSame(16, $weather['temperature']);
        $this->assertSame('klar', $weather['label']);
        $this->assertSame(44, $weather['aqi']);
        $this->assertSame('mittel', $weather['aqi_label']);
    }

    public function test_the_strip_shows_the_weather(): void
    {
        $this->fakeWeather(temp: 16, aqi: 44);

        $this->get('/kontakt')
            ->assertOk()
            ->assertSee('Berlin')
            ->assertSee('16', false)
            ->assertSee('AQI')
            ->assertSee('44');
    }

    /**
     * The one thing that must never happen: a page that will not load because
     * someone else's weather server is down.
     */
    public function test_the_page_still_loads_when_the_weather_api_is_down(): void
    {
        Http::fake([
            '*/v1/forecast*' => Http::response([], 503),
            '*/v1/air-quality*' => Http::response([], 503),
        ]);

        $this->get('/kontakt')->assertOk()->assertDontSee('AQI');
        $this->assertNull(WeatherBar::current());
    }

    public function test_the_page_still_loads_when_the_request_throws(): void
    {
        Http::fake(fn () => throw new \RuntimeException('network down'));

        $this->get('/kontakt')->assertOk();
        $this->assertNull(WeatherBar::current());
    }

    public function test_air_quality_may_be_missing_without_losing_the_temperature(): void
    {
        Http::fake([
            '*/v1/forecast*' => Http::response([
                'current' => ['temperature_2m' => 9, 'weather_code' => 3],
            ]),
            '*/v1/air-quality*' => Http::response([], 500),
        ]);

        $weather = WeatherBar::current();

        $this->assertSame(9, $weather['temperature']);
        $this->assertSame('bedeckt', $weather['label']);
        $this->assertNull($weather['aqi']);
    }

    public function test_the_reading_is_cached_rather_than_fetched_every_time(): void
    {
        $this->fakeWeather();

        WeatherBar::current();
        WeatherBar::current();
        WeatherBar::current();

        // Two calls make up one reading: weather and air quality.
        Http::assertSentCount(2);
    }

    /**
     * Without this, an API that is down would be retried on every page view
     * and every reader would sit through the timeout.
     */
    public function test_a_failure_is_remembered_so_it_is_not_retried_every_time(): void
    {
        Http::fake(['*' => Http::response([], 503)]);

        WeatherBar::current();
        WeatherBar::current();

        // One, not two: a failed weather call returns before air quality is
        // asked for, and the second call reads the remembered failure.
        Http::assertSentCount(1);
        $this->assertFalse(Cache::get('weather.berlin'));
    }

    public function test_a_hot_day_and_a_storm_read_correctly(): void
    {
        $this->fakeWeather(temp: 31, code: 95, aqi: 95);

        $weather = WeatherBar::current();

        $this->assertSame(31, $weather['temperature']);
        $this->assertSame('Gewitter', $weather['label']);
        $this->assertSame('sehr schlecht', $weather['aqi_label']);
    }

    /* --------------------------- social links --------------------------- */

    public function test_the_strip_carries_the_social_accounts(): void
    {
        $this->fakeWeather();

        Setting::set('social_facebook', 'https://www.facebook.com/hauptstadtreportde/');
        Setting::set('social_linkedin', 'https://www.linkedin.com/company/hauptstadtreportde/');
        Setting::set('social_twitter', 'https://x.com/markusstephen77');

        $this->get('/kontakt')
            ->assertOk()
            ->assertSee('https://www.facebook.com/hauptstadtreportde/', false)
            ->assertSee('https://www.linkedin.com/company/hauptstadtreportde/', false)
            ->assertSee('https://x.com/markusstephen77', false)
            ->assertSee('LinkedIn');
    }

    public function test_the_accounts_are_shown_as_marks_not_words(): void
    {
        $this->fakeWeather();

        Setting::set('social_facebook', 'https://www.facebook.com/hauptstadtreportde/');
        Setting::set('social_linkedin', 'https://www.linkedin.com/company/hauptstadtreportde/');
        Setting::set('social_twitter', 'https://x.com/markusstephen77');

        $html = $this->get('/kontakt')->assertOk()->getContent();
        $strip = substr($html, strpos($html, 'background:#111827'));
        $strip = substr($strip, 0, strpos($strip, '<header'));

        // Three links, each carrying a drawn mark rather than the name.
        $this->assertSame(3, substr_count($strip, '<svg'));
        $this->assertStringContainsString('fill:currentColor', $strip);
    }

    /** The mark is decorative, so the name has to reach a screen reader. */
    public function test_each_mark_still_announces_which_network_it_is(): void
    {
        $this->fakeWeather();
        Setting::set('social_linkedin', 'https://www.linkedin.com/company/hauptstadtreportde/');

        $this->get('/kontakt')
            ->assertOk()
            ->assertSee('auf LinkedIn', false)
            ->assertSee('aria-hidden="true"', false);
    }

    /**
     * Adding a network to the settings without drawing its mark should leave
     * its name showing, not an empty gap.
     */
    public function test_a_network_with_no_mark_falls_back_to_its_name(): void
    {
        $html = view('partials.social-icon', ['label' => 'Mastodon', 'size' => 16])->render();

        $this->assertStringNotContainsString('<svg', $html);
        $this->assertStringContainsString('Mastodon', $html);
    }

    public function test_linkedin_is_offered_in_the_settings(): void
    {
        Setting::set('social_linkedin', 'https://www.linkedin.com/company/hauptstadtreportde/');

        $this->assertArrayHasKey('LinkedIn', \App\Support\SiteSettings::socialLinks());
    }

    public function test_an_account_that_was_never_filled_in_is_left_out(): void
    {
        $this->fakeWeather();

        $this->get('/kontakt')->assertOk()->assertDontSee('LinkedIn');
    }
}
