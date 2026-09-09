<?php

namespace App\Social;

use App\Support\SocialSettings;

/**
 * Hands out the driver for a platform — or the logging one for every
 * platform while practice mode is on.
 */
class PublisherFactory
{
    public function for(string $platform): ?SocialPublisher
    {
        if (SocialSettings::practiceMode()) {
            return new LogPublisher();
        }

        $class = config("social.drivers.{$platform}");

        if (! $class || ! class_exists($class)) {
            return null;
        }

        return app($class);
    }
}
