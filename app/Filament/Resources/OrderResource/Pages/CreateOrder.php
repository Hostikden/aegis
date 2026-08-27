<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use App\Models\Product;
use App\Models\Order;
use App\Models\ProductionTask;
use Filament\Resources\Pages\CreateRecord;

class CreateOrder extends CreateRecord
{
    protected static string $resource = OrderResource::class;

    /**
     * Автогенерация задач цеха циклом по всем позициям многокомпонентного заказа
     */
    protected function afterCreate(): void
    {
        $order = $this->record;

        // ИСПРАВЛЕНО: Бежим циклом по всем изделиям, которые директор добавил в заказ
        foreach ($order->orderItems as $item) {
            if ($item->product) {
                // Запускаем рекурсивную генерацию задач для конкретного изделия из списка
                $this->generateTasksForProduct($order, $item->product, $item->quantity);
            }
        }

        // Ставим материалы в резерв на складе под все изделия сразу
        app(\App\Services\ProductionService::class)->reserveMaterialsForOrder($order);
    }

    /**
     * Рекурсивный метод создания технологических задач для рабочих с расчетом Item ID
     */
    protected function generateTasksForProduct(Order $order, Product $product, int $requiredQuantity): void
    {
        if ($product->type === 'detail') {
            $maxItemNumber = ProductionTask::max('item_number');
            $nextItemNumber = $maxItemNumber ? ($maxItemNumber + 1) : 10000;

            if ($product->operations()->count() > 0) {
                foreach ($product->operations as $operation) {
                    $pieceTime = floatval($operation->piece_time ?? 0);
                    $prepTime = floatval($operation->prep_time ?? 0);

                    $order->productionTasks()->create([
                        'item_number' => $nextItemNumber,
                        'operation_name' => "🌟 Item: {$nextItemNumber} | Опер. {$operation->operation_number} [{$operation->operation_name}] — {$product->name} (чёртеж {$product->sku})",
                        // Тип оборудования берём напрямую из справочника операций,
                        // а не парсим потом декоративную строку выше.
                        'equipment_type' => $operation->operation_name,
                        'status' => 'pending',
                        'quantity_to_do' => $requiredQuantity,
                        'planned_minutes' => $prepTime + ($pieceTime * $requiredQuantity),
                    ]);
                }
            } else {
                $order->productionTasks()->create([
                    'item_number' => $nextItemNumber,
                    'operation_name' => "🌟 Item: {$nextItemNumber} | Производство детали: {$product->name} (чёртеж {$product->sku}) — Техпроцесс не задан!",
                    'equipment_type' => null,
                    'status' => 'pending',
                    'quantity_to_do' => $requiredQuantity,
                    'planned_minutes' => 0,
                ]);
            }
        }
        elseif ($product->type === 'assembly') {
            // Если в одной из позиций заказа указана сборка, бежим по её деталям
            foreach ($product->components as $component) {
                // Рассчитываем количество: сколько детали нужно на 1 узел * количество узлов в данной позиции
                $totalComponentQuantity = $component->pivot->quantity * $requiredQuantity;

                // Рекурсивно создаем задачи для каждого вложенного компонента
                $this->generateTasksForProduct($order, $component, $totalComponentQuantity);
            }

            // Создаем финальную сборочную операцию для самого узла данной позиции
            $maxItemNumber = ProductionTask::max('item_number');
            $nextItemNumber = $maxItemNumber ? ($maxItemNumber + 1) : 10000;

            $order->productionTasks()->create([
                'item_number' => $nextItemNumber,
                'operation_name' => "📦 Item: {$nextItemNumber} | Финальная сборка узла: {$product->name} (чёртеж {$product->sku})",
                // Сборка — отдельный "виртуальный" тип загрузки (участок сборки),
                // время пока = 0, как и было в calculateProductionTimeInMinutes().
                'equipment_type' => 'Сборка',
                'status' => 'pending',
                'quantity_to_do' => $requiredQuantity,
                'planned_minutes' => 0,
            ]);
        }
    }
}
