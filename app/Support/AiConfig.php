<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Resolves AI settings, preferring values saved in the admin Settings
 * page (DB) and falling back to config/ai.php (.env).
 */
class AiConfig
{
    public static function provider(): string
    {
        return Setting::get('ai_provider') ?: config('ai.default', 'claude');
    }

    public static function language(): string
    {
        return Setting::get('ai_language') ?: config('ai.language', 'German');
    }

    public static function apiKey(string $provider): ?string
    {
        return Setting::get("ai_{$provider}_api_key")
            ?: config("ai.providers.{$provider}.api_key");
    }

    public static function model(string $provider): string
    {
        return Setting::get("ai_{$provider}_model")
            ?: config("ai.providers.{$provider}.model", '');
    }

    public static function imageModel(): string
    {
        return Setting::get('ai_openai_image_model')
            ?: config('ai.providers.openai.image_model', 'gpt-image-1');
    }

    public static function imageQuality(): string
    {
        return Setting::get('ai_openai_image_quality')
            ?: config('ai.providers.openai.image_quality', 'low');
    }

    /**
     * @return array<string, string>  model id => label
     */
    public static function imageModelOptions(): array
    {
        return [
            'gpt-image-2'      => 'gpt-image-2 (newest, best quality)',
            'gpt-image-1.5'    => 'gpt-image-1.5',
            'gpt-image-1'      => 'gpt-image-1',
            'gpt-image-1-mini' => 'gpt-image-1-mini (cheapest)',
        ];
    }

    /**
     * @return array<string, string> provider key => human label
     */
    public static function providerOptions(): array
    {
        $options = [];
        foreach (config('ai.providers', []) as $key => $cfg) {
            $options[$key] = $cfg['label'] ?? ucfirst($key);
        }

        return $options;
    }
}
