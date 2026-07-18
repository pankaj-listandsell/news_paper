<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Category;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Http\Request;

class NewsController extends Controller
{
    public function home()
    {
        $featured = Article::published()->with('category', 'author')
            ->where('is_featured', true)
            ->latest('published_at')
            ->take(5)
            ->get();

        $latest = Article::published()->with('category', 'author')
            ->latest('published_at')
            ->take(12)
            ->get();

        $mostViewed = Article::published()->with('category')
            ->orderByDesc('views')
            ->take(6)
            ->get();

        // Group latest articles per category for section blocks
        $categoryBlocks = Category::where('is_active', true)
            ->orderBy('sort_order')
            ->with(['articles' => fn ($q) => $q->published()->latest('published_at')->take(4)])
            ->get()
            ->filter(fn ($c) => $c->articles->isNotEmpty());

        return view('news.home', compact('featured', 'latest', 'mostViewed', 'categoryBlocks'));
    }

    public function show(Article $article)
    {
        abort_unless($article->status === 'published' && $article->published_at <= now(), 404);

        $article->increment('views');
        $article->load(['category', 'author', 'tags', 'comments' => fn ($q) => $q->approved()->latest()]);

        return view('news.show', [
            'article' => $article,
            'related' => $this->relatedArticles($article),
        ]);
    }

    /**
     * Articles sharing the most tags come first; if that doesn't fill the
     * list, top it up with recent articles from the same category.
     */
    private function relatedArticles(Article $article, int $limit = 4)
    {
        $tagIds = $article->tags->pluck('id');

        $byTags = $tagIds->isEmpty()
            ? collect()
            : Article::published()
                ->whereKeyNot($article->id)
                ->withCount(['tags' => fn ($q) => $q->whereIn('tags.id', $tagIds)])
                ->whereHas('tags', fn ($q) => $q->whereIn('tags.id', $tagIds))
                ->orderByDesc('tags_count')
                ->latest('published_at')
                ->take($limit)
                ->get();

        if ($byTags->count() >= $limit) {
            return $byTags;
        }

        // Not enough tag matches — fill the rest from the same category.
        $fill = Article::published()
            ->where('category_id', $article->category_id)
            ->whereKeyNot($article->id)
            ->whereNotIn('id', $byTags->pluck('id'))
            ->latest('published_at')
            ->take($limit - $byTags->count())
            ->get();

        return $byTags->concat($fill);
    }

    public function category(Category $category)
    {
        $articles = $category->articles()->published()->with('author')
            ->latest('published_at')
            ->paginate(12);

        return view('news.category', compact('category', 'articles'));
    }

    public function tag(Tag $tag)
    {
        $articles = $tag->articles()->published()->with('category', 'author')
            ->latest('published_at')
            ->paginate(12);

        return view('news.tag', compact('tag', 'articles'));
    }

    public function author(User $user)
    {
        abort_unless($user->show_on_frontend, 404);

        $articles = $user->articles()->published()->with('category')
            ->latest('published_at')
            ->paginate(12);

        return view('news.author', ['author' => $user, 'articles' => $articles]);
    }

    /**
     * Static pages (Über uns / Impressum / Datenschutz) — the content is
     * written in admin → General Settings.
     *
     * @var array<string, array{key:string, heading:string}>
     */
    public const PAGES = [
        'ueber-uns'   => ['key' => 'about_content',   'heading' => 'Über uns'],
        'impressum'   => ['key' => 'imprint_content', 'heading' => 'Impressum'],
        'datenschutz' => ['key' => 'privacy_content', 'heading' => 'Datenschutzerklärung'],
    ];

    public function page(string $page)
    {
        abort_unless(isset(self::PAGES[$page]), 404);

        $content = \App\Support\SiteSettings::get(self::PAGES[$page]['key']);

        // Not filled in yet — the page doesn't exist.
        abort_if(blank(strip_tags($content)), 404);

        return view('news.page', [
            'heading' => self::PAGES[$page]['heading'],
            'content' => $content,
        ]);
    }

    public function search(Request $request)
    {
        $q = trim((string) $request->get('q'));

        $articles = Article::published()->with('category', 'author')
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('title', 'like', "%{$q}%")
                        ->orWhere('excerpt', 'like', "%{$q}%")
                        ->orWhere('body', 'like', "%{$q}%");
                });
            })
            ->latest('published_at')
            ->paginate(12)
            ->withQueryString();

        return view('news.search', compact('articles', 'q'));
    }
}
