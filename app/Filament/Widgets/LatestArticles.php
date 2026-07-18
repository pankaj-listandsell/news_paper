<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\ArticleResource;
use App\Models\Article;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class LatestArticles extends BaseWidget
{
    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Latest articles';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Article::query()->with(['category'])->latest('created_at')->limit(8)
            )
            ->paginated(false)
            ->columns([
                Tables\Columns\ImageColumn::make('featured_image')
                    ->disk('public')
                    ->label('')
                    ->square()
                    ->defaultImageUrl(fn (Article $r) => $r->image_url),
                Tables\Columns\TextColumn::make('title')
                    ->limit(48)
                    ->weight('semibold')
                    ->description(fn (Article $r) => $r->source_name ?: $r->category?->name),
                Tables\Columns\TextColumn::make('category.name')
                    ->badge()
                    ->label('Category')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'published' => 'success',
                        'pending'   => 'warning',
                        default     => 'gray',
                    }),
                Tables\Columns\TextColumn::make('views')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('published_at')
                    ->dateTime('d M, H:i')
                    ->label('Published')
                    ->placeholder('—'),
            ])
            ->actions([
                Tables\Actions\Action::make('edit')
                    ->url(fn (Article $record) => ArticleResource::getUrl('edit', ['record' => $record]))
                    ->icon('heroicon-m-pencil-square')
                    ->label('Edit'),
            ]);
    }
}
