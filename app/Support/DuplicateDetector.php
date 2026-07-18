<?php

namespace App\Support;

use App\Models\Article;
use Illuminate\Support\Str;

/**
 * Flags articles whose headline closely matches one already imported
 * recently — e.g. the same story picked up from two different sources.
 */
class DuplicateDetector
{
    /** Titles at or above this similarity are treated as the same story. */
    public const THRESHOLD = 0.72;

    /** Only compare against articles from the last N days. */
    private const WINDOW_DAYS = 3;

    /** @var array<int, array{id:int, tokens:array<string>}> */
    private array $recent;

    public function __construct()
    {
        $this->recent = Article::where('created_at', '>=', now()->subDays(self::WINDOW_DAYS))
            ->get(['id', 'title'])
            ->map(fn (Article $a) => ['id' => $a->id, 'tokens' => self::tokens($a->title)])
            ->all();
    }

    /**
     * Is this title a near-duplicate of something already imported?
     * Pass the URL so an update to the very same article isn't a "duplicate".
     */
    public function isDuplicate(string $title, ?string $sourceUrl = null): bool
    {
        $selfId = $sourceUrl
            ? Article::where('source_url', $sourceUrl)->value('id')
            : null;

        $tokens = self::tokens($title);

        if (count($tokens) < 3) {
            return false; // too short to judge
        }

        foreach ($this->recent as $other) {
            if ($selfId && $other['id'] === $selfId) {
                continue;
            }

            if (self::jaccard($tokens, $other['tokens']) >= self::THRESHOLD) {
                return true;
            }
        }

        return false;
    }

    /**
     * Significant words of a headline (lowercase, no punctuation, 4+ chars).
     *
     * @return array<string>
     */
    private static function tokens(string $title): array
    {
        $clean = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', mb_strtolower($title));
        $words = preg_split('/\s+/u', trim((string) $clean)) ?: [];

        return array_values(array_unique(array_filter(
            $words,
            fn ($w) => mb_strlen($w) >= 4
        )));
    }

    /**
     * Word-set overlap (0..1). Language-agnostic and cheap.
     *
     * @param  array<string>  $a
     * @param  array<string>  $b
     */
    private static function jaccard(array $a, array $b): float
    {
        if (empty($a) || empty($b)) {
            return 0.0;
        }

        $intersection = count(array_intersect($a, $b));
        $union        = count(array_unique(array_merge($a, $b)));

        return $union === 0 ? 0.0 : $intersection / $union;
    }
}
