<?php

namespace App\Filament\Widgets;

use App\Models\Article;
use App\Models\Comment;
use App\Models\NewsSource;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $published = Article::where('status', 'published')->count();
        $drafts    = Article::where('status', 'draft')->count();
        $views     = (int) Article::sum('views');
        $pending   = Comment::where('is_approved', false)->count();
        $sources   = NewsSource::where('is_active', true)->count();

        return [
            Stat::make('Published articles', number_format($published))
                ->description($drafts . ' drafts waiting')
                ->descriptionIcon('heroicon-m-document-text')
                ->chart($this->last7Days())
                ->color('success'),

            Stat::make('Total views', number_format($views))
                ->description('Across all articles')
                ->descriptionIcon('heroicon-m-eye')
                ->color('info'),

            Stat::make('Pending comments', number_format($pending))
                ->description($pending > 0 ? 'Needs moderation' : 'All caught up')
                ->descriptionIcon($pending > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle')
                ->color($pending > 0 ? 'warning' : 'gray'),

            Stat::make('Active sources', number_format($sources))
                ->description('Feeds being scraped')
                ->descriptionIcon('heroicon-m-rss')
                ->color('primary'),
        ];
    }

    /**
     * Articles published per day for the last 7 days (sparkline).
     *
     * @return list<int>
     */
    private function last7Days(): array
    {
        return collect(range(6, 0))
            ->map(fn (int $daysAgo) => Article::where('status', 'published')
                ->whereDate('published_at', now()->subDays($daysAgo)->toDateString())
                ->count())
            ->all();
    }
}
