<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Category;
use App\Models\Tag;
use App\Support\SiteSettings;
use Illuminate\Http\Response;

class FeedController extends Controller
{
    /**
     * XML sitemap for search engines.
     */
    public function sitemap(): Response
    {
        $articles = Article::published()
            ->latest('published_at')
            ->take(1000)
            ->get(['slug', 'updated_at']);

        $categories = Category::where('is_active', true)->get(['slug', 'updated_at']);
        $tags       = Tag::has('articles')->get(['slug', 'updated_at']);

        // Static pages, but only the ones that actually have content.
        $pages = collect(NewsController::PAGES)
            ->filter(fn (array $page) => filled(strip_tags(SiteSettings::get($page['key']))))
            ->keys()
            ->all();

        return response()
            ->view('feeds.sitemap', compact('articles', 'categories', 'tags', 'pages'))
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    /**
     * RSS 2.0 feed of the latest articles.
     */
    public function rss(): Response
    {
        $articles = Article::published()
            ->with('category')
            ->latest('published_at')
            ->take(30)
            ->get();

        return response()
            ->view('feeds.rss', [
                'articles' => $articles,
                'siteName' => SiteSettings::name(),
                'siteDesc' => SiteSettings::get('site_description'),
            ])
            ->header('Content-Type', 'application/rss+xml; charset=UTF-8');
    }

    /**
     * robots.txt pointing crawlers at the sitemap.
     */
    public function robots(): Response
    {
        $lines = [
            'User-agent: *',
            'Disallow: /admin',
            'Disallow: /suche',
            // The old English path still 301s to /suche — keep crawlers off both.
            'Disallow: /search',
            '',
            'Sitemap: ' . route('sitemap'),
        ];

        return response(implode("\n", $lines))
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}
