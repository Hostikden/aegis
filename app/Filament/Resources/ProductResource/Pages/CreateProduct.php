<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    /**
     * Вручную привязываем детали к сборке после создания продукта
     */
    protected function afterCreate(): void
    {
        $product = $this->record;
        $data = $this->form->getRawState();

        if ($product->type === 'assembly' && !empty($data['assembly_components'])) {
            foreach ($data['assembly_components'] as $component) {
                $product->components()->attach($component['component_product_id'], [
                    'quantity' => $component['component_quantity'],
                ]);
            }
        }
    }
}
