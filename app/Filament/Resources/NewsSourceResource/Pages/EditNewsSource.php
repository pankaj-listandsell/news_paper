<?php

namespace App\Filament\Resources\NewsSourceResource\Pages;

use App\Filament\Resources\NewsSourceResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditNewsSource extends EditRecord
{
    protected static string $resource = NewsSourceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
