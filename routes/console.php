<?php

use App\Support\SiteSettings;
use App\Support\SocialSettings;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Auto-scrape schedule — configured from the admin "General Settings" page.
 * Times run in German local time (DST-safe, independent of host timezone).
 * --sync runs everything inline (no queue worker needed on shared hosting).
 */
$scrape = fn () => Schedule::command('news:scrape --sync')
    ->timezone('Europe/Berlin')
    ->withoutOverlapping()
    ->evenInMaintenanceMode();

switch (SiteSettings::scrapeFrequency()) {
    case 'every_15':
        $scrape()->everyFifteenMinutes();
        break;
    case 'every_30':
        $scrape()->everyThirtyMinutes();
        break;
    case 'hourly':
        $scrape()->hourly();
        break;
    default: // specific times
        foreach (SiteSettings::scrapeTimes() as $time) {
            $scrape()->dailyAt($time);
        }
}

/*
 * Social auto-posting — configured from the admin "Social Posting" page.
 * Runs inline for the same reason the scrape does: no queue worker on the host.
 * Each run pushes out only a handful per platform, because a burst of posts
 * reads as spam.
 */
$social = fn () => Schedule::command('social:publish')
    ->timezone('Europe/Berlin')
    ->withoutOverlapping();

switch (SocialSettings::frequency()) {
    case 'every_30':
        $social()->everyThirtyMinutes();
        break;
    case 'hourly':
        $social()->hourly();
        break;
    default: // specific times
        foreach (SocialSettings::scheduleTimes() as $time) {
            $social()->dailyAt($time);
        }
}
