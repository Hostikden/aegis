<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use Filament\Resources\Pages\CreateRecord;
use App\Models\Material;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Перехватываем данные репитера productMaterials перед записью в БД
        if (!empty($data['productMaterials']) && is_array($data['productMaterials'])) {
            foreach ($data['productMaterials'] as $key => $item) {
                if (isset($item['material_type']) && $item['material_type'] === 'Покупное изделие') {
                    // Самостоятельно ищем ID покупного изделия в таблице материалов по его наименованию
                    $material = Material::where('name', 'Покупное изделие')
                        ->where('grade', $item['material_grade'] ?? null)
                        ->first();

                    if ($material) {
                        $data['productMaterials'][$key]['material_id'] = $material->id;
                    }
                }
            }
        }

        return $data;
    }
}


