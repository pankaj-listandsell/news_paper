<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ContactMessageResource\Pages;
use App\Models\ContactMessage;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class ContactMessageResource extends Resource
{
    protected static ?string $model = ContactMessage::class;

    protected static ?string $navigationIcon = 'heroicon-o-envelope';

    protected static ?string $navigationGroup = 'Engagement';

    protected static ?string $navigationLabel = 'Contact';

    protected static ?string $modelLabel = 'contact message';

    protected static ?int $navigationSort = 2;

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getModel()::where('is_read', false)->count() ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    /**
     * Messages arrive through the public form — nothing is created here.
     */
    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make()->schema([
                Infolists\Components\TextEntry::make('name')->label('From'),
                Infolists\Components\TextEntry::make('email')
                    ->label('E-mail')
                    ->copyable()
                    ->url(fn (ContactMessage $record) => 'mailto:' . $record->email),
                Infolists\Components\TextEntry::make('subject'),
                Infolists\Components\TextEntry::make('created_at')->label('Received')->dateTime('d M Y, H:i'),
                Infolists\Components\TextEntry::make('message')
                    ->prose()
                    ->columnSpanFull(),
                Infolists\Components\TextEntry::make('ip_address')->label('IP')->placeholder('—'),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->columns([
                Tables\Columns\IconColumn::make('is_read')
                    ->label('')
                    ->boolean()
                    ->trueIcon('heroicon-o-envelope-open')
                    ->falseIcon('heroicon-s-envelope')
                    ->trueColor('gray')
                    ->falseColor('warning')
                    ->tooltip(fn (ContactMessage $r) => $r->is_read ? 'Read' : 'Unread'),
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->weight(fn (ContactMessage $r) => $r->is_read ? null : 'bold'),
                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->copyable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('subject')
                    ->searchable()
                    ->limit(40)
                    ->weight(fn (ContactMessage $r) => $r->is_read ? null : 'bold'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Received')
                    ->dateTime('d M Y, H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_read')
                    ->label('Read')
                    ->placeholder('All messages')
                    ->trueLabel('Read only')
                    ->falseLabel('Unread only'),
            ])
            ->actions([
                // Opening a message marks it as read.
                Tables\Actions\ViewAction::make()
                    ->after(fn (ContactMessage $record) => $record->is_read ?: $record->update(['is_read' => true])),
                Tables\Actions\Action::make('reply')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('success')
                    ->url(fn (ContactMessage $r) => 'mailto:' . $r->email . '?subject=' . rawurlencode('Re: ' . $r->subject))
                    ->openUrlInNewTab(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('markRead')
                        ->label('Mark as read')
                        ->icon('heroicon-o-envelope-open')
                        ->deselectRecordsAfterCompletion()
                        ->action(fn (Collection $records) => $records->each->update(['is_read' => true])),
                    Tables\Actions\BulkAction::make('markUnread')
                        ->label('Mark as unread')
                        ->icon('heroicon-s-envelope')
                        ->color('gray')
                        ->deselectRecordsAfterCompletion()
                        ->action(fn (Collection $records) => $records->each->update(['is_read' => false])),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListContactMessages::route('/'),
        ];
    }
}
