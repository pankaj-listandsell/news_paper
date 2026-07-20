<?php

use App\Support\SiteSettings;
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
    ->withoutOverlapping();

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
