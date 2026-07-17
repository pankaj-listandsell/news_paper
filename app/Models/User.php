<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        // Author profile
        'avatar',
        'designation',
        'bio',
        'website',
        'twitter',
        'linkedin',
        'show_on_frontend',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'show_on_frontend' => 'boolean',
        ];
    }

    /**
     * Author photo, or an initials-based placeholder when none is uploaded.
     */
    public function getAvatarUrlAttribute(): string
    {
        if ($this->avatar) {
            return \Illuminate\Support\Facades\Storage::disk('public')->url($this->avatar);
        }

        return 'https://placehold.co/200x200/1f2937/ffffff?text=' . urlencode(mb_substr($this->name, 0, 1));
    }

    /**
     * Social/profile links that are actually filled in.
     *
     * @return array<string, string> label => url
     */
    public function getProfileLinksAttribute(): array
    {
        return array_filter([
            'Website'  => $this->website,
            'X'        => $this->twitter,
            'LinkedIn' => $this->linkedin,
        ], fn ($url) => filled($url));
    }

    /**
     * Articles authored by this user.
     */
    public function articles(): HasMany
    {
        return $this->hasMany(Article::class);
    }

    /**
     * Only users with a role may access the admin panel.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->hasAnyRole(['admin', 'editor', 'author']);
    }
}
