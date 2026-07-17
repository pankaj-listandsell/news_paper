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

        $related = Article::published()
            ->where('category_id', $article->category_id)
            ->whereKeyNot($article->id)
            ->latest('published_at')
            ->take(4)
            ->get();

        return view('news.show', compact('article', 'related'));
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
