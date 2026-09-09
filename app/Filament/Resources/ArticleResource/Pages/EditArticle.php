<?php

namespace App\Filament\Resources\ArticleResource\Pages;

use App\Filament\Resources\ArticleResource;
use App\Models\Article;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditArticle extends EditRecord
{
    protected static string $resource = ArticleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // The same modal the article list offers, so it is to hand while
            // you are actually looking at the piece.
            Actions\Action::make('share')
                ->label('Share')
                ->icon('heroicon-o-share')
                ->color('gray')
                ->visible(fn (): bool => ArticleResource::hasConnectedAccounts())
                ->form(fn (): array => ArticleResource::shareFormSchema($this->getRecord()))
                ->modalHeading(fn (): string => 'Share: ' . $this->getRecord()->title)
                ->modalSubmitActionLabel('Post now')
                ->action(function (array $data): void {
                    /** @var Article $article */
                    $article = $this->getRecord();

                    ArticleResource::performShare($article, $data['platforms']);
                }),

            Actions\DeleteAction::make(),
        ];
    }
}
