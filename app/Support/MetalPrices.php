<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Gold and silver, priced in euro.
 *
 * Two free sources with no API key between them: gold-api.com quotes the
 * metals in dollars per troy ounce, and the European Central Bank publishes
 * the day's euro reference rates. Germany buys gold by the gram, so that is
 * what the page leads with.
 *
 * Nothing here may break a page. Every failure returns null and the page
 * says the price is unavailable.
 */
class MetalPrices
{
    private const CACHE_KEY = 'metal.prices';

    /** Metal quotes move all day, but not enough to chase by the minute. */
    private const FRESH_MINUTES = 15;

    /** Long enough that an outage cannot make every reader wait out a timeout. */
    private const FAILED_MINUTES = 5;

    /** One troy ounce, in grams. */
    private const GRAMS_PER_OUNCE = 31.1034768;

    /**
     * @return array{
     *     gold: array{gram:float, ounce:float, kilo:float},
     *     silver: array{gram:float, ounce:float, kilo:float},
     *     usd_per_eur: float,
 *     rate_source: string,
     *     fetched_at: \Illuminate\Support\Carbon
     * }|null
     */
    public static function current(): ?array
    {
        try {
            $cached = Cache::get(self::CACHE_KEY);

            if ($cached !== null) {
                // false is how a known failure is remembered.
                return $cached ?: null;
            }

            $fresh = self::fetch();

            Cache::put(
                self::CACHE_KEY,
                $fresh ?? false,
                now()->addMinutes($fresh ? self::FRESH_MINUTES : self::FAILED_MINUTES),
            );

            return $fresh;
        } catch (\Throwable $e) {
            Log::warning('Metal price lookup failed: ' . $e->getMessage());

            return null;
        }
    }

    private static function fetch(): ?array
    {
        $euro = self::usdPerEur();

        if ($euro === null) {
            return null;
        }

        $gold   = self::ouncePriceInUsd('XAU');
        $silver = self::ouncePriceInUsd('XAG');

        // Gold is the point of the page; silver is a bonus.
        if ($gold === null) {
            return null;
        }

        return [
            'gold'        => self::breakdown($gold / $euro['rate']),
            'silver'      => $silver === null ? null : self::breakdown($silver / $euro['rate']),
            'usd_per_eur' => $euro['rate'],
            'rate_source' => $euro['source'],
            'fetched_at'  => now(),
        ];
    }

    /**
     * @return array{gram:float, ounce:float, kilo:float}
     */
    private static function breakdown(float $eurPerOunce): array
    {
        $perGram = $eurPerOunce / self::GRAMS_PER_OUNCE;

        return [
            'gram'  => round($perGram, 2),
            'ounce' => round($eurPerOunce, 2),
            'kilo'  => round($perGram * 1000, 2),
        ];
    }

    private static function ouncePriceInUsd(string $symbol): ?float
    {
        try {
            $response = Http::timeout(5)->get("https://api.gold-api.com/price/{$symbol}");
        } catch (\Throwable) {
            return null;
        }

        if ($response->failed()) {
            return null;
        }

        $price = $response->json('price');

        return is_numeric($price) && $price > 0 ? (float) $price : null;
    }

    /**
     * Dollars per euro.
     *
     * The ECB's own daily reference rate is the figure German reporting is
     * expected to use, so it is asked first. Frankfurter stands in when the
     * ECB cannot be reached — on some hosts its certificate chain will not
     * validate, and a gold page is no use at all without a euro rate.
     *
     * @return array{rate:float, source:string}|null
     */
    private static function usdPerEur(): ?array
    {
        if ($rate = self::ecbRate()) {
            return ['rate' => $rate, 'source' => 'EZB-Referenzkurs'];
        }

        if ($rate = self::frankfurterRate()) {
            return ['rate' => $rate, 'source' => 'Frankfurter'];
        }

        return null;
    }

    private static function ecbRate(): ?float
    {
        try {
            $response = Http::timeout(5)->get('https://www.ecb.europa.eu/stats/eurofxref/eurofxref-daily.xml');
        } catch (\Throwable) {
            return null;
        }

        if ($response->failed()) {
            return null;
        }

        if (! preg_match("/currency=['\"]USD['\"]\s+rate=['\"]([0-9.]+)['\"]/", $response->body(), $m)) {
            return null;
        }

        $rate = (float) $m[1];

        return $rate > 0 ? $rate : null;
    }

    /**
     * Frankfurter quotes euro per dollar, which is the rate the other way up.
     */
    private static function frankfurterRate(): ?float
    {
        try {
            $response = Http::timeout(5)->get('https://api.frankfurter.dev/v1/latest', [
                'base'    => 'USD',
                'symbols' => 'EUR',
            ]);
        } catch (\Throwable) {
            return null;
        }

        if ($response->failed()) {
            return null;
        }

        $eurPerUsd = $response->json('rates.EUR');

        return is_numeric($eurPerUsd) && $eurPerUsd > 0
            ? round(1 / (float) $eurPerUsd, 4)
            : null;
    }
}
