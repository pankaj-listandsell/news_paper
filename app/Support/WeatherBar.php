<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Berlin's current weather and air quality, for the strip above the header.
 *
 * Open-Meteo is used because it is free and needs no API key, so there is no
 * credential to keep alive. Both calls are cached: the strip appears on every
 * page of the site, and one lookup every half hour is plenty for a figure that
 * is only shown to the nearest degree.
 *
 * Nothing here is allowed to break a page. Every failure returns null and the
 * strip simply leaves the weather out.
 */
class WeatherBar
{
    /** Berlin, Brandenburger Tor. */
    private const LAT = 52.52;
    private const LON = 13.405;

    private const CACHE_KEY = 'weather.berlin';

    /** How long a good reading is kept. A degree does not move that fast. */
    private const FRESH_MINUTES = 30;

    /**
     * How long a failure is remembered. Without this, an API that is down
     * would be retried on every single page view, and every reader would wait
     * out the timeout.
     */
    private const FAILED_MINUTES = 5;

    /**
     * @return array{temperature:int, label:string, icon:string, aqi:?int, aqi_label:?string}|null
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
            // A cache or network problem must not take the page down with it.
            Log::warning('Weather lookup failed: ' . $e->getMessage());

            return null;
        }
    }

    /**
     * @return array{temperature:int, label:string, icon:string, aqi:?int, aqi_label:?string}|null
     */
    private static function fetch(): ?array
    {
        $weather = Http::timeout(3)->get('https://api.open-meteo.com/v1/forecast', [
            'latitude'  => self::LAT,
            'longitude' => self::LON,
            'current'   => 'temperature_2m,weather_code',
            'timezone'  => 'Europe/Berlin',
        ]);

        if ($weather->failed() || $weather->json('current.temperature_2m') === null) {
            return null;
        }

        $code = (int) $weather->json('current.weather_code', 0);

        return [
            'temperature' => (int) round((float) $weather->json('current.temperature_2m')),
            'label'       => self::describe($code),
            'icon'        => self::icon($code),
            ...self::airQuality(),
        ];
    }

    /**
     * The European AQI, which is the scale German readers will recognise.
     *
     * @return array{aqi:?int, aqi_label:?string}
     */
    private static function airQuality(): array
    {
        $air = Http::timeout(3)->get('https://air-quality-api.open-meteo.com/v1/air-quality', [
            'latitude'  => self::LAT,
            'longitude' => self::LON,
            'current'   => 'european_aqi',
            'timezone'  => 'Europe/Berlin',
        ]);

        $value = $air->successful() ? $air->json('current.european_aqi') : null;

        if ($value === null) {
            return ['aqi' => null, 'aqi_label' => null];
        }

        $aqi = (int) round((float) $value);

        return ['aqi' => $aqi, 'aqi_label' => self::aqiLabel($aqi)];
    }

    /**
     * The European AQI bands, in the words the EEA uses for them.
     */
    private static function aqiLabel(int $aqi): string
    {
        return match (true) {
            $aqi <= 20  => 'gut',
            $aqi <= 40  => 'ziemlich gut',
            $aqi <= 60  => 'mittel',
            $aqi <= 80  => 'schlecht',
            $aqi <= 100 => 'sehr schlecht',
            default     => 'extrem schlecht',
        };
    }

    /**
     * WMO weather codes, grouped into the handful of states worth naming.
     */
    private static function describe(int $code): string
    {
        return match (true) {
            $code === 0            => 'klar',
            $code <= 2             => 'leicht bewölkt',
            $code === 3            => 'bedeckt',
            $code <= 48            => 'neblig',
            $code <= 57            => 'Nieselregen',
            $code <= 67            => 'Regen',
            $code <= 77            => 'Schnee',
            $code <= 82            => 'Schauer',
            $code <= 86            => 'Schneeschauer',
            default                => 'Gewitter',
        };
    }

    private static function icon(int $code): string
    {
        return match (true) {
            $code === 0            => '☀',
            $code <= 2             => '🌤',
            $code === 3            => '☁',
            $code <= 48            => '🌫',
            $code <= 67            => '🌧',
            $code <= 77            => '❄',
            $code <= 86            => '🌦',
            default                => '⛈',
        };
    }
}
