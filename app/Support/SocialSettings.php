<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Carbon;

/**
 * Social posting settings, saved from the admin "Social Posting" page and
 * falling back to config/social.php.
 */
class SocialSettings
{
    /**
     * Guarded, because routes/console.php reads these while the schedule is
     * being built — which happens before the settings table exists on a first
     * migrate, and would otherwise take every artisan command down with it.
     */
    private static function get(string $key): ?string
    {
        try {
            $value = Setting::get($key);
        } catch (\Throwable) {
            return null;
        }

        return $value === null || $value === '' ? null : (string) $value;
    }

    public static function practiceMode(): bool
    {
        $saved = self::get('social_practice_mode');

        return $saved === null
            ? (bool) config('social.practice_mode', false)
            : $saved === '1';
    }

    /**
     * Most posts one scheduled run may push out per platform.
     */
    public static function batchSize(): int
    {
        return max(1, (int) (self::get('social_batch_size')
            ?: config('social.schedule.batch_size', 5)));
    }

    public static function maxAttempts(): int
    {
        return max(1, (int) config('social.schedule.max_attempts', 3));
    }

    /**
     * Nothing published before this date is ever auto-posted.
     *
     * Without it, switching auto-posting on would try to push the site's whole
     * back catalogue out at once — which is how accounts get blocked.
     */
    public static function backlogCutoff(): ?Carbon
    {
        $value = self::get('social_backlog_cutoff');

        if ($value === null) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Times of day the scheduled run fires, in German local time.
     *
     * @return array<int, string>
     */
    public static function scheduleTimes(): array
    {
        $times = collect(explode(',', (string) self::get('social_schedule_times')))
            ->map(fn ($t) => trim($t))
            ->filter(fn ($t) => preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $t) === 1)
            ->unique()
            ->sort()
            ->values()
            ->all();

        return $times ?: ['09:00', '15:00', '20:00'];
    }

    public static function frequency(): string
    {
        return self::get('social_frequency') ?: 'times';
    }

    /**
     * Selectable times — full hours only, so they line up with the cron that
     * runs the scheduler.
     *
     * @return array<string, string>
     */
    public static function scheduleTimeOptions(): array
    {
        $options = [];

        foreach (range(0, 23) as $hour) {
            $value           = sprintf('%02d:00', $hour);
            $options[$value] = $value;
        }

        return $options;
    }
}
