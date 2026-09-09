<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ArticleResource\Pages;
use App\Models\Article;
use App\Models\Category;
use App\Models\SocialAccount;
use App\Models\SocialShare;
use App\Social\SocialSharer;
use Filament\Notifications\Notification;
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
                            Forms\Components\Select::make('http_status')
                                ->label('HTTP status')
                                ->options([
                                    200 => '200 — OK (normal page)',
                                    301 => '301 — Moved permanently',
                                    304 => '304 — Not modified (frozen for crawlers)',
                                ])
                                ->default(200)
                                ->required()
                                ->selectablePlaceholder(false)
                                ->live()
                                ->helperText(fn (Forms\Get $get): string => match ((int) $get('http_status')) {
                                    301 => 'Visitors and search engines are sent to the URL below.',
                                    304 => 'Search engines are told the page never changes, so they stop re-crawling it. Readers still get the full page as normal.',
                                    default => 'The article is served normally.',
                                }),
                            Forms\Components\TextInput::make('redirect_url')
                                ->label('Redirect to')
                                ->placeholder('https://hauptstadt-report.de/nachrichten/neuer-artikel')
                                ->helperText('Full URL, or a path starting with /')
                                ->maxLength(255)
                                ->required()
                                ->regex('/^(https?:\/\/|\/)/')
                                ->validationMessages([
                                    'regex' => 'Enter a full URL (https://…) or a path starting with /.',
                                ])
                                ->visible(fn (Forms\Get $get): bool => (int) $get('http_status') === 301),
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
                                ->required()
                                ->live()
                                // Picking a category fills in its author, but only
                                // on a new article — an existing one keeps the
                                // author it was published with.
                                ->afterStateUpdated(function ($state, Forms\Set $set, string $operation): void {
                                    if ($operation !== 'create' || blank($state)) {
                                        return;
                                    }

                                    if ($authorId = Category::find($state)?->user_id) {
                                        $set('user_id', $authorId);
                                    }
                                }),
                            Forms\Components\Select::make('user_id')
                                ->label('Author')
                                ->relationship('author', 'name')
                                ->searchable()
                                ->preload()
                                ->default(auth()->id())
                                ->required()
                                ->helperText(fn (Forms\Get $get): ?string => Category::find($get('category_id'))?->author?->name
                                    ? 'Filled in from the category. You can still change it.'
                                    : null),
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

    /**
     * The "post to" checkboxes. Shared by the row action and the button on the
     * article's own page, so the two cannot drift apart.
     *
     * @return array<int, Forms\Components\CheckboxList>
     */
    public static function shareFormSchema(Article $article): array
    {
        return [
            Forms\Components\CheckboxList::make('platforms')
                ->label('Post to')
                ->options(static::sharablePlatforms($article))
                ->descriptions(static::shareStatuses($article))
                // Tick what has not gone out yet; leave the rest for the
                // editor to decide about.
                ->default(array_keys(static::sharablePlatforms($article, onlyUnsent: true)))
                ->required(),
        ];
    }

    /**
     * @param  array<int, string>  $platforms
     */
    public static function performShare(Article $article, array $platforms): void
    {
        $sharer = new SocialSharer();
        $sent   = [];
        $held   = [];

        foreach ($platforms as $platform) {
            $share = $sharer->share($article, $platform);

            $share->wasSent()
                ? $sent[] = $platform
                : $held[] = $platform . ' (' . $share->error . ')';
        }

        if ($sent !== []) {
            Notification::make()
                ->title('Posted to ' . implode(', ', $sent))
                ->success()
                ->send();
        }

        if ($held !== []) {
            Notification::make()
                ->title('Not posted')
                ->body(implode("\n", $held))
                ->danger()
                ->persistent()
                ->send();
        }
    }

    /**
     * Is there anywhere to post at all?
     */
    public static function hasConnectedAccounts(): bool
    {
        return SocialAccount::where('is_active', true)->exists();
    }

    /**
     * Platforms this article can be sent to right now.
     *
     * @return array<string, string>  platform => label
     */
    public static function sharablePlatforms(Article $article, bool $onlyUnsent = false): array
    {
        $sent = $article->socialShares
            ->where('status', SocialShare::SENT)
            ->pluck('platform')
            ->all();

        return SocialAccount::where('is_active', true)
            ->get()
            ->when($onlyUnsent, fn ($accounts) => $accounts->reject(
                fn (SocialAccount $a) => in_array($a->platform, $sent, true)
            ))
            ->mapWithKeys(fn (SocialAccount $a) => [$a->platform => $a->label()])
            ->all();
    }

    /**
     * A line under each checkbox saying where that platform stands.
     *
     * @return array<string, string>
     */
    public static function shareStatuses(Article $article): array
    {
        return $article->socialShares
            ->mapWithKeys(fn (SocialShare $s) => [$s->platform => match ($s->status) {
                SocialShare::SENT    => 'Already posted ' . $s->posted_at?->diffForHumans() . '. Ticking this does nothing.',
                SocialShare::FAILED  => 'Failed after ' . $s->attempts . ' attempts: ' . $s->error,
                SocialShare::SKIPPED => 'Deliberately excluded.',
                default              => $s->error ? 'Waiting: ' . $s->error : 'Waiting.',
            }])
            ->all();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('socialShares'))
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
                Tables\Columns\TextColumn::make('http_status')
                    ->label('HTTP')
                    ->badge()
                    ->color(fn (int $state): string => match ($state) {
                        301     => 'warning',
                        304     => 'info',
                        default => 'gray',
                    })
                    ->tooltip(fn (Article $r): ?string => match ($r->http_status) {
                        301     => 'Redirects to ' . $r->redirect_url,
                        304     => 'Frozen — crawlers get 304, readers get the page',
                        default => null,
                    }),
                Tables\Columns\IconColumn::make('is_featured')
                    ->boolean()
                    ->label('Feat.'),
                Tables\Columns\IconColumn::make('is_breaking')
                    ->boolean()
                    ->label('Break.'),
                Tables\Columns\TextColumn::make('socialShares.platform')
                    ->label('Shared')
                    ->badge()
                    ->separator(',')
                    ->getStateUsing(fn (Article $r): array => $r->socialShares
                        ->where('status', SocialShare::SENT)
                        ->pluck('platform')
                        ->all())
                    ->placeholder('—')
                    ->toggleable(),
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
                Tables\Filters\SelectFilter::make('http_status')
                    ->label('HTTP status')
                    ->options([
                        200 => '200 — OK',
                        301 => '301 — Moved permanently',
                        304 => '304 — Frozen',
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
                Tables\Actions\Action::make('share')
                    ->label('Share')
                    ->icon('heroicon-o-share')
                    ->color('gray')
                    // Nothing to offer until at least one account is connected.
                    ->visible(fn (): bool => static::hasConnectedAccounts())
                    ->form(fn (Article $record): array => static::shareFormSchema($record))
                    ->modalHeading(fn (Article $record): string => 'Share: ' . $record->title)
                    ->modalSubmitActionLabel('Post now')
                    ->action(fn (Article $record, array $data) => static::performShare($record, $data['platforms'])),
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
