<?php

use App\Social\FacebookPublisher;
use App\Social\LinkedInPublisher;
use App\Social\XPublisher;

return [

    /*
    |--------------------------------------------------------------------------
    | Drivers
    |--------------------------------------------------------------------------
    | Which class talks to which platform. Adding a platform means adding a
    | SocialPublisher implementation and one line here.
    */
    'drivers' => [
        'facebook' => FacebookPublisher::class,
        'linkedin' => LinkedInPublisher::class,
        'x'        => XPublisher::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Practice mode
    |--------------------------------------------------------------------------
    | With this on, nothing leaves the server: every platform is served by the
    | logging driver instead, which records the share exactly as a real post
    | would but only writes the message to the log. It is how the whole flow
    | is exercised before any API credentials exist. Overridable from the
    | admin Social settings page.
    */
    'practice_mode' => env('SOCIAL_PRACTICE_MODE', false),

    /*
    |--------------------------------------------------------------------------
    | Scheduled posting
    |--------------------------------------------------------------------------
    */
    'schedule' => [
        // Most posts to push out in a single scheduled run, per platform.
        // Deliberately small: platforms read a sudden burst as spam.
        'batch_size' => 5,

        // How many times a failed share is retried before it is left alone.
        'max_attempts' => 3,
    ],

];
