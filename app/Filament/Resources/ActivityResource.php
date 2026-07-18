<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ActivityResource\Pages;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Spatie\Activitylog\Models\Activity;

class ActivityResource extends Resource
{
    protected static ?string $model = Activity::class;

    protected static ?string $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationGroup = 'System';

    protected static ?string $navigationLabel = 'Activity log';

    protected static ?int $navigationSort = 5;

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('d M Y, H:i')
                    ->label('When')
                    ->sortable(),
                Tables\Columns\TextColumn::make('causer.name')
                    ->label('By')
                    ->default('System')
                    ->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('description')
                    ->label('Action')
                    ->badge()
                    ->color(fn (string $state): string => match (true) {
                        str_contains($state, 'created') => 'success',
                        str_contains($state, 'deleted') => 'danger',
                        default                          => 'info',
                    }),
                Tables\Columns\TextColumn::make('subject_type')
                    ->label('Item')
                    ->formatStateUsing(fn (?string $state) => class_basename($state ?? ''))
                    ->description(fn (Activity $r) => data_get($r->properties, 'attributes.title')
                        ?? data_get($r->properties, 'attributes.name')),
                Tables\Columns\TextColumn::make('changed')
                    ->label('Changed fields')
                    ->state(fn (Activity $r) => collect(data_get($r->properties, 'attributes', []))
                        ->keys()
                        ->implode(', '))
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('subject_type')
                    ->label('Item type')
                    ->options([
                        \App\Models\Article::class   => 'Article',
                        \App\Models\NewsSource::class => 'News source',
                    ]),
            ])
            ->actions([
                //
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListActivities::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
