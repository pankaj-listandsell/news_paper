<?php

namespace App\Scraping;

/**
 * Contract for an AI provider that rewrites a scraped article's
 * title + description into fresh, unique copy.
 */
interface AiRewriter
{
    /**
     * @param  list<string>  $categories  when given, the model also picks one of these
     * @return array{title:string, excerpt:string, body:string, meta_title:string, meta_description:string, category:string}|null
     *         null when the provider isn't configured or the call fails
     *         (caller keeps the original text as fallback).
     */
    public function rewrite(string $title, string $body, string $language, array $categories = []): ?array;

    /**
     * Whether this provider has an API key configured.
     */
    public function isConfigured(): bool;
}
