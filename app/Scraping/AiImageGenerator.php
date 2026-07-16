<?php

namespace App\Scraping;

use App\Support\AiConfig;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Generates a fresh illustration for an article using OpenAI's image API
 * (DALL·E) and stores it on the public disk.
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
     * @return string|null  disk path (e.g. "articles/ai/xyz.png") or null on failure
     */
    public function generate(string $title): ?string
    {
        $apiKey = AiConfig::apiKey('openai');

        if (blank($apiKey)) {
            return null;
        }

        try {
            $response = Http::withToken($apiKey)
                ->timeout(120)
                ->post(config('ai.providers.openai.image_url'), [
                    'model'   => config('ai.providers.openai.image_model', 'gpt-image-1'),
                    'prompt'  => $this->prompt($title),
                    'n'       => 1,
                    'size'    => config('ai.providers.openai.image_size', '1024x1024'),
                    'quality' => config('ai.providers.openai.image_quality', 'low'),
                ]);

            if (! $response->successful()) {
                Log::warning('AI image failed: ' . $response->status() . ' ' . $response->body());

                return null;
            }

            // Newer models return base64; dall-e-3 returns a temporary URL.
            $bytes = null;
            if ($b64 = $response->json('data.0.b64_json')) {
                $bytes = base64_decode($b64);
            } elseif ($url = $response->json('data.0.url')) {
                $bytes = Http::timeout(60)->get($url)->body();
            }

            if (blank($bytes)) {
                return null;
            }

            $path = 'articles/ai/' . Str::uuid() . '.png';
            Storage::disk('public')->put($path, $bytes);

            return $path;
        } catch (\Throwable $e) {
            Log::warning('AI image error: ' . $e->getMessage());

            return null;
        }
    }

    private function prompt(string $title): string
    {
        return "Editorial news illustration representing this headline: \"{$title}\". "
            . 'Photorealistic, high quality, no text, no words, no watermark, no logos.';
    }
}
