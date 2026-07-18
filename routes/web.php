<?php

use App\Http\Controllers\CommentController;
use App\Http\Controllers\FeedController;
use App\Http\Controllers\NewsController;
use App\Http\Controllers\SubscriberController;
use Illuminate\Support\Facades\Route;

Route::get('/', [NewsController::class, 'home'])->name('home');

// SEO / syndication
Route::get('/sitemap.xml', [FeedController::class, 'sitemap'])->name('sitemap');
Route::get('/feed', [FeedController::class, 'rss'])->name('rss');
Route::get('/robots.txt', [FeedController::class, 'robots'])->name('robots');

// Static pages (content managed in admin → General Settings)
Route::get('/{page}', [NewsController::class, 'page'])
    ->whereIn('page', array_keys(NewsController::PAGES))
    ->name('page');

Route::get('/search', [NewsController::class, 'search'])->name('search');

Route::get('/category/{category:slug}', [NewsController::class, 'category'])->name('category.show');
Route::get('/tag/{tag:slug}', [NewsController::class, 'tag'])->name('tag.show');
Route::get('/author/{user}', [NewsController::class, 'author'])->name('author.show');

Route::get('/news/{article:slug}', [NewsController::class, 'show'])->name('article.show');
// Rate limited: a person posts a handful of comments an hour, a bot posts hundreds.
Route::post('/news/{article:slug}/comments', [CommentController::class, 'store'])
    ->middleware('throttle:5,60')
    ->name('comments.store');

Route::post('/subscribe', [SubscriberController::class, 'store'])
    ->middleware('throttle:5,60')
    ->name('subscribe');
