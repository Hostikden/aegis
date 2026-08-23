<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Material;
use App\Models\MaterialHistory;
use Illuminate\Support\Facades\DB;

class ProductionService
{
    /**
     * Рассчитать общую потребность в сырье для любого изделия (детали или сборки)
     */
    public function calculateRequiredMaterials(Product $product, float $orderQuantity): array
    {
        $materialsNeed = [];

        if ($product->type === 'detail') {
            // Если это простая деталь, берем её прямые нормы расхода металлов
            foreach ($product->productMaterials as $pm) {
                $totalNeed = $pm->consumption_rate * $orderQuantity;
                if (!isset($materialsNeed[$pm->material_id])) {
                    $materialsNeed[$pm->material_id] = 0;
                }
                $materialsNeed[$pm->material_id] += $totalNeed;
            }
        } elseif ($product->type === 'assembly') {
            // Если это сборка, проходим по всем вложенным компонентам
            foreach ($product->components as $component) {
                // Количество детали на 1 сборку * общее количество сборок в заказе
                $componentQuantity = $component->pivot->quantity * $orderQuantity;

                // Рекурсивно собираем металлы для вложенной детали
                $subMaterials = $this->calculateRequiredMaterials($component, $componentQuantity);

                foreach ($subMaterials as $materialId => $neededVolume) {
                    if (!isset($materialsNeed[$materialId])) {
                        $materialsNeed[$materialId] = 0;
                    }
                    $materialsNeed[$materialId] += $neededVolume;
                }
            }
        }

        return $materialsNeed;
    }

    /**
     * Выполнить физическое списание металлов со склада и залогировать в историю движений
     */
    public function debitMaterialsForOrder(Product $product, float $orderQuantity, string $orderNumber): void
    {
        $requirements = $this->calculateRequiredMaterials($product, $orderQuantity);

        DB::transaction(function () use ($requirements, $orderNumber) {
            foreach ($requirements as $materialId => $volumeToDebit) {
                $material = Material::findOrFail($materialId);

                // 1. Уменьшаем остаток на складе
                $material->decrement('quantity', $volumeToDebit);

                // 2. Записываем операцию в историю движений, которую мы настраивали ранее
                MaterialHistory::create([
                    'material_id' => $material->id,
                    'type' => 'deduction', // Расход / Списание
                    'quantity' => $volumeToDebit,
                    'description' => "Списание под производство заказа №{$orderNumber}",
                ]);
            }
        });
    }
}
