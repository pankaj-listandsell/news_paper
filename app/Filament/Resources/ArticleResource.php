<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ArticleResource\Pages;
use App\Models\Article;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class ArticleResource extends Resource
{
    protected static ?string $model = Article::class;

    protected static ?string $navigationIcon = 'heroicon-o-newspaper';

    protected static ?string $navigationGroup = 'Content';

    protected static ?int $navigationSort = 1;

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
            ])
            ->actions([
                Tables\Actions\Action::make('aiRewrite')
                    ->label('AI rewrite')
                    ->icon('heroicon-o-sparkles')
                    ->color('info')
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
                Tables\Actions\EditAction::make(),
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
            'index'  => Pages\ListArticles::route('/'),
            'create' => Pages\CreateArticle::route('/create'),
            'edit'   => Pages\EditArticle::route('/{record}/edit'),
        ];
    }
}
