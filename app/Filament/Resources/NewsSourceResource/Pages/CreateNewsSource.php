<?php

namespace App\Filament\Resources\NewsSourceResource\Pages;

use App\Filament\Resources\NewsSourceResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateNewsSource extends CreateRecord
{
    protected static string $resource = NewsSourceResource::class;
}
