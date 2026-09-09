<?php

namespace App\Social;

use App\Models\Article;

/**
 * What one run actually did, in a shape both the console and a notification
 * can read.
 */
class SocialRunReport
{
    /** @var array<int, array{platform:string, title:string}> */
    public array $posted = [];

    /** @var array<int, array{platform:string, title:string}> */
    public array $planned = [];

    /** @var array<int, array{platform:string, title:string, reason:string}> */
    public array $blocked = [];

    public function sent(string $platform, Article $article): void
    {
        $this->posted[] = ['platform' => $platform, 'title' => $article->title];
    }

    public function wouldPost(string $platform, Article $article): void
    {
        $this->planned[] = ['platform' => $platform, 'title' => $article->title];
    }

    public function held(string $platform, Article $article, ?string $reason): void
    {
        $this->blocked[] = [
            'platform' => $platform,
            'title'    => $article->title,
            'reason'   => $reason ?: 'Unknown reason.',
        ];
    }

    public function sentCount(): int
    {
        return count($this->posted);
    }

    public function heldCount(): int
    {
        return count($this->blocked);
    }

    public function plannedCount(): int
    {
        return count($this->planned);
    }

    public function didNothing(): bool
    {
        return $this->posted === [] && $this->blocked === [] && $this->planned === [];
    }

    /**
     * A short summary of what went wrong, for a notification body.
     */
    public function problems(int $limit = 5): string
    {
        return collect($this->blocked)
            ->take($limit)
            ->map(fn (array $row) => "{$row['platform']}: {$row['reason']}")
            ->unique()
            ->implode("\n");
    }
}
