<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/**
 * One connected social platform the site can post to.
 *
 * The credentials column holds whatever that platform needs — a page token
 * and page id for Facebook, an organisation urn for LinkedIn, OAuth tokens
 * for X. It is encrypted, so it is read and written through credential()
 * and setCredentials() rather than touched directly.
 */
class SocialAccount extends Model
{
    public const PLATFORMS = [
        'facebook' => 'Facebook Page',
        'linkedin' => 'LinkedIn Page',
        'x'        => 'X (Twitter)',
    ];

    protected $fillable = [
        'platform', 'name', 'credentials', 'token_expires_at',
        'is_active', 'auto_post', 'last_error', 'last_posted_at',
    ];

    protected $casts = [
        'is_active'        => 'boolean',
        'auto_post'        => 'boolean',
        'token_expires_at' => 'datetime',
        'last_posted_at'   => 'datetime',
    ];

    protected $hidden = ['credentials'];

    public function label(): string
    {
        return self::PLATFORMS[$this->platform] ?? $this->platform;
    }

    /**
     * @return array<string, mixed>
     */
    public function credentials(): array
    {
        if (blank($this->credentials)) {
            return [];
        }

        try {
            return json_decode(Crypt::decryptString($this->credentials), true) ?: [];
        } catch (\Throwable) {
            // A rotated APP_KEY makes old ciphertext unreadable. Treat that as
            // "not connected" rather than taking the whole admin panel down.
            return [];
        }
    }

    public function credential(string $key, $default = null)
    {
        return $this->credentials()[$key] ?? $default;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function setCredentials(array $values): void
    {
        // Blank values would otherwise wipe a token when a form is saved
        // without re-typing it.
        $values = array_filter($values, fn ($v) => filled($v));

        $this->credentials = $values === []
            ? null
            : Crypt::encryptString(json_encode($values));
    }

    /**
     * Ready to post: switched on, and the driver has what it needs.
     */
    public function isUsable(): bool
    {
        return $this->is_active && ! $this->tokenHasExpired();
    }

    public function tokenHasExpired(): bool
    {
        return $this->token_expires_at !== null
            && $this->token_expires_at->isPast();
    }

    /**
     * Tokens that run out in the next fortnight, so the admin can warn before
     * posting quietly stops working.
     */
    public function tokenExpiresSoon(): bool
    {
        return $this->token_expires_at !== null
            && ! $this->tokenHasExpired()
            && $this->token_expires_at->lt(now()->addDays(14));
    }
}
