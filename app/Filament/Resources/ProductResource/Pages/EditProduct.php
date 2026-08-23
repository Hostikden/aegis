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

        if ($product->type === 'assembly') {
            // Очищаем старый состав сборки
            $product->components()->detach();

            // Записываем обновленный состав
            if (!empty($data['assembly_components'])) {
                foreach ($data['assembly_components'] as $component) {
                    $product->components()->attach($component['component_product_id'], [
                        'quantity' => $component['component_quantity'],
                    ]);
                }
            }
        }
    }
}
