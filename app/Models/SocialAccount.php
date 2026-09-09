<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One connected social platform the site can post to.
 *
 * The credentials column holds whatever that platform needs — a page token
 * and page id for Facebook, an organisation urn for LinkedIn, OAuth tokens
 * for X. It is cast to an encrypted array, so it is never at rest in the
 * clear, and is read through credential() so a key that was never stored
 * simply comes back null.
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
        'credentials'      => 'encrypted:array',
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
     * Everything stored for this account.
     *
     * Deliberately not named credentials(): a method whose name matches a
     * column makes Eloquent treat that column as a relationship.
     *
     * @return array<string, mixed>
     */
    public function credentialBag(): array
    {
        try {
            return $this->credentials ?? [];
        } catch (\Throwable) {
            // A rotated APP_KEY leaves the stored ciphertext unreadable. Read
            // that as "not connected" rather than taking the admin panel down.
            return [];
        }
    }

    public function credential(string $key, $default = null)
    {
        return $this->credentialBag()[$key] ?? $default;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function setCredentials(array $values): void
    {
        // Blank values would otherwise store an empty token and make the
        // account look connected when it is not.
        $values = array_filter($values, fn ($v) => filled($v));

        $this->credentials = $values === [] ? null : $values;
    }

    /**
     * Ready to post: switched on, and the token has not run out.
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
