<?php

namespace Tests\Feature;

use App\Support\MetalPrices;
use App\Support\PostalCodes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The reader tools: age calculator, postal code search and the gold price.
 */
class ToolsTest extends TestCase
{
    use RefreshDatabase;

    private function fakeWeather(): void
    {
        // Every page renders the header strip, which asks for the weather.
        Http::fake([
            '*/v1/forecast*'    => Http::response(['current' => ['temperature_2m' => 16, 'weather_code' => 0]]),
            '*/v1/air-quality*' => Http::response(['current' => ['european_aqi' => 44]]),
        ]);
    }

    /* ------------------------------ age ------------------------------ */

    public function test_the_age_calculator_opens(): void
    {
        $this->fakeWeather();

        $this->get('/altersrechner')
            ->assertOk()
            ->assertSee('Altersrechner')
            ->assertSee('Geburtsdatum');
    }

    /** It is worked out in the browser, so the page must carry the script. */
    public function test_the_age_calculator_ships_its_script(): void
    {
        $this->fakeWeather();

        $this->get('/altersrechner')
            ->assertOk()
            ->assertSee('birthdate', false)
            ->assertSee('age-result', false);
    }

    public function test_a_future_birthdate_cannot_be_picked(): void
    {
        $this->fakeWeather();

        $this->get('/altersrechner')
            ->assertOk()
            ->assertSee('max="' . now()->timezone('Europe/Berlin')->format('Y-m-d') . '"', false);
    }

    /* --------------------------- postal codes --------------------------- */

    private function fakePostalApi(array $rows): void
    {
        $this->fakeWeather();
        Http::fake(['openplzapi.org/*' => Http::response($rows)]);
    }

    public function test_the_postal_search_opens_empty(): void
    {
        $this->fakeWeather();

        $this->get('/plz-suche')
            ->assertOk()
            ->assertSee('PLZ-Suche')
            ->assertDontSee('Treffer');
    }

    public function test_a_postal_code_finds_its_place(): void
    {
        $this->fakePostalApi([[
            'postalCode'   => '10115',
            'name'         => 'Berlin',
            'municipality' => ['name' => 'Berlin, Stadt'],
            'federalState' => ['name' => 'Berlin'],
        ]]);

        $this->get('/plz-suche?q=10115')
            ->assertOk()
            ->assertSee('10115')
            ->assertSee('Berlin')
            ->assertSee('1 Treffer');
    }

    public function test_a_place_name_finds_its_codes(): void
    {
        $this->fakePostalApi([
            ['postalCode' => '14467', 'name' => 'Potsdam', 'federalState' => ['name' => 'Brandenburg']],
            ['postalCode' => '14469', 'name' => 'Potsdam', 'federalState' => ['name' => 'Brandenburg']],
        ]);

        $this->get('/plz-suche?q=Potsdam')
            ->assertOk()
            ->assertSee('14467')
            ->assertSee('14469')
            ->assertSee('2 Treffer');

        // A five-digit query is a code; anything else is a name.
        Http::assertSent(fn ($request) => str_contains($request->url(), 'name=Potsdam'));
    }

    public function test_nothing_found_says_so_rather_than_showing_an_empty_table(): void
    {
        $this->fakePostalApi([]);

        $this->get('/plz-suche?q=99999')
            ->assertOk()
            ->assertSee('wurde nichts gefunden')
            // The words also appear in the page copy, so look for the table.
            ->assertDontSee('<table', false);
    }

    /** A lookup that failed is not the same as a place that does not exist. */
    public function test_a_broken_lookup_is_told_apart_from_no_results(): void
    {
        $this->fakeWeather();
        Http::fake(['openplzapi.org/*' => Http::response([], 503)]);

        $this->get('/plz-suche?q=10115')
            ->assertOk()
            ->assertSee('nicht erreichbar')
            ->assertDontSee('wurde nichts gefunden');
    }

    public function test_a_postal_answer_is_cached(): void
    {
        $this->fakePostalApi([[
            'postalCode' => '10115', 'name' => 'Berlin', 'federalState' => ['name' => 'Berlin'],
        ]]);

        PostalCodes::search('10115');
        PostalCodes::search('10115');

        Http::assertSentCount(1);
    }

    public function test_a_five_digit_query_is_treated_as_a_code(): void
    {
        $this->assertTrue(PostalCodes::looksLikeCode('10115'));
        $this->assertFalse(PostalCodes::looksLikeCode('Berlin'));
        $this->assertFalse(PostalCodes::looksLikeCode('1011'));
    }

    /* ------------------------------ gold ------------------------------ */

    private function fakeMetals(float $gold = 4161.5, float $silver = 61.96, float $usdPerEur = 1.1225): void
    {
        $this->fakeWeather();

        Http::fake([
            '*gold-api.com/price/XAU' => Http::response(['price' => $gold]),
            '*gold-api.com/price/XAG' => Http::response(['price' => $silver]),
            '*eurofxref-daily.xml'    => Http::response(
                "<Cube><Cube currency='USD' rate='{$usdPerEur}'/></Cube>"
            ),
        ]);
    }

    public function test_the_gold_page_shows_a_price_per_gram(): void
    {
        $this->fakeMetals(gold: 4161.5, usdPerEur: 1.1225);

        // 4161.50 / 1.1225 = 3707.35 € per ounce; / 31.1034768 = 119.19 € per gram.
        $this->get('/goldpreis')
            ->assertOk()
            ->assertSee('Goldpreis')
            ->assertSee('119,19')
            ->assertSee('3.707,35');
    }

    public function test_the_gold_page_shows_silver_too(): void
    {
        $this->fakeMetals();

        $this->get('/goldpreis')->assertOk()->assertSee('Silberpreis');
    }

    public function test_the_page_still_loads_when_the_prices_are_unavailable(): void
    {
        $this->fakeWeather();
        Http::fake([
            '*gold-api.com/*'      => Http::response([], 503),
            '*eurofxref-daily.xml' => Http::response([], 503),
        ]);

        $this->get('/goldpreis')
            ->assertOk()
            ->assertSee('nicht abrufbar')
            // The word is in the meta description too, so look for a figure.
            ->assertDontSee('Silber je Gramm');
    }

    /** Silver is a bonus; losing it must not cost us the gold price. */
    public function test_gold_survives_silver_being_unavailable(): void
    {
        $this->fakeWeather();
        Http::fake([
            '*gold-api.com/price/XAU' => Http::response(['price' => 4000]),
            '*gold-api.com/price/XAG' => Http::response([], 500),
            '*eurofxref-daily.xml'    => Http::response("<Cube currency='USD' rate='1.10'/>"),
        ]);

        $prices = MetalPrices::current();

        $this->assertNotNull($prices['gold']);
        $this->assertNull($prices['silver']);
    }

    /**
     * The ECB is the source German reporting is expected to quote, so it is
     * asked first and named on the page when it answers.
     */
    public function test_the_ecb_rate_is_preferred_and_credited(): void
    {
        $this->fakeMetals();

        $this->get('/goldpreis')->assertOk()->assertSee('EZB-Referenzkurs');
    }

    /**
     * On some hosts the ECB's certificate chain will not validate, and a gold
     * page is no use without a euro rate. The stand-in has to work, and the
     * page has to stop claiming a figure came from the ECB.
     */
    public function test_a_stand_in_rate_is_used_when_the_ecb_cannot_be_reached(): void
    {
        $this->fakeWeather();

        Http::fake([
            '*eurofxref-daily.xml'    => fn () => throw new \RuntimeException('SSL certificate problem'),
            '*frankfurter.dev*'       => Http::response(['rates' => ['EUR' => 0.89087]]),
            '*gold-api.com/price/XAU' => Http::response(['price' => 4161.5]),
            '*gold-api.com/price/XAG' => Http::response(['price' => 61.96]),
        ]);

        $prices = MetalPrices::current();

        $this->assertNotNull($prices);
        $this->assertSame('Frankfurter', $prices['rate_source']);
        // 1 / 0.89087 = 1.1225, the same figure the ECB would have given.
        $this->assertSame(1.1225, $prices['usd_per_eur']);

        $this->get('/goldpreis')->assertOk()->assertDontSee('EZB-Referenzkurs');
    }

    public function test_both_rate_sources_failing_leaves_no_price(): void
    {
        $this->fakeWeather();

        Http::fake([
            '*eurofxref-daily.xml'    => Http::response([], 503),
            '*frankfurter.dev*'       => Http::response([], 503),
            '*gold-api.com/price/XAU' => Http::response(['price' => 4161.5]),
        ]);

        $this->assertNull(MetalPrices::current());
    }

    public function test_prices_are_cached_rather_than_fetched_every_time(): void
    {
        $this->fakeMetals();

        MetalPrices::current();
        MetalPrices::current();

        // Three calls make one reading: the euro rate, gold, silver.
        Http::assertSentCount(3);
    }

    public function test_a_price_failure_is_remembered(): void
    {
        $this->fakeWeather();
        Http::fake(['*eurofxref-daily.xml' => Http::response([], 503)]);

        MetalPrices::current();

        $this->assertFalse(Cache::get('metal.prices'));
    }

    /* ---------------------------- discovery ---------------------------- */

    public function test_the_tools_are_in_the_sitemap(): void
    {
        $this->fakeWeather();

        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertSee('/altersrechner')
            ->assertSee('/plz-suche')
            ->assertSee('/goldpreis');
    }

    public function test_the_tools_are_linked_from_the_footer(): void
    {
        $this->fakeWeather();

        $this->get('/altersrechner')
            ->assertOk()
            ->assertSee('Service-Tools')
            ->assertSee(route('tools.gold'), false);
    }
}
