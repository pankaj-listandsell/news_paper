<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Google reCAPTCHA v2 ("I'm not a robot") verification.
 */
class Recaptcha
{
    private const VERIFY_URL = 'https://www.google.com/recaptcha/api/siteverify';

    /**
     * True when the submission may proceed. Always true while reCAPTCHA is
     * not configured, so forms keep working before the keys are entered.
     */
    public static function passes(?string $token, ?string $ip = null): bool
    {
        if (! SiteSettings::recaptchaEnabled()) {
            return true;
        }

        if (blank($token)) {
            return false;
        }

        try {
            $response = Http::asForm()
                ->timeout(10)
                ->post(self::VERIFY_URL, [
                    'secret'   => SiteSettings::recaptchaSecret(),
                    'response' => $token,
                    'remoteip' => $ip,
                ]);

            return (bool) $response->json('success', false);
        } catch (\Throwable $e) {
            // Fail closed — a spammer must not get through on a network blip.
            Log::warning('reCAPTCHA verification failed: ' . $e->getMessage());

            return false;
        }
    }
}
