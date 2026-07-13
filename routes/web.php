<?php

use App\Http\Controllers\CommentController;
use App\Http\Controllers\NewsController;
use App\Http\Controllers\SubscriberController;
use Illuminate\Support\Facades\Route;

Route::get('/', [NewsController::class, 'home'])->name('home');

Route::get('/search', [NewsController::class, 'search'])->name('search');

Route::get('/category/{category:slug}', [NewsController::class, 'category'])->name('category.show');
Route::get('/tag/{tag:slug}', [NewsController::class, 'tag'])->name('tag.show');
Route::get('/author/{user}', [NewsController::class, 'author'])->name('author.show');

Route::get('/news/{article:slug}', [NewsController::class, 'show'])->name('article.show');
Route::post('/news/{article:slug}/comments', [CommentController::class, 'store'])->name('comments.store');

Route::post('/subscribe', [SubscriberController::class, 'store'])->name('subscribe');
