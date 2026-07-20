<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Auto-scrape configured RSS sources 3x a day, in German local time.
// Using the scheduler (not fixed server-time cron) keeps these exact
// through daylight-saving changes, whatever the host's timezone is.
// --sync runs everything inline (no queue worker needed on shared hosting).
foreach (['06:00', '14:00', '23:00'] as $time) {
    Schedule::command('news:scrape --sync')
        ->timezone('Europe/Berlin')
        ->dailyAt($time)
        ->withoutOverlapping();
}
