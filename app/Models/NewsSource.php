<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class NewsSource extends Model
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'feed_url', 'is_active', 'ai_rewrite', 'ai_image', 'auto_publish'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $event) => "Source {$event}");
    }

    protected $fillable = [
        'name', 'feed_url', 'category_id', 'user_id',
        'is_active', 'auto_publish', 'fetch_full_content',
        'ai_rewrite', 'ai_provider', 'ai_image', 'ai_category', 'max_items',
        'import_new_only', 'last_scraped_at', 'last_error',
    ];

    protected $casts = [
        'is_active'          => 'boolean',
        'auto_publish'       => 'boolean',
        'fetch_full_content' => 'boolean',
        'ai_rewrite'         => 'boolean',
        'ai_image'           => 'boolean',
        'ai_category'        => 'boolean',
        'import_new_only'    => 'boolean',
        'last_scraped_at'    => 'datetime',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class, 'source_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
