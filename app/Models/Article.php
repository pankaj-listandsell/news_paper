<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Article extends Model
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['title', 'status', 'http_status', 'redirect_url', 'category_id', 'is_featured', 'is_breaking'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $event) => "Article {$event}");
    }

    protected $fillable = [
        'title', 'slug', 'subtitle', 'excerpt', 'body', 'featured_image',
        'category_id', 'user_id', 'status', 'is_featured', 'is_breaking',
        'http_status', 'redirect_url',
        'views', 'meta_title', 'meta_description', 'published_at',
        'source_id', 'source_name', 'source_url',
    ];

    protected $casts = [
        'is_featured'  => 'boolean',
        'is_breaking'  => 'boolean',
        'http_status'  => 'integer',
        'published_at' => 'datetime',
    ];

    /**
     * When an article is deleted, remove its uploaded media from disk too
     * (featured image + images embedded in the body). External/scraped
     * URLs are left untouched — those files are not ours.
     */
    protected static function booted(): void
    {
        static::deleting(function (Article $article) {
            $article->deleteLocalMedia();
        });
    }

    public function deleteLocalMedia(): void
    {
        $disk = Storage::disk('public');
        $paths = [];

        // Featured image (only if it's a local disk path, not an external URL)
        if ($this->featured_image && ! $this->isExternalUrl($this->featured_image)) {
            $paths[] = $this->featured_image;
        }

        // Images embedded in the body that live on our public disk
        if ($this->body && preg_match_all('/src=["\']([^"\']+)["\']/i', $this->body, $m)) {
            foreach ($m[1] as $src) {
                if (str_contains($src, '/storage/')) {
                    // strip everything up to and including "/storage/"
                    $paths[] = ltrim(substr($src, strpos($src, '/storage/') + strlen('/storage/')), '/');
                }
            }
        }

        foreach (array_unique($paths) as $path) {
            if ($path !== '' && $disk->exists($path)) {
                $disk->delete($path);
            }
        }
    }

    private function isExternalUrl(string $value): bool
    {
        return str_starts_with($value, 'http://') || str_starts_with($value, 'https://');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(NewsSource::class, 'source_id');
    }

    /**
     * Bump the view counter without touching updated_at.
     *
     * Eloquent's increment() stamps updated_at as a matter of course, which
     * would make every single page view look like an edit — to the sitemap's
     * <lastmod>, and to the conditional-GET check on the detail page. A read
     * is not a write.
     */
    public function recordView(): void
    {
        static::withoutTimestamps(fn () => $this->increment('views'));
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published')
                     ->where('published_at', '<=', now())
                     // A 301 article is not a page any more, just a signpost, so
                     // it stays out of every listing, the sitemap and the RSS
                     // feed. A frozen (304) one still renders normally and is
                     // listed like any other.
                     ->where('http_status', '!=', 301);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function getImageUrlAttribute(): string
    {
        if (! $this->featured_image) {
            return 'https://placehold.co/800x450/1f2937/ffffff?text=' . urlencode($this->category?->name ?? 'News');
        }

        // Scraped articles store an external URL; local uploads store a disk path.
        if (str_starts_with($this->featured_image, 'http://') || str_starts_with($this->featured_image, 'https://')) {
            return $this->featured_image;
        }

        return \Illuminate\Support\Facades\Storage::disk('public')->url($this->featured_image);
    }

    public function getBylineAttribute(): string
    {
        // Only ever our own author — the original publisher is never shown
        // on the public site.
        return $this->author?->name ?: 'Redaktion';
    }

    public function getReadingTimeAttribute(): int
    {
        $words = str_word_count(strip_tags($this->body));

        return max(1, (int) ceil($words / 200));
    }
}
