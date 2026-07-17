<?php

namespace App\Filament\Widgets;

use App\Models\Article;
use Filament\Widgets\ChartWidget;

class ArticlesChart extends ChartWidget
{
    protected static ?string $heading = 'Articles published';

    protected static ?int $sort = 2;

    protected static ?string $maxHeight = '260px';

    public ?string $filter = '30';

    protected function getFilters(): ?array
    {
        return [
            '7'  => 'Last 7 days',
            '30' => 'Last 30 days',
            '90' => 'Last 90 days',
        ];
    }

    protected function getData(): array
    {
        $days   = (int) ($this->filter ?? 30);
        $labels = [];
        $counts = [];

        foreach (range($days - 1, 0) as $daysAgo) {
            $date     = now()->subDays($daysAgo);
            $labels[] = $date->format($days > 30 ? 'd M' : 'd M');
            $counts[] = Article::where('status', 'published')
                ->whereDate('published_at', $date->toDateString())
                ->count();
        }

        return [
            'datasets' => [[
                'label'           => 'Published',
                'data'            => $counts,
                'borderColor'     => '#ef4444',
                'backgroundColor' => 'rgba(239, 68, 68, 0.12)',
                'fill'            => true,
                'tension'         => 0.35,
                'pointRadius'     => 0,
                'pointHoverRadius' => 4,
            ]],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => ['display' => false],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks'       => ['precision' => 0],
                ],
            ],
        ];
    }
}
