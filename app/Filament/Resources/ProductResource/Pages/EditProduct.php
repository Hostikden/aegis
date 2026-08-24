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
     * Шаг А: Перед открытием формы наполняем репитер данными из БД
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
     * Шаг Б: После сохранения карточки перезаписываем связи в pivot-таблице
     */
  protected function afterSave(): void
{
    $product = $this->record;
    $data = $this->form->getRawState();

    // 1. ЛОГИКА ДЛЯ СБОРКИ (Ваш исходный код)
    if ($product->type === 'assembly') {
        $product->components()->detach();

        if (!empty($data['assembly_components'])) {
            foreach ($data['assembly_components'] as $component) {
                $product->components()->attach($component['component_product_id'], [
                    'quantity' => $component['component_quantity'],
                ]);
            }
        }
    }

    // 2. ИСПРАВЛЕНИЕ ОШИБКИ: ЛОГИКА ДЛЯ МАТЕРИАЛОВ И ПОКУПНЫХ ИЗДЕЛИЙ
    // Проверьте, что в ProductResource.php ваш репитер называется именно 'product_materials'
    if (!empty($data['product_materials'])) {

        // Синхронизируем материалы: для простоты можно перезаписывать их при сохранении карточки
        // (Убедитесь, что у модели Product есть связь materials() или productMaterials())
        $product->materials()->delete();

        foreach ($data['product_materials'] as $item) {
            $materialId = $item['material_id'] ?? null;

            // Если это Покупное изделие — принудительно вычисляем его ID на бэкенде
            if (isset($item['material_type']) && $item['material_type'] === 'Покупное изделие') {
                $material = \App\Models\Material::where('name', 'Покупное изделие')
                    ->where('grade', $item['material_grade'])
                    ->first();
                $materialId = $material?->id;
            }

            // Записываем строку в таблицу product_materials без ошибок валидации SQL
            if ($materialId) {
                $product->materials()->create([
                    'material_id'      => $materialId,
                    'material_type'    => $item['material_type'],
                    'material_grade'   => $item['material_grade'],
                    'detail_length'    => $item['detail_length'] ?? null,
                    'consumption_rate' => $item['consumption_rate'] ?? null,
                ]);
            }
        }
    }
}



 /**
     * Исправление ошибки валидации при редактировании материалов в репитере
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (!empty($data['productMaterials']) && is_array($data['productMaterials'])) {
            foreach ($data['productMaterials'] as $key => $item) {
                if (isset($item['material_type']) && $item['material_type'] === 'Покупное изделие') {
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





}
