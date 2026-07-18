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
        'site_favicon'     => '',
        'brand_color'      => '#dc2626',
        'newsletter_text'  => 'Abonnieren Sie, um aktuelle Nachrichten per E-Mail zu erhalten.',
        'copyright_text'   => 'Alle Rechte vorbehalten.',
        'contact_email'    => '',
        'social_facebook'  => '',
        'social_twitter'   => '',
        'social_instagram' => '',
        'social_youtube'   => '',
        // Tracking / verification
        'google_analytics_id'      => '',
        'google_site_verification' => '',
        // Content pages (imprint + privacy are required for German sites)
        'about_content'   => '',
        'imprint_content' => '',
        'privacy_content' => '',
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
        return self::fileUrl('site_logo');
    }

    /**
     * Public URL of the uploaded favicon, or null when none is set.
     */
    public static function faviconUrl(): ?string
    {
        return self::fileUrl('site_favicon');
    }

    private static function fileUrl(string $key): ?string
    {
        $path = self::get($key);

        if ($path === '') {
            return null;
        }

        return Storage::disk('public')->url($path);
    }

    /**
     * The brand colour plus a darker hover shade and a light tint,
     * derived from the single colour the admin picks.
     *
     * @return array{base:string, dark:string, soft:string}
     */
    public static function brandColors(): array
    {
        $base = self::get('brand_color');

        if (! preg_match('/^#[0-9a-f]{6}$/i', $base)) {
            $base = self::DEFAULTS['brand_color'];
        }

        [$r, $g, $b] = sscanf($base, '#%02x%02x%02x');

        return [
            'base' => $base,
            // ~15% darker, for hover states
            'dark' => sprintf('#%02x%02x%02x', (int) ($r * 0.85), (int) ($g * 0.85), (int) ($b * 0.85)),
            // ~8% tint on white, for subtle hover backgrounds
            'soft' => sprintf(
                '#%02x%02x%02x',
                (int) ($r + (255 - $r) * 0.92),
                (int) ($g + (255 - $g) * 0.92),
                (int) ($b + (255 - $b) * 0.92)
            ),
        ];
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
