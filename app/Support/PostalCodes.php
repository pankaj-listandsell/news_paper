<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * German postal code lookup, both ways: a Postleitzahl gives you the places
 * it covers, a place name gives you its codes.
 *
 * OpenPLZ serves the official Destatis data, free and without an API key.
 * Postal codes essentially never change, so an answer is kept for a week.
 */
class PostalCodes
{
    private const BASE = 'https://openplzapi.org/de/Localities';

    /** A postal code is not going to move. */
    private const FRESH_DAYS = 7;

    /** Short, so an outage does not stick around. */
    private const FAILED_MINUTES = 10;

    /**
     * Anything that looks like a German postal code is treated as one;
     * everything else is taken as a place name.
     */
    public static function looksLikeCode(string $query): bool
    {
        return (bool) preg_match('/^\d{5}$/', trim($query));
    }

    /**
     * @return array<int, array{postal_code:string, place:string, district:?string, state:string}>|null
     *         null means the lookup itself failed, as opposed to finding nothing
     */
    public static function search(string $query): ?array
    {
        $query = trim($query);

        if ($query === '' || mb_strlen($query) > 60) {
            return [];
        }

        $key = 'plz.' . md5(mb_strtolower($query));

        try {
            $cached = Cache::get($key);

            if ($cached !== null) {
                return $cached === false ? null : $cached;
            }

            $results = self::fetch($query);

            Cache::put(
                $key,
                $results ?? false,
                $results === null ? now()->addMinutes(self::FAILED_MINUTES) : now()->addDays(self::FRESH_DAYS),
            );

            return $results;
        } catch (\Throwable $e) {
            Log::warning('Postal code lookup failed: ' . $e->getMessage());

            return null;
        }
    }

    /**
     * @return array<int, array{postal_code:string, place:string, district:?string, state:string}>|null
     */
    private static function fetch(string $query): ?array
    {
        $response = Http::timeout(8)->get(self::BASE, [
            self::looksLikeCode($query) ? 'postalCode' : 'name' => $query,
            'page'     => 1,
            'pageSize' => 50,
        ]);

        if ($response->failed()) {
            return null;
        }

        $rows = $response->json();

        if (! is_array($rows)) {
            return null;
        }

        return collect($rows)
            ->map(fn (array $row) => [
                'postal_code' => (string) ($row['postalCode'] ?? ''),
                'place'       => (string) ($row['name'] ?? ''),
                'district'    => $row['district']['name'] ?? $row['municipality']['name'] ?? null,
                'state'       => (string) ($row['federalState']['name'] ?? ''),
            ])
            ->filter(fn (array $row) => $row['postal_code'] !== '' && $row['place'] !== '')
            ->unique(fn (array $row) => $row['postal_code'] . '|' . $row['place'])
            ->sortBy('postal_code')
            ->values()
            ->all();
    }
}
