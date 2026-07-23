<?php

use App\Http\Controllers\CommentController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\FeedController;
use App\Http\Controllers\NewsController;
use App\Http\Controllers\SubscriberController;
use Illuminate\Support\Facades\Route;

Route::get('/', [NewsController::class, 'home'])->name('home');

// SEO / syndication — these paths are standards, so they stay English.
Route::get('/sitemap.xml', [FeedController::class, 'sitemap'])->name('sitemap');
Route::get('/feed', [FeedController::class, 'rss'])->name('rss');
Route::get('/robots.txt', [FeedController::class, 'robots'])->name('robots');

// Contact form — declared before the /{page} catch-all.
Route::get('/kontakt', [ContactController::class, 'show'])->name('contact');
// Rate limited: a person sends a couple of messages, a bot sends hundreds.
Route::post('/kontakt', [ContactController::class, 'send'])
    ->middleware('throttle:5,60')
    ->name('contact.send');

// Static pages (content managed in admin → General Settings)
Route::get('/{page}', [NewsController::class, 'page'])
    ->whereIn('page', array_keys(NewsController::PAGES))
    ->name('page');

/*
 * Public URLs are German — the route names stay the same, so every
 * route() call in the views keeps working.
 */
Route::get('/suche', [NewsController::class, 'search'])->name('search');

Route::get('/kategorie/{category:slug}', [NewsController::class, 'category'])->name('category.show');
Route::get('/thema/{tag:slug}', [NewsController::class, 'tag'])->name('tag.show');
Route::get('/autor/{user}', [NewsController::class, 'author'])->name('author.show');

Route::get('/nachrichten/{article:slug}', [NewsController::class, 'show'])->name('article.show');
// Rate limited: a person posts a handful of comments an hour, a bot posts hundreds.
Route::post('/nachrichten/{article:slug}/kommentare', [CommentController::class, 'store'])
    ->middleware('throttle:5,60')
    ->name('comments.store');

Route::post('/newsletter', [SubscriberController::class, 'store'])
    ->middleware('throttle:5,60')
    ->name('subscribe');

/*
 * Permanent redirects from the old English URLs, so existing links and
 * anything already indexed keeps working.
 */
Route::permanentRedirect('/search', '/suche');
Route::get('/category/{slug}', fn (string $slug) => redirect('/kategorie/' . $slug, 301));
Route::get('/tag/{slug}', fn (string $slug) => redirect('/thema/' . $slug, 301));
Route::get('/author/{user}', fn (string $user) => redirect('/autor/' . $user, 301));
Route::get('/news/{slug}', fn (string $slug) => redirect('/nachrichten/' . $slug, 301));
