<?php

namespace App\Scraping;

/**
 * Contract for an AI provider that rewrites a scraped article's
 * title + description into fresh, unique copy.
 */
interface AiRewriter
{
    /**
     * @return array{title:string, excerpt:string, body:string}|null
     *         null when the provider isn't configured or the call fails
     *         (caller keeps the original text as fallback).
     */
    public function rewrite(string $title, string $body, string $language): ?array;

    /**
     * Whether this provider has an API key configured.
     */
    public function isConfigured(): bool;
}
