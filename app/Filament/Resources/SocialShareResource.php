<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SocialShareResource\Pages;
use App\Models\SocialShare;
use App\Social\SocialSharer;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * The record of everything that has been sent to a platform, or tried to be.
 *
 * Read-only by design: rows are written by the sharer, not by hand. What the
 * admin can do here is retry something that failed, or mark an article as one
 * that should never be posted.
 */
class SocialShareResource extends Resource
{
    protected static ?string $model = SocialShare::class;

    protected static ?string $navigationIcon = 'heroicon-o-paper-airplane';

    protected static ?string $navigationGroup = 'Content';

    protected static ?string $navigationLabel = 'Social Posts';

    protected static ?string $modelLabel = 'social post';

    protected static ?int $navigationSort = 5;

    /** The number worth chasing: posts that gave up. */
    public static function getNavigationBadge(): ?string
    {
        return (string) static::getModel()::failed()->count() ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('article'))
            ->defaultSort('updated_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('article.title')
                    ->label('Article')
                    ->searchable()
                    ->limit(45)
                    ->url(fn (SocialShare $r): ?string => $r->article
                        ? ArticleResource::getUrl('edit', ['record' => $r->article])
                        : null),
                Tables\Columns\TextColumn::make('platform')
                    ->badge()
                    ->color('gray')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        SocialShare::SENT    => 'success',
                        SocialShare::FAILED  => 'danger',
                        SocialShare::SKIPPED => 'gray',
                        default              => 'warning',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('error')
                    ->label('Last problem')
                    ->limit(60)
                    ->tooltip(fn (SocialShare $r): ?string => $r->error)
                    ->placeholder('—')
                    ->wrap(),
                Tables\Columns\TextColumn::make('attempts')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('posted_at')
                    ->label('Posted')
                    ->dateTime('d M Y H:i')
                    ->placeholder('—')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('platform')
                    ->options(\App\Models\SocialAccount::PLATFORMS),
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        SocialShare::SENT    => 'Sent',
                        SocialShare::PENDING => 'Waiting',
                        SocialShare::FAILED  => 'Failed',
                        SocialShare::SKIPPED => 'Never post',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('open')
                    ->label('View post')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('gray')
                    ->url(fn (SocialShare $r): ?string => $r->remote_url)
                    ->openUrlInNewTab()
                    ->visible(fn (SocialShare $r): bool => filled($r->remote_url)),

                Tables\Actions\Action::make('retry')
                    ->label('Try again')
                    ->icon('heroicon-o-arrow-path')
                    ->requiresConfirmation()
                    ->modalDescription('This clears the attempt count and posts again right now.')
                    // Nothing that already went out may be sent a second time.
                    ->visible(fn (SocialShare $r): bool => ! $r->wasSent())
                    ->action(function (SocialShare $record): void {
                        static::retry($record);
                    }),

                Tables\Actions\Action::make('skip')
                    ->label('Never post')
                    ->icon('heroicon-o-no-symbol')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalDescription('This article will be left out of every future run for this platform.')
                    ->visible(fn (SocialShare $r): bool => ! $r->wasSent() && $r->status !== SocialShare::SKIPPED)
                    ->action(fn (SocialShare $record) => $record->update([
                        'status' => SocialShare::SKIPPED,
                        'error'  => null,
                    ])),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('retryAll')
                    ->label('Try again')
                    ->icon('heroicon-o-arrow-path')
                    ->requiresConfirmation()
                    ->action(function (Collection $records): void {
                        $records->reject->wasSent()->each(fn (SocialShare $r) => static::retry($r, notify: false));

                        Notification::make()->title('Retried')->success()->send();
                    }),
                Tables\Actions\BulkAction::make('skipAll')
                    ->label('Never post')
                    ->icon('heroicon-o-no-symbol')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalDescription('Use this on the back catalogue so a scheduled run never picks it up.')
                    ->action(fn (Collection $records) => $records->reject->wasSent()
                        ->each(fn (SocialShare $r) => $r->update([
                            'status' => SocialShare::SKIPPED,
                            'error'  => null,
                        ]))),
            ])
            ->emptyStateHeading('Nothing has been posted yet');
    }

    /**
     * Clear the attempt count and post again straight away.
     */
    private static function retry(SocialShare $share, bool $notify = true): void
    {
        $share->update(['status' => SocialShare::PENDING, 'attempts' => 0, 'error' => null]);

        if (! $share->article) {
            return;
        }

        $result = (new SocialSharer())->share($share->article, $share->platform);

        if (! $notify) {
            return;
        }

        $result->wasSent()
            ? Notification::make()->title('Posted')->success()->send()
            : Notification::make()->title('Still not posted')->body($result->error)->danger()->send();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSocialShares::route('/'),
        ];
    }
}
