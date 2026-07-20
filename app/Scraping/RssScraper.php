<?php

namespace App\Scraping;

use App\Models\NewsSource;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laminas\Feed\Reader\Reader;

class RssScraper implements SourceScraper
{
    /**
     * Download the RSS/Atom feed body.
     */
    public function fetch(NewsSource $source): string
    {
        $response = Http::withHeaders([
                'User-Agent' => 'NewsPaperBot/1.0 (+news aggregator)',
                'Accept'     => 'application/rss+xml, application/atom+xml, application/xml;q=0.9, */*;q=0.8',
            ])
            ->timeout(20)
            ->retry(2, 500)
            ->get($source->feed_url);

        $response->throw();

        return $response->body();
    }

    /**
     * Parse the feed XML into a flat list of entry arrays.
     */
    public function parse(string $raw): array
    {
        $feed = Reader::importString($raw);

        $items = [];

        foreach ($feed as $entry) {
            $items[] = [
                'title'       => trim((string) $entry->getTitle()),
                'link'        => (string) $entry->getLink(),
                'summary'     => (string) $entry->getDescription(),
                'content'     => (string) ($entry->getContent() ?: $entry->getDescription()),
                'published'   => $entry->getDateCreated() ?? $entry->getDateModified(),
                'image'       => $this->extractImage($entry),
            ];
        }

        return $items;
    }

    /**
     * Map feed entries onto Article-ready arrays.
     */
    public function normalize(array $items, NewsSource $source): array
    {
        $status      = $source->auto_publish ? 'published' : 'draft';
        $publishedAt = now();

        return collect($items)
            ->filter(fn ($item) => $item['title'] !== '' && $item['link'] !== '')
            // max_items of 0 = no limit (import every item the feed offers).
            ->when($source->max_items > 0, fn ($c) => $c->take($source->max_items))
            ->map(function (array $item) use ($source, $status) {
                $publishedAt = $item['published']
                    ? Carbon::instance($item['published'])
                    : now();

                $summary = trim(strip_tags($item['summary']));

                return [
                    'title'        => Str::limit($item['title'], 250, ''),
                    'slug'         => $this->uniqueSlug($item['title'], $item['link']),
                    'excerpt'      => Str::limit($summary, 300),
                    'body'         => $this->cleanBody($item['content']),
                    'featured_image' => $item['image'],
                    'category_id'  => $source->category_id,
                    'user_id'      => $source->user_id ?? $this->fallbackAuthorId(),
                    'source_id'    => $source->id,
                    'source_name'  => $source->name,
                    'source_url'   => $item['link'],
                    'status'       => $status,
                    'published_at' => $publishedAt,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Pull the best available image from a feed entry.
     */
    private function extractImage($entry): ?string
    {
        // Enclosure (RSS) — often the main image
        $enclosure = $entry->getEnclosure();
        if ($enclosure && isset($enclosure->url) && Str::contains((string) ($enclosure->type ?? ''), 'image')) {
            return (string) $enclosure->url;
        }

        // media:content / media:thumbnail namespace
        try {
            $dom = $entry->getElement();
            $xpath = new \DOMXPath($dom->ownerDocument);
            $xpath->registerNamespace('media', 'http://search.yahoo.com/mrss/');
            foreach (['media:content/@url', 'media:thumbnail/@url'] as $q) {
                $nodes = $xpath->query('.//' . $q, $dom);
                if ($nodes && $nodes->length) {
                    return $nodes->item(0)->nodeValue;
                }
            }
        } catch (\Throwable) {
            // ignore namespace/dom errors
        }

        // First <img> inside the content/description
        $html = $entry->getContent() ?: $entry->getDescription();
        if ($html && preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $html, $m)) {
            return $m[1];
        }

        return null;
    }

    /**
     * Strip scripts/iframes but keep basic formatting.
     */
    private function cleanBody(string $html): string
    {
        $html = preg_replace('#<(script|style|iframe)[^>]*>.*?</\1>#is', '', $html) ?? $html;

        return trim($html) !== '' ? $html : '<p>Read the full story at the original source.</p>';
    }

    private function uniqueSlug(string $title, string $link): string
    {
        $base = Str::slug($title) ?: 'news';

        // Append a short deterministic hash of the source URL to avoid collisions.
        return Str::limit($base, 200, '') . '-' . substr(md5($link), 0, 6);
    }

    private function fallbackAuthorId(): ?int
    {
        return \App\Models\User::query()->min('id');
    }
}
