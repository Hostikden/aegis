<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    /**
     * Логика подстановки material_id для "Покупного изделия" перенесена
     * в сам репитер productMaterials (см. ProductResource::form(),
     * ->mutateRelationshipDataBeforeCreateUsing()).
     *
     * Здесь эта мутация была бесполезна: репитер объявлен через
     * ->relationship('productMaterials'), поэтому Filament сохраняет
     * его строки отдельным вызовом saveRelationships(), а не берёт
     * значения из $data, прошедшего через mutateFormDataBeforeCreate().
     */

    /**
     * Состав сборки (assembly_components) — НЕ relationship-репитер,
     * данные по нему нужно синхронизировать вручную в pivot-таблицу
     * ПОСЛЕ того, как основная запись Product уже создана (иначе нет id).
     */
    protected function afterCreate(): void
    {
        $product = $this->record;

        if ($product->type !== 'assembly') {
            return;
        }

        $components = $this->form->getRawState()['assembly_components'] ?? [];

        if (empty($components)) {
            return;
        }

        foreach ($components as $component) {
            if (empty($component['component_product_id'])) {
                continue;
            }

            $product->components()->attach($component['component_product_id'], [
                'quantity' => $component['component_quantity'] ?? 1,
            ]);
        }
    }
}
