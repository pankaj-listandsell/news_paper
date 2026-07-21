<?php

namespace App\Providers;

use App\Models\Article;
use App\Models\Category;
use App\Support\SiteSettings;
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
        // Route the mailer through the admin-configured SMTP account. Guarded
        // so a missing settings table (first migrate) can't break booting.
        try {
            SiteSettings::applyMailConfig();
        } catch (\Throwable) {
            // keep the .env mail config as the fallback
        }

        // Share navigation + breaking news + site settings with the public layout.
        View::composer('layouts.app', function ($view) {
            $view->with('site', SiteSettings::all());
            $view->with('siteLogo', SiteSettings::logoUrl());
            $view->with('siteFavicon', SiteSettings::faviconUrl());
            $view->with('siteSocial', SiteSettings::socialLinks());
            $view->with('brand', SiteSettings::brandColors());

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
