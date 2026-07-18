<?php

namespace App\Filament\Widgets;

use App\Models\AiUsage;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AiCostOverview extends BaseWidget
{
    protected static ?int $sort = 2;

    protected function getStats(): array
    {
        $today = AiUsage::whereDate('created_at', today())->sum('cost');
        $month = AiUsage::where('created_at', '>=', now()->startOfMonth())->sum('cost');
        $all   = AiUsage::sum('cost');

        $textCost  = AiUsage::where('type', 'text')->sum('cost');
        $imageCost = AiUsage::where('type', 'image')->sum('cost');
        $calls     = AiUsage::count();

        return [
            Stat::make('AI spend today', '$' . number_format($today, 2))
                ->description($this->callsToday() . ' API calls')
                ->descriptionIcon('heroicon-m-sparkles')
                ->chart($this->last7Days())
                ->color($today > 5 ? 'warning' : 'success'),

            Stat::make('This month', '$' . number_format($month, 2))
                ->description('Since ' . now()->startOfMonth()->format('d M'))
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color('info'),

            Stat::make('All time', '$' . number_format($all, 2))
                ->description($calls . ' calls total')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('gray'),

            Stat::make('Text vs image', '$' . number_format($textCost, 2) . ' / $' . number_format($imageCost, 2))
                ->description('Images are usually the bulk of the bill')
                ->descriptionIcon('heroicon-m-photo')
                ->color('primary'),
        ];
    }

    private function callsToday(): int
    {
        return AiUsage::whereDate('created_at', today())->count();
    }

    /**
     * Daily spend (in cents, so the sparkline has usable resolution).
     *
     * @return list<float>
     */
    private function last7Days(): array
    {
        return collect(range(6, 0))
            ->map(fn (int $daysAgo) => round(
                AiUsage::whereDate('created_at', now()->subDays($daysAgo)->toDateString())->sum('cost') * 100,
                2
            ))
            ->all();
    }
}
