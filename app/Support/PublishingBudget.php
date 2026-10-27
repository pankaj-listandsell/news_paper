<?php

namespace App\Support;

use App\Models\Article;
use Illuminate\Support\Carbon;

/**
 * How many scraped posts may still go out today, and how many of those this
 * particular run may take.
 *
 * The cap is applied before an article is handed to the AI, not after, so the
 * articles that will not be published cost nothing to begin with. That is the
 * point of it: a day's posting limit is also a day's spending limit.
 *
 * Anything over the budget is dropped rather than held as a draft. The
 * scraper moves last_scraped_at forward either way, so a dropped item never
 * comes back — tomorrow's run brings tomorrow's news, not yesterday's
 * leftovers.
 *
 * One of these is made per run and shared by every source, so the limit is
 * the whole site's, not each feed's.
 */
class PublishingBudget
{
    /** Posting starts at this hour, Berlin time. */
    public const WINDOW_START = 6;

    /** And stops after this one, so nothing posts into the small hours. */
    public const WINDOW_END = 23;

    private int $taken = 0;

    private readonly int $limit;

    private readonly int $alreadyPublished;

    /** What this run may take, fixed when the run starts. */
    private readonly int $allowance;

    public function __construct(?int $limit = null, ?Carbon $at = null)
    {
        $at = $at ?? now();

        $this->limit            = $limit ?? SiteSettings::dailyPostLimit();
        $this->alreadyPublished = self::publishedToday($at);
        $this->allowance        = $this->shareOf($at);
    }

    /**
     * This run's share of what the day has left.
     *
     * Spread over the runs still to come, so the first run of the morning
     * cannot take the lot and leave the site silent until midnight. Always at
     * least one while there is budget, so a quiet hour does not waste the day.
     */
    private function shareOf(Carbon $at): int
    {
        $left = max(0, $this->limit - $this->alreadyPublished);

        if ($left === 0 || ! self::isWithinWindow($at)) {
            return 0;
        }

        $perRun = (int) ceil($left / max(1, self::runsLeftToday($at)));

        return max(1, min($left, $perRun));
    }

    /**
     * Posts the scraper has put out today.
     *
     * Counted by created_at, not published_at: a feed can carry yesterday's
     * date, and what is being limited is how much we post today. Articles
     * written by hand in the admin panel have no source and are not counted —
     * the cap is there to hold back the scraper, not the newsroom.
     */
    public static function publishedToday(?Carbon $at = null): int
    {
        $berlin = ($at ?? now())->copy()->timezone('Europe/Berlin');

        return Article::query()
            ->whereNotNull('source_id')
            ->where('status', 'published')
            ->whereBetween('created_at', [
                $berlin->copy()->startOfDay()->utc(),
                $berlin->copy()->endOfDay()->utc(),
            ])
            ->count();
    }

    /**
     * May this run take one more?
     */
    public function hasRoom(): bool
    {
        return $this->taken < $this->allowance;
    }

    public function take(): void
    {
        $this->taken++;
    }

    public function limit(): int
    {
        return $this->limit;
    }

    public function publishedBefore(): int
    {
        return $this->alreadyPublished;
    }

    public function allowance(): int
    {
        return $this->allowance;
    }

    public function taken(): int
    {
        return $this->taken;
    }

    /**
     * Posts left in the day after this run's takings.
     */
    public function remainingToday(): int
    {
        return max(0, $this->limit - $this->alreadyPublished - $this->taken);
    }

    /**
     * Is it a time of day we post at?
     */
    public static function isWithinWindow(?Carbon $at = null): bool
    {
        $hour = ($at ?? now())->copy()->timezone('Europe/Berlin')->hour;

        return $hour >= self::WINDOW_START && $hour <= self::WINDOW_END;
    }

    /**
     * Scheduled runs still to come today, this one included.
     *
     * Read from the scrape schedule the admin actually configured, so the
     * spread stays right whether that is hourly or a handful of fixed times.
     */
    public static function runsLeftToday(?Carbon $at = null): int
    {
        $now = ($at ?? now())->copy()->timezone('Europe/Berlin');

        if (! self::isWithinWindow($now)) {
            return 0;
        }

        // Whole hours left in the window, this one included.
        $hoursLeft = self::WINDOW_END - $now->hour + 1;

        return match (SiteSettings::scrapeFrequency()) {
            'every_15' => $hoursLeft * 4,
            'every_30' => $hoursLeft * 2,
            'hourly'   => $hoursLeft,
            // Fixed times: only those still ahead and inside the window.
            default    => max(1, collect(SiteSettings::scrapeTimes())
                ->filter(function (string $time) use ($now) {
                    $hour = (int) substr($time, 0, 2);

                    return $hour >= $now->hour
                        && $hour >= self::WINDOW_START
                        && $hour <= self::WINDOW_END;
                })
                ->count()),
        };
    }
}
