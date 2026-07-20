<?php

namespace App\Scraping;

use App\Support\AiConfig;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Generates a fresh illustration for an article using OpenAI's image API
 * (gpt-image-1) and stores it on the public disk.
 *
 * NOTE: the result is an AI illustration, NOT a real photo of the event.
 */
class AiImageGenerator
{
    public function isConfigured(): bool
    {
        return filled(AiConfig::apiKey('openai'));
    }

    /**
     * @param  string|null  $context  category / topic used for the safe fallback prompt
     * @return string|null  disk path (e.g. "articles/ai/xyz.png") or null on failure
     */
    public function generate(string $title, ?string $context = null): ?string
    {
        if (blank(AiConfig::apiKey('openai'))) {
            return null;
        }

        // First try a headline-based prompt; if the safety system blocks it,
        // fall back to a neutral topic-based prompt so an image is still made.
        $bytes = $this->request($this->headlinePrompt($title));

        if ($bytes === null) {
            $bytes = $this->request($this->safePrompt($context));
        }

        if (blank($bytes)) {
            return null;
        }

        // Resize + re-encode: ~1.4 MB PNG becomes a ~100 KB WebP.
        $image = \App\Support\ImageOptimizer::optimize($bytes);

        $path = 'articles/ai/' . Str::uuid() . '.' . $image['extension'];
        Storage::disk('public')->put($path, $image['bytes']);

        return $path;
    }

    /**
     * Call the image API once. Returns raw image bytes or null.
     * Retries transient network errors; does NOT retry a moderation block
     * (the caller falls back to a safer prompt instead).
     */
    private function request(string $prompt): ?string
    {
        $apiKey = AiConfig::apiKey('openai');

        try {
            $response = Http::withToken($apiKey)
                ->timeout(120)
                ->retry(2, 1000, throw: false) // transient network/SSL errors
                ->post(config('ai.providers.openai.image_url'), [
                    'model'   => AiConfig::imageModel(),
                    'prompt'  => $prompt,
                    'n'       => 1,
                    'size'    => config('ai.providers.openai.image_size', '1024x1024'),
                    'quality' => AiConfig::imageQuality(),
                ]);

            if (! $response->successful()) {
                Log::warning('AI image failed: ' . $response->status() . ' ' . Str::limit($response->body(), 300));

                return null;
            }

            \App\Models\AiUsage::record(
                'openai',
                AiConfig::imageModel(),
                'image',
                (int) $response->json('usage.input_tokens', 0),
                (int) $response->json('usage.output_tokens', 0),
            );

            if ($b64 = $response->json('data.0.b64_json')) {
                return base64_decode($b64);
            }

            if ($url = $response->json('data.0.url')) {
                return Http::timeout(60)->retry(2, 1000, throw: false)->get($url)->body();
            }

            return null;
        } catch (\Throwable $e) {
            Log::warning('AI image error: ' . $e->getMessage());

            return null;
        }
    }

    private function headlinePrompt(string $title): string
    {
        return 'A clean, professional editorial news illustration for a newspaper, '
            . 'representing this topic: "' . Str::limit($title, 200, '') . '". '
            . 'Tasteful, safe-for-work, photorealistic, neutral tone. '
            . 'No text, no words, no watermark, no logos, no graphic or sensitive content.';
    }

    private function safePrompt(?string $context): string
    {
        $topic = $context ? "the topic of {$context}" : 'a general news story';

        return "A clean, professional, generic editorial news photo representing {$topic}. "
            . 'Neutral, tasteful, safe-for-work, photorealistic. '
            . 'No people in distress, no text, no words, no watermark, no logos.';
    }
}
