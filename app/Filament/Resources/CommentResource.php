<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CommentResource\Pages;
use App\Filament\Resources\CommentResource\RelationManagers;
use App\Models\Comment;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

class CommentResource extends Resource
{
    protected static ?string $model = Comment::class;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $navigationGroup = 'Engagement';

    protected static ?int $navigationSort = 1;

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getModel()::where('is_approved', false)->count() ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    /**
     * Eager-load the article so the list is a couple of queries, not one per row.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('article');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('article_id')
                    ->relationship('article', 'title')
                    ->searchable()
                    ->required(),
                Forms\Components\TextInput::make('author_name')
                    ->required(),
                Forms\Components\TextInput::make('author_email')
                    ->email()
                    ->required(),
                Forms\Components\Textarea::make('body')
                    ->required()
                    ->columnSpanFull(),
                Forms\Components\Toggle::make('is_approved'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->columns([
                Tables\Columns\TextColumn::make('author_name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('body')
                    ->limit(50)
                    ->wrap(),
                Tables\Columns\TextColumn::make('article.title')
                    ->limit(30)
                    ->toggleable(),
                Tables\Columns\IconColumn::make('is_approved')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_approved'),
                Tables\Filters\Filter::make('created_at')
                    ->label('Date')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label('From')
                            ->native(false),
                        Forms\Components\DatePicker::make('until')
                            ->label('Until')
                            ->native(false),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '<=', $date)))
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if ($data['from'] ?? null) {
                            $indicators[] = 'From ' . Carbon::parse($data['from'])->format('d M Y');
                        }

                        if ($data['until'] ?? null) {
                            $indicators[] = 'Until ' . Carbon::parse($data['until'])->format('d M Y');
                        }

                        return $indicators;
                    }),
            ])
            ->filtersFormColumns(2)
            ->actions([
                Tables\Actions\Action::make('approve')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (Comment $r) => ! $r->is_approved)
                    ->action(fn (Comment $r) => $r->update(['is_approved' => true])),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('approve')
                        ->label('Approve')
                        ->icon('heroicon-o-check')
                        ->color('success')
                        ->deselectRecordsAfterCompletion()
                        ->action(fn (Collection $records) => $records->each->update(['is_approved' => true])),
                    Tables\Actions\BulkAction::make('unapprove')
                        ->label('Unapprove')
                        ->icon('heroicon-o-x-mark')
                        ->color('gray')
                        ->deselectRecordsAfterCompletion()
                        ->action(fn (Collection $records) => $records->each->update(['is_approved' => false])),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListComments::route('/'),
            'create' => Pages\CreateComment::route('/create'),
            'edit' => Pages\EditComment::route('/{record}/edit'),
        ];
    }
}
