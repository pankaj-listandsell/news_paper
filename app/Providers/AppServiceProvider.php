<?php

namespace App\Providers;

use App\Models\Article;
use App\Models\Category;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Share navigation + breaking news with the public layout.
        View::composer('layouts.app', function ($view) {
            $view->with('navCategories', Category::where('is_active', true)
                ->orderBy('sort_order')
                ->get());

            $view->with('breakingNews', Article::published()
                ->where('is_breaking', true)
                ->latest('published_at')
                ->take(6)
                ->get(['title', 'slug']));
        });
    }
}
