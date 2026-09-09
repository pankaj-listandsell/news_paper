<?php

namespace App\Social;

use App\Models\Article;
use Illuminate\Support\Str;

/**
 * The text that goes out with an article, trimmed to fit the platform.
 *
 * The link carries the article's Open Graph tags, so every platform renders
 * its own preview card with the headline and image — the message itself only
 * has to add the words around it.
 */
class SocialMessage
{
    public function __construct(
        public readonly string $text,
        public readonly string $url,
    ) {
    }

    public static function for(Article $article, string $platform): self
    {
        $url = route('article.show', $article);

        return new self(
            self::compose($article, $platform, $url),
            $url,
        );
    }

    private static function compose(Article $article, string $platform, string $url): string
    {
        $limit    = self::limitFor($platform);
        $hashtags = self::hashtags($article, $platform);

        // The platform counts the link against the limit, so reserve room for
        // it (and the tags) before deciding how much headline fits.
        $reserved = mb_strlen($url) + mb_strlen($hashtags) + 4;
        $room     = max(40, $limit - $reserved);

        $body = trim($article->title);

        // Only the roomier platforms get the standfirst as well.
        if ($limit > 400 && filled($article->excerpt)) {
            $withExcerpt = $body . "\n\n" . trim(strip_tags($article->excerpt));

            if (mb_strlen($withExcerpt) <= $room) {
                $body = $withExcerpt;
            }
        }

        if (mb_strlen($body) > $room) {
            $body = Str::limit($body, $room - 1, '…');
        }

        return trim($body . "\n\n" . $url . ($hashtags === '' ? '' : "\n" . $hashtags));
    }

    /**
     * A couple of the article's own tags, as hashtags. More than that reads
     * as spam on every one of these platforms.
     */
    private static function hashtags(Article $article, string $platform): string
    {
        if (! $article->relationLoaded('tags') && ! $article->exists) {
            return '';
        }

        $tags = $article->tags()->pluck('name')
            ->take(self::limitFor($platform) > 400 ? 3 : 2)
            ->map(fn (string $name) => '#' . Str::of($name)
                ->ascii()
                ->replaceMatches('/[^A-Za-z0-9]+/', '')
                ->toString())
            ->filter(fn (string $tag) => mb_strlen($tag) > 1);

        return $tags->implode(' ');
    }

    /**
     * Character budget per platform. X is the tight one; the others are
     * generous enough that the real limit is the reader's patience.
     */
    private static function limitFor(string $platform): int
    {
        return match ($platform) {
            'x'        => 280,
            'linkedin' => 1200,
            default    => 1500,
        };
    }
}
