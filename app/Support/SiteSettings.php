<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Storage;

/**
 * Site-wide settings editable from the admin "General Settings" page.
 * Falls back to sensible German defaults when nothing is saved yet.
 */
class SiteSettings
{
    /**
     * Setting key => default value.
     *
     * @var array<string, string>
     */
    public const DEFAULTS = [
        'site_name'        => 'News Paper',
        'site_tagline'     => 'Aktuelle Nachrichten, Analysen und Updates – den ganzen Tag.',
        'site_description' => 'Aktuelle Nachrichten, Eilmeldungen und Analysen.',
        'site_logo'        => '',
        'newsletter_text'  => 'Abonnieren Sie, um aktuelle Nachrichten per E-Mail zu erhalten.',
        'copyright_text'   => 'Alle Rechte vorbehalten.',
        'contact_email'    => '',
        'social_facebook'  => '',
        'social_twitter'   => '',
        'social_instagram' => '',
        'social_youtube'   => '',
    ];

    public static function get(string $key): string
    {
        $value = Setting::get($key);

        return $value !== null && $value !== ''
            ? (string) $value
            : (self::DEFAULTS[$key] ?? '');
    }

    /**
     * All settings, keyed — handy for sharing with a view.
     *
     * @return array<string, string>
     */
    public static function all(): array
    {
        $out = [];
        foreach (array_keys(self::DEFAULTS) as $key) {
            $out[$key] = self::get($key);
        }

        return $out;
    }

    public static function name(): string
    {
        return self::get('site_name');
    }

    /**
     * Public URL of the uploaded logo, or null when none is set.
     */
    public static function logoUrl(): ?string
    {
        $path = self::get('site_logo');

        if ($path === '') {
            return null;
        }

        return Storage::disk('public')->url($path);
    }

    /**
     * Social links that are actually filled in.
     *
     * @return array<string, string> label => url
     */
    public static function socialLinks(): array
    {
        $links = [
            'Facebook'  => self::get('social_facebook'),
            'X'         => self::get('social_twitter'),
            'Instagram' => self::get('social_instagram'),
            'YouTube'   => self::get('social_youtube'),
        ];

        return array_filter($links, fn ($url) => $url !== '');
    }
}
