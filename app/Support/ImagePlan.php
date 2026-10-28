<?php

namespace App\Support;

use App\Models\Article;
use Illuminate\Support\Carbon;

/**
 * Decides whether an article gets the publisher's own photograph or one drawn
 * by the AI.
 *
 * The two take turns — the day's first post keeps its photo, the second is
 * drawn, and so on — so the front page carries a mix rather than switching
 * look halfway through the day. An article the feed gave no picture for takes
 * a drawn one out of turn, since the alternative is no image at all.
 *
 * Drawn images are also capped per day. Each one is an API call that costs
 * money, and this is where that bill is held down.
 */
class ImagePlan
{
    /** Where the two kinds of image live on the public disk. */
    public const AI_DIR = 'articles/ai/';

    public const SOURCE_DIR = 'articles/source/';

    private int $drawnThisRun = 0;

    private readonly int $limit;

    private readonly int $drawnBefore;

    public function __construct(?int $limit = null, ?Carbon $at = null)
    {
        $this->limit       = $limit ?? SiteSettings::dailyAiImageLimit();
        $this->drawnBefore = self::drawnToday($at);
    }

    /**
     * AI images made today. Counted from what is actually on the articles, so
     * a generation that failed and fell back to the photo does not spend a
     * slot it never used.
     */
    public static function drawnToday(?Carbon $at = null): int
    {
        $berlin = ($at ?? now())->copy()->timezone('Europe/Berlin');

        return Article::query()
            ->where('featured_image', 'like', self::AI_DIR . '%')
            ->whereBetween('created_at', [
                $berlin->copy()->startOfDay()->utc(),
                $berlin->copy()->endOfDay()->utc(),
            ])
            ->count();
    }

    /**
     * Should this article get a drawn image?
     *
     * @param  int   $postNumber    which post of the day this will be, from 1
     * @param  bool  $hasOwnPicture whether the feed supplied a usable photo
     */
    public function wantsAi(int $postNumber, bool $hasOwnPicture): bool
    {
        if ($this->remaining() === 0) {
            return false;
        }

        // Nothing to fall back on, so draw one whatever the turn order says.
        if (! $hasOwnPicture) {
            return true;
        }

        // Even-numbered posts are the drawn ones, which halves the day.
        return $postNumber % 2 === 0;
    }

    public function remaining(): int
    {
        return max(0, $this->limit - $this->drawnBefore - $this->drawnThisRun);
    }

    /**
     * Called once an image has actually been drawn and kept.
     */
    public function drew(): void
    {
        $this->drawnThisRun++;
    }

    public function limit(): int
    {
        return $this->limit;
    }

    public function drawnBefore(): int
    {
        return $this->drawnBefore;
    }

    public function drawnThisRun(): int
    {
        return $this->drawnThisRun;
    }
}
