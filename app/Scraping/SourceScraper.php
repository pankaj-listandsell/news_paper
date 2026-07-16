<?php

namespace App\Scraping;

use App\Models\NewsSource;

/**
 * Contract for every news source scraper.
 *
 * The flow is always: fetch() -> parse() -> normalize(),
 * so a scraper can be swapped (RSS today, HTML/API tomorrow)
 * without the calling job knowing the details.
 */
interface SourceScraper
{
    /**
     * Download the raw payload from the source (RSS XML, HTML, JSON...).
     */
    public function fetch(NewsSource $source): string;

    /**
     * Turn the raw payload into a flat list of loosely-typed items.
     *
     * @return array<int, array<string, mixed>>
     */
    public function parse(string $raw): array;

    /**
     * Map parsed items onto our Article schema (ready for saving).
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    public function normalize(array $items, NewsSource $source): array;
}
