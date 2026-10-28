<?php

namespace App\Scraping;

use App\Support\ImageOptimizer;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Brings a feed's own photograph onto our disk.
 *
 * Left as a link, the picture lives on the publisher's server: they can block
 * it, move it or delete it, and a months-old article quietly loses its image.
 * Every reader's browser also fetches it from them, which puts our domain in
 * their logs and our page speed in their hands.
 *
 * So the bytes are copied once, resized and re-encoded the same way AI images
 * are, and served from our own storage from then on.
 */
class SourceImageFetcher
{
    /** Anything larger than this is not a news photo worth having. */
    private const MAX_BYTES = 12 * 1024 * 1024;

    private const TIMEOUT = 15;

    /**
     * @return string|null  a path on the public disk, or null to leave the
     *                      caller's original URL alone
     */
    public function fetch(?string $url): ?string
    {
        if (blank($url) || ! Str::startsWith($url, ['http://', 'https://'])) {
            return null;
        }

        try {
            $response = Http::timeout(self::TIMEOUT)
                ->withHeaders(['Accept' => 'image/*'])
                ->get($url);
        } catch (\Throwable $e) {
            Log::info("Source image could not be fetched: {$e->getMessage()}");

            return null;
        }

        if ($response->failed()) {
            Log::info("Source image returned HTTP {$response->status()}: {$url}");

            return null;
        }

        // A publisher blocking hotlinks often answers with an HTML page rather
        // than an error, so the body has to be checked, not just the status.
        if (! Str::startsWith((string) $response->header('Content-Type'), 'image/')) {
            Log::info("Source image was not an image: {$url}");

            return null;
        }

        $bytes = $response->body();

        if (strlen($bytes) === 0 || strlen($bytes) > self::MAX_BYTES) {
            return null;
        }

        try {
            $image = ImageOptimizer::optimize($bytes);
        } catch (\Throwable $e) {
            Log::info("Source image could not be processed: {$e->getMessage()}");

            return null;
        }

        $path = 'articles/source/' . Str::uuid() . '.' . $image['extension'];

        Storage::disk('public')->put($path, $image['bytes']);

        return $path;
    }
}
