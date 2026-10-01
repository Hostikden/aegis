<?php

namespace App\Filament\Resources\NonconformityResource\Pages;

use App\Filament\Resources\NonconformityResource;
use Filament\Resources\Pages\CreateRecord;

class CreateNonconformity extends CreateRecord
{
    protected static string $resource = NonconformityResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();

        return $data;
    }
}
