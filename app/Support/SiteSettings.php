<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Crypt;
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
        'gtm_id'                   => '',
        'google_site_verification' => '',
        // Search engine indexing ('1' = allow, '0' = noindex)
        'search_indexing'          => '1',
        // GDPR cookie consent banner ('1' = on, gates Analytics behind consent)
        'cookie_banner'            => '1',
        // Show the comment section on articles ('1' = on, '0' = off)
        'comments_enabled'         => '1',
        // Content pages (imprint + privacy are required for German sites)
        'about_content'   => '',
        'imprint_content' => '',
        'privacy_content' => '',
        // Scraping schedule
        'scrape_frequency' => 'times',
        'scrape_times'     => '06:00,14:00,23:00',
        // Email a run summary after each scrape ('1' = on, '0' = off)
        'scrape_notify'    => '0',
    ];

    public static function get(string $key): string
    {
        // Guarded so it never breaks console commands run before the
        // settings table exists (e.g. during the first migrate).
        try {
            $value = Setting::get($key);
        } catch (\Throwable) {
            $value = null;
        }

        return $value !== null && $value !== ''
            ? (string) $value
            : (self::DEFAULTS[$key] ?? '');
    }

    public static function scrapeFrequency(): string
    {
        return self::get('scrape_frequency') ?: 'times';
    }

    /**
     * Whether to email a summary to the admin after each scrape run.
     */
    public static function scrapeNotify(): bool
    {
        return self::get('scrape_notify') === '1';
    }

    /**
     * Whether the comment section is shown (and accepts new comments).
     */
    public static function commentsEnabled(): bool
    {
        return self::get('comments_enabled') !== '0';
    }

    /**
     * Where scrape notifications go: the contact email, else the oldest
     * admin user's address. Null when neither exists.
     */
    public static function notifyRecipient(): ?string
    {
        $email = self::get('contact_email');

        if ($email !== '') {
            return $email;
        }

        return \App\Models\User::query()->oldest('id')->value('email');
    }

    /*
    |--------------------------------------------------------------------------
    | Mail (SMTP) — configured from the admin, overrides .env at runtime.
    | These keys are deliberately NOT in DEFAULTS so credentials never leak
    | into the public view data shared by all().
    |--------------------------------------------------------------------------
    */

    /**
     * True once a host + username are saved — i.e. admin SMTP is in use.
     */
    public static function mailConfigured(): bool
    {
        return self::get('mail_host') !== '' && self::get('mail_username') !== '';
    }

    /**
     * The stored SMTP password, decrypted. Falls back to the raw value for
     * any legacy plaintext entry.
     */
    public static function mailPassword(): string
    {
        $stored = self::get('mail_password');

        if ($stored === '') {
            return '';
        }

        try {
            return Crypt::decryptString($stored);
        } catch (\Throwable) {
            return $stored;
        }
    }

    /**
     * Mail fields for prefilling the settings form (password omitted — it is
     * never sent back to the browser).
     *
     * @return array<string, string>
     */
    public static function mailSettings(): array
    {
        return [
            'mail_mailer'       => self::get('mail_mailer') ?: 'smtp',
            'mail_host'         => self::get('mail_host'),
            'mail_port'         => self::get('mail_port') ?: '587',
            'mail_username'     => self::get('mail_username'),
            'mail_local_domain' => self::get('mail_local_domain') ?: 'localhost',
            'mail_from_address' => self::get('mail_from_address'),
            'mail_from_name'    => self::get('mail_from_name'),
        ];
    }

    /**
     * Point Laravel's mailer at the admin-configured SMTP account. No-op when
     * nothing is saved, so the .env config keeps working as a fallback.
     */
    public static function applyMailConfig(): void
    {
        $mailer = self::get('mail_mailer') ?: 'smtp';
        $host   = self::get('mail_host');

        // Nothing configured for SMTP — leave the .env config in charge.
        if ($mailer === 'smtp' && $host === '') {
            return;
        }

        config(['mail.default' => $mailer]);

        if ($mailer === 'smtp') {
            // 465 = implicit SSL, everything else (587/25) = STARTTLS.
            $port = (int) (self::get('mail_port') ?: 587);

            config([
                'mail.mailers.smtp.transport'    => 'smtp',
                'mail.mailers.smtp.host'         => $host,
                'mail.mailers.smtp.port'         => $port,
                'mail.mailers.smtp.username'     => self::get('mail_username'),
                'mail.mailers.smtp.password'     => self::mailPassword(),
                'mail.mailers.smtp.encryption'   => $port === 465 ? 'ssl' : 'tls',
                'mail.mailers.smtp.local_domain' => self::get('mail_local_domain') ?: null,
            ]);
        }

        config([
            'mail.from.address' => self::get('mail_from_address') ?: self::get('mail_username'),
            'mail.from.name'    => self::get('mail_from_name') ?: self::name(),
        ]);
    }

    /**
     * Valid HH:MM times the scrape should run at (specific-times mode).
     *
     * @return array<int, string>
     */
    public static function scrapeTimes(): array
    {
        $times = array_filter(
            array_map('trim', explode(',', self::get('scrape_times'))),
            fn ($t) => preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $t)
        );

        // Zero-padded HH:MM sorts chronologically as plain strings.
        $times = array_unique($times);
        sort($times);

        return $times ?: ['06:00', '14:00', '23:00'];
    }

    /**
     * Selectable scrape times — full hours only (00:00 … 23:00), which always
     * line up with the every-5-minutes cron that runs the scheduler.
     *
     * @return array<string, string>
     */
    public static function scrapeTimeOptions(): array
    {
        $options = [];

        for ($h = 0; $h < 24; $h++) {
            $time = sprintf('%02d:00', $h);
            $options[$time] = $time;
        }

        return $options;
    }

    /**
     * @return array<string, string>
     */
    public static function scrapeFrequencyOptions(): array
    {
        return [
            'times'    => 'At specific times each day',
            'hourly'   => 'Every hour',
            'every_30' => 'Every 30 minutes',
            'every_15' => 'Every 15 minutes',
        ];
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
