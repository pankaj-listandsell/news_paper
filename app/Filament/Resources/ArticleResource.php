<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ArticleResource\Pages;
use App\Models\Article;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class ArticleResource extends Resource
{
    protected static ?string $model = Article::class;

    protected static ?string $navigationIcon = 'heroicon-o-newspaper';

    protected static ?string $navigationGroup = 'Content';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'title';

    /**
     * Eager-load the relations the table renders, so a page of articles is a
     * couple of queries instead of one per row.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['category', 'author']);
    }

    /**
     * @return array<int, string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return ['title', 'excerpt'];
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Grid::make(3)->schema([
                    Forms\Components\Group::make()->schema([
                        Forms\Components\Section::make('Article')->schema([
                            Forms\Components\TextInput::make('title')
                                ->required()
                                ->maxLength(255)
                                ->live(onBlur: true)
                                ->afterStateUpdated(function (string $operation, string $state, Forms\Set $set) {
                                    if ($operation === 'create') {
                                        $set('slug', Str::slug($state));
                                    }
                                }),
                            Forms\Components\TextInput::make('slug')
                                ->required()
                                ->maxLength(255)
                                ->unique(ignoreRecord: true),
                            Forms\Components\TextInput::make('subtitle')
                                ->maxLength(255),
                            Forms\Components\Textarea::make('excerpt')
                                ->rows(2)
                                ->maxLength(500)
                                ->helperText('Short summary shown on listing pages.'),
                            Forms\Components\RichEditor::make('body')
                                ->required()
                                ->columnSpanFull()
                                ->fileAttachmentsDisk('public')
                                ->fileAttachmentsDirectory('articles/attachments'),
                        ]),

                        Forms\Components\Section::make('SEO')
                            ->collapsed()
                            ->schema([
                                Forms\Components\TextInput::make('meta_title')
                                    ->maxLength(255),
                                Forms\Components\Textarea::make('meta_description')
                                    ->rows(2)
                                    ->maxLength(255),
                            ]),
                    ])->columnSpan(2),

                    Forms\Components\Group::make()->schema([
                        Forms\Components\Section::make('Publish')->schema([
                            Forms\Components\Select::make('status')
                                ->options([
                                    'draft'     => 'Draft',
                                    'pending'   => 'Pending review',
                                    'published' => 'Published',
                                ])
                                ->default('draft')
                                ->required()
                                ->live(),
                            Forms\Components\DateTimePicker::make('published_at')
                                ->label('Publish date')
                                ->default(now()),
                            Forms\Components\Toggle::make('is_featured')
                                ->label('Featured'),
                            Forms\Components\Toggle::make('is_breaking')
                                ->label('Breaking news'),
                        ]),

                        Forms\Components\Section::make('Organization')->schema([
                            Forms\Components\Select::make('category_id')
                                ->relationship('category', 'name')
                                ->searchable()
                                ->preload()
                                ->required(),
                            Forms\Components\Select::make('user_id')
                                ->label('Author')
                                ->relationship('author', 'name')
                                ->searchable()
                                ->preload()
                                ->default(auth()->id())
                                ->required(),
                            Forms\Components\Select::make('tags')
                                ->relationship('tags', 'name')
                                ->multiple()
                                ->searchable()
                                ->preload(),
                        ]),

                        Forms\Components\Section::make('Featured image')->schema([
                            Forms\Components\FileUpload::make('featured_image')
                                ->image()
                                ->disk('public')
                                ->directory('articles')
                                ->imageEditor(),
                        ]),
                    ])->columnSpan(1),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->paginationPageOptions([10, 25, 50, 100])
            ->columns([
                Tables\Columns\ImageColumn::make('featured_image')
                    ->disk('public')
                    ->label('')
                    ->square(),
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->limit(40)
                    ->description(fn (Article $r) => $r->category?->name),
                Tables\Columns\TextColumn::make('author.name')
                    ->label('Author')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'published' => 'success',
                        'pending'   => 'warning',
                        default     => 'gray',
                    }),
                Tables\Columns\IconColumn::make('is_featured')
                    ->boolean()
                    ->label('Feat.'),
                Tables\Columns\IconColumn::make('is_breaking')
                    ->boolean()
                    ->label('Break.'),
                Tables\Columns\TextColumn::make('views')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('published_at')
                    ->dateTime('d M Y')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'draft'     => 'Draft',
                        'pending'   => 'Pending review',
                        'published' => 'Published',
                    ]),
                Tables\Filters\SelectFilter::make('category')
                    ->relationship('category', 'name'),
                Tables\Filters\TernaryFilter::make('is_featured'),
                Tables\Filters\TernaryFilter::make('is_breaking'),
                Tables\Filters\Filter::make('published_at')
                    ->label('Publish date')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label('Published from')
                            ->native(false),
                        Forms\Components\DatePicker::make('until')
                            ->label('Published until')
                            ->native(false),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->whereDate('published_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $q, $date) => $q->whereDate('published_at', '<=', $date)))
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
                Tables\Actions\Action::make('aiRewrite')
                    ->label('AI rewrite')
                    ->icon('heroicon-o-sparkles')
                    ->color('info')
                    ->iconButton()
                    ->tooltip('Rewrite title, excerpt and SEO with AI')
                    ->form([
                        Forms\Components\Select::make('provider')
                            ->label('Provider')
                            ->options(\App\Support\AiConfig::providerOptions())
                            ->default(\App\Support\AiConfig::provider())
                            ->required(),
                    ])
                    ->action(function (Article $record, array $data) {
                        $rewriter = \App\Scraping\AiRewriterFactory::make($data['provider']);

                        if (! $rewriter->isConfigured()) {
                            \Filament\Notifications\Notification::make()
                                ->title('API key missing')
                                ->body("Add this provider's API key in AI Settings.")
                                ->warning()
                                ->send();

                            return;
                        }

                        $result = $rewriter->rewrite(
                            $record->title,
                            $record->body ?? '',
                            \App\Support\AiConfig::language()
                        );

                        if ($result === null) {
                            \Filament\Notifications\Notification::make()
                                ->title('AI rewrite failed')
                                ->body('The provider returned no response. Check the logs.')
                                ->danger()
                                ->send();

                            return;
                        }

                        $record->update([
                            'title'            => $result['title'],
                            'excerpt'          => $result['excerpt'] ?: $record->excerpt,
                            'body'             => ! empty($result['body']) ? $result['body'] : $record->body,
                            'meta_title'       => $result['meta_title'],
                            'meta_description' => $result['meta_description'],
                        ]);

                        \Filament\Notifications\Notification::make()
                            ->title('Rewritten with AI')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('aiImage')
                    ->label('AI image')
                    ->icon('heroicon-o-photo')
                    ->color('warning')
                    ->iconButton()
                    ->tooltip('Generate a new AI image')
                    ->requiresConfirmation()
                    ->modalDescription('Generates a new AI image (OpenAI). This replaces the current image.')
                    ->action(function (Article $record) {
                        $generator = new \App\Scraping\AiImageGenerator();

                        if (! $generator->isConfigured()) {
                            \Filament\Notifications\Notification::make()
                                ->title('OpenAI key missing')
                                ->body('Add your OpenAI key in AI Settings.')
                                ->warning()
                                ->send();

                            return;
                        }

                        $path = $generator->generate($record->title, $record->category?->name);

                        if ($path === null) {
                            \Filament\Notifications\Notification::make()
                                ->title('Image generation failed')
                                ->body('Check the logs (content policy or API error).')
                                ->danger()
                                ->send();

                            return;
                        }

                        $record->update(['featured_image' => $path]);

                        \Filament\Notifications\Notification::make()
                            ->title('AI image generated')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('viewOnSite')
                        ->label('View on site')
                        ->icon('heroicon-o-arrow-top-right-on-square')
                        ->url(fn (Article $record) => route('article.show', $record))
                        ->openUrlInNewTab()
                        ->visible(fn (Article $record) => $record->status === 'published'),
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\DeleteAction::make(),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('publish')
                        ->label('Publish')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->deselectRecordsAfterCompletion()
                        ->action(function (Collection $records) {
                            $records->each(fn (Article $r) => $r->update([
                                'status'       => 'published',
                                // Keep the original date; only fill it when missing.
                                'published_at' => $r->published_at ?? now(),
                            ]));
                        }),
                    Tables\Actions\BulkAction::make('unpublish')
                        ->label('Move to draft')
                        ->icon('heroicon-o-eye-slash')
                        ->color('gray')
                        ->requiresConfirmation()
                        ->deselectRecordsAfterCompletion()
                        ->action(fn (Collection $records) => $records->each->update(['status' => 'draft'])),
                    Tables\Actions\BulkAction::make('feature')
                        ->label('Mark as featured')
                        ->icon('heroicon-o-star')
                        ->color('warning')
                        ->deselectRecordsAfterCompletion()
                        ->action(fn (Collection $records) => $records->each->update(['is_featured' => true])),
                    Tables\Actions\BulkAction::make('unfeature')
                        ->label('Remove featured')
                        ->icon('heroicon-o-star')
                        ->color('gray')
                        ->deselectRecordsAfterCompletion()
                        ->action(fn (Collection $records) => $records->each->update(['is_featured' => false])),
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
            'index'  => Pages\ListArticles::route('/'),
            'create' => Pages\CreateArticle::route('/create'),
            'edit'   => Pages\EditArticle::route('/{record}/edit'),
        ];
    }
}
