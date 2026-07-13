<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Article extends Model
{
    protected $fillable = [
        'title', 'slug', 'subtitle', 'excerpt', 'body', 'featured_image',
        'category_id', 'user_id', 'status', 'is_featured', 'is_breaking',
        'views', 'meta_title', 'meta_description', 'published_at',
    ];

    protected $casts = [
        'is_featured'  => 'boolean',
        'is_breaking'  => 'boolean',
        'published_at' => 'datetime',
    ];

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

    public function scopePublished($query)
    {
        return $query->where('status', 'published')
                     ->where('published_at', '<=', now());
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function getImageUrlAttribute(): string
    {
        return $this->featured_image
            ? \Illuminate\Support\Facades\Storage::disk('public')->url($this->featured_image)
            : 'https://placehold.co/800x450/1f2937/ffffff?text=' . urlencode($this->category?->name ?? 'News');
    }

    public function getReadingTimeAttribute(): int
    {
        $words = str_word_count(strip_tags($this->body));

        return max(1, (int) ceil($words / 200));
    }
}
