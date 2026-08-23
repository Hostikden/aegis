<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Material;
use App\Models\MaterialHistory;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

class ProductionService
{
    /**
     * Рекурсивный расчет потребности в сырье для изделия (детали или сборки)
     */
    public function calculateRequiredMaterials(Product $product, float $quantity): array
    {
        $materialsNeed = [];

        if ($product->type === 'detail') {
            foreach ($product->productMaterials as $pm) {
                $totalNeed = $pm->consumption_rate * $quantity;
                $materialsNeed[$pm->material_id] = ($materialsNeed[$pm->material_id] ?? 0) + $totalNeed;
            }
        } elseif ($product->type === 'assembly') {
            foreach ($product->components as $component) {
                $componentQuantity = $component->pivot->quantity * $quantity;
                $subMaterials = $this->calculateRequiredMaterials($component, $componentQuantity);
                foreach ($subMaterials as $materialId => $neededVolume) {
                    $materialsNeed[$materialId] = ($materialsNeed[$materialId] ?? 0) + $neededVolume;
                }
            }
        }

        return $materialsNeed;
    }

    /**
     * ШАГ 1: Поставить материалы в резерв (Вызывается при создании заказа)
     */
    public function reserveMaterialsForOrder(Order $order): void
    {
        $requirements = $this->calculateRequiredMaterials($order->product, $order->total_quantity);

        DB::transaction(function () use ($requirements) {
            foreach ($requirements as $materialId => $volume) {
                $material = Material::findOrFail($materialId);
                $material->increment('reserved', $volume);
            }
        });
    }

    /**
     * ШАГ 2: Списание из резерва (Вызывается при закрытии Заготовительной операции)
     */
    public function debitMaterialsFromReserve(Order $order): void
    {
        $requirements = $this->calculateRequiredMaterials($order->product, $order->total_quantity);

        DB::transaction(function () use ($requirements, $order) {
            foreach ($requirements as $materialId => $volumeToDebit) {
                $material = Material::findOrFail($materialId);

                // Уменьшаем физический остаток на складе
                $material->decrement('quantity', $volumeToDebit);
                // Снимаем бронь (резерв)
                $material->decrement('reserved', min($material->reserved, $volumeToDebit));

                // Фиксируем операцию расхода в истории
                MaterialHistory::create([
                    'material_id' => $material->id,
                    'type' => 'deduction',
                    'quantity' => $volumeToDebit,
                    'description' => "Автосписание проката по заготовительной операции заказа №{$order->order_number}",
                ]);
            }
        });
    }

    /**
     * ШАГ 3: Аннулирование резерва (Вызывается при отмене заказа)
     */
    public function cancelReservationForOrder(Order $order): void
    {
        $requirements = $this->calculateRequiredMaterials($order->product, $order->total_quantity);

        DB::transaction(function () use ($requirements) {
            foreach ($requirements as $materialId => $volume) {
                $material = Material::findOrFail($materialId);
                $material->decrement('reserved', min($material->reserved, $volume));
            }
        });
    }



        /**
     * Рассчитать общее время изготовления изделия (в минутах) с учетом Тшт и Тпз
     */
    public function calculateProductionTimeInMinutes(Product $product, int $orderQuantity): float
    {
        $totalMinutes = 0;

        if ($product->type === 'detail') {
            // Если это деталь, берем время из её техпроцесса
            foreach ($product->operations as $operation) {
                $pieceTime = floatval($operation->piece_time ?? 0);
                $prepTime = floatval($operation->prep_time ?? 0);

                // Формула: Тпз + (Тшт * Кол-во деталей)
                $totalMinutes += $prepTime + ($pieceTime * $orderQuantity);
            }
        } elseif ($product->type === 'assembly') {
            // Если это сборка, рекурсивно считаем время изготовления всех деталей
            foreach ($product->components as $component) {
                // Кол-во детали в 1 сборке * Общий объем заказа на сборку
                $totalComponentQuantity = $component->pivot->quantity * $orderQuantity;

                $totalMinutes += $this->calculateProductionTimeInMinutes($component, $totalComponentQuantity);
            }

            // Добавляем 60 минут на финальную сборку самого узла в цеху
            $totalMinutes += 60;
        }

        return $totalMinutes;
    }

    /**
     * Отформатировать минуты в красивую строку (дни, часы, минуты)
     */
    public function formatMinutesToHumanTime(float $minutes): string
    {
        if ($minutes <= 0) {
            return 'не задано';
        }

        $minutes = round($minutes);

        $days = floor($minutes / 1440); // 1440 минут в сутках
        $hours = floor(($minutes % 1440) / 60);
        $remainingMinutes = $minutes % 60;

        $result = [];
        if ($days > 0) $result[] = "{$days} дн.";
        if ($hours > 0) $result[] = "{$hours} ч.";
        if ($remainingMinutes > 0 || empty($result)) $result[] = "{$remainingMinutes} мин.";

        return implode(' ', $result);
    }

}
