<?php

namespace App\Filament\Resources\NonconformityResource\Pages;

use App\Filament\Resources\NonconformityResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListNonconformities extends ListRecords
{
    protected static string $resource = NonconformityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
