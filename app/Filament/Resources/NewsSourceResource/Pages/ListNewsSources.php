<?php

namespace App\Filament\Resources\NewsSourceResource\Pages;

use App\Filament\Resources\NewsSourceResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListNewsSources extends ListRecords
{
    protected static string $resource = NewsSourceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
