<?php

namespace App\Filament\Resources;

use App\Filament\Resources\NewsSourceResource\Pages;
use App\Jobs\ScrapeSourceJob;
use App\Models\NewsSource;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class NewsSourceResource extends Resource
{
    protected static ?string $model = NewsSource::class;

    protected static ?string $navigationIcon = 'heroicon-o-rss';

    protected static ?string $navigationGroup = 'System';

    protected static ?string $navigationLabel = 'News Sources';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Source')->schema([
                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->maxLength(255)
                        ->placeholder('e.g. BBC News — World'),
                    Forms\Components\TextInput::make('feed_url')
                        ->label('RSS feed URL')
                        ->url()
                        ->required()
                        ->maxLength(500)
                        ->placeholder('https://feeds.bbci.co.uk/news/world/rss.xml')
                        ->helperText("Enter the website's RSS/Atom feed URL."),
                ])->columns(1),

                Forms\Components\Section::make('Mapping & behaviour')->schema([
                    Forms\Components\Select::make('category_id')
                        ->label('Assign to category')
                        ->relationship('category', 'name')
                        ->searchable()
                        ->preload()
                        ->helperText('All news from this source goes into this category.'),
                    Forms\Components\Select::make('user_id')
                        ->label('Attribute to author (optional)')
                        ->relationship('author', 'name')
                        ->searchable()
                        ->preload(),
                    Forms\Components\TextInput::make('max_items')
                        ->numeric()
                        ->default(20)
                        ->minValue(1)
                        ->maxValue(100)
                        ->helperText('Maximum items to import per run.'),
                    Forms\Components\Toggle::make('fetch_full_content')
                        ->label('Fetch full article')
                        ->default(false)
                        ->helperText('ON = fetches each article\'s full content from its page (instead of the RSS summary). Slower + mind copyright.'),
                    Forms\Components\Toggle::make('ai_rewrite')
                        ->label('AI rewrite (unique title/description)')
                        ->default(false)
                        ->live()
                        ->helperText('ON = rewrites title + description into unique copy with AI. Add the API key in AI Settings.'),
                    Forms\Components\Select::make('ai_provider')
                        ->label('AI provider for this source')
                        ->options(\App\Support\AiConfig::providerOptions())
                        ->placeholder('Use default (AI Settings)')
                        ->visible(fn (Forms\Get $get) => $get('ai_rewrite'))
                        ->helperText('Empty = use the default provider from AI Settings.'),
                    Forms\Components\Toggle::make('ai_image')
                        ->label('Generate AI image')
                        ->default(false)
                        ->helperText('ON = generates a new AI image per article (OpenAI). Costs money and is an AI illustration, not a real photo.'),
                    Forms\Components\Toggle::make('auto_publish')
                        ->default(true)
                        ->helperText('ON = publish immediately. OFF = save as draft for review before publishing.'),
                    Forms\Components\Toggle::make('is_active')
                        ->default(true)
                        ->helperText('OFF = this source will not be scraped.'),
                ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('category.name')
                    ->badge()
                    ->label('Category'),
                Tables\Columns\TextColumn::make('articles_count')
                    ->counts('articles')
                    ->label('Imported')
                    ->badge()
                    ->color('success'),
                Tables\Columns\IconColumn::make('auto_publish')
                    ->boolean()
                    ->label('Auto'),
                Tables\Columns\IconColumn::make('fetch_full_content')
                    ->boolean()
                    ->label('Full'),
                Tables\Columns\IconColumn::make('ai_rewrite')
                    ->boolean()
                    ->label('AI'),
                Tables\Columns\IconColumn::make('ai_image')
                    ->boolean()
                    ->label('Img'),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),
                Tables\Columns\TextColumn::make('last_scraped_at')
                    ->dateTime('d M H:i')
                    ->label('Last run')
                    ->placeholder('never'),
                Tables\Columns\IconColumn::make('last_error')
                    ->label('OK')
                    ->boolean()
                    ->state(fn (NewsSource $r) => blank($r->last_error))
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-exclamation-triangle')
                    ->falseColor('danger')
                    ->tooltip(fn (NewsSource $r) => $r->last_error),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active'),
                Tables\Filters\TernaryFilter::make('auto_publish'),
            ])
            ->actions([
                Tables\Actions\Action::make('scrape')
                    ->label('Fetch now')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->action(function (NewsSource $record) {
                        try {
                            $result = (new ScrapeSourceJob($record))->handle();
                            Notification::make()
                                ->title("Imported: {$result['created']} new, {$result['updated']} updated")
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Scrape failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
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
            'index'  => Pages\ListNewsSources::route('/'),
            'create' => Pages\CreateNewsSource::route('/create'),
            'edit'   => Pages\EditNewsSource::route('/{record}/edit'),
        ];
    }
}
