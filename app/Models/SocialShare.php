<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The record of one article's trip to one platform.
 *
 * There is at most one row per article per platform (enforced by a unique
 * index), which is what stops the same story going out twice.
 */
class SocialShare extends Model
{
    public const PENDING = 'pending';
    public const SENT    = 'sent';
    public const FAILED  = 'failed';
    public const SKIPPED = 'skipped';

    protected $fillable = [
        'article_id', 'platform', 'status', 'remote_id',
        'remote_url', 'error', 'attempts', 'posted_at',
    ];

    protected $casts = [
        'posted_at' => 'datetime',
        'attempts'  => 'integer',
    ];

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    public function scopeSent(Builder $query): Builder
    {
        return $query->where('status', self::SENT);
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', self::FAILED);
    }

    public function wasSent(): bool
    {
        return $this->status === self::SENT;
    }
}
