<?php

namespace App\Filament\Resources\SteelGradeResource\Pages;

use App\Filament\Resources\SteelGradeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSteelGrades extends ListRecords
{
    protected static string $resource = SteelGradeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
