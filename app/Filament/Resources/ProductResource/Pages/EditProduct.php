<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    /**
     * Шаг А: перед открытием формы наполняем репитер assembly_components
     * данными из pivot-таблицы. Это нужно вручную, т.к. assembly_components
     * НЕ подключён через ->relationship() (он завязан на pivot с доп. полем
     * quantity, поэтому синхронизируется вручную в afterSave()).
     *
     * Репитер productMaterials трогать здесь не нужно — он объявлен как
     * ->relationship('productMaterials') и Filament наполняет его сам.
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $product = $this->record;

        if ($product->type === 'assembly') {
            $data['assembly_components'] = $product->components->map(function ($component) {
                return [
                    'component_product_id' => $component->id,
                    'component_quantity' => $component->pivot->quantity,
                ];
            })->toArray();
        }

        return $data;
    }

    /**
     * Шаг Б: после сохранения карточки перезаписываем pivot-связи сборки.
     *
     * Логика сохранения productMaterials здесь намеренно убрана:
     * - репитер объявлен через ->relationship('productMaterials'), поэтому
     *   Filament уже сам создаёт/обновляет/удаляет строки при сохранении
     *   формы (через встроенный saveRelationships());
     * - ручной блок, который был здесь раньше ($product->materials()->delete()
     *   + пересоздание), дублировал и конфликтовал с этим механизмом,
     *   а из-за опечатки в имени ключа ($data['product_materials'] вместо
     *   $data['productMaterials']) фактически никогда не выполнялся.
     *
     * Подстановка material_id для "Покупного изделия" теперь делается
     * в самом репитере через ->mutateRelationshipDataBeforeSaveUsing()
     * (см. ProductResource::form()).
     */
    protected function afterSave(): void
    {
        $product = $this->record;

        if ($product->type !== 'assembly') {
            return;
        }

        $product->components()->detach();

        $components = $this->form->getRawState()['assembly_components'] ?? [];

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
