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
     * Автогенерация задач цеха на основе техпроцесса изделий с присвоением сквозных номеров Item
     */
    protected function afterCreate(): void
    {
        $order = $this->record;
        $product = $order->product;

        if (!$product) {
            return;
        }

        // Запускаем генерацию задач
        $this->generateTasksForProduct($order, $product, $order->total_quantity);

        // Ставим материалы в резерв на складе
        app(\App\Services\ProductionService::class)->reserveMaterialsForOrder($order);
    }

    /**
     * Рекурсивный метод создания технологических задач для рабочих с расчетом Item ID
     */
    protected function generateTasksForProduct(Order $order, Product $product, int $requiredQuantity): void
    {
        if ($product->type === 'detail') {
            // ВЫЧИСЛЯЕМ СЛЕДУЮЩИЙ УНИКАЛЬНЫЙ НОМЕР ITEM ПО СТРАНЕ ЗАКАЗОВ
            // Если номеров в базе нет — стартуем строго с 10000
            $maxItemNumber = ProductionTask::max('item_number');
            $nextItemNumber = $maxItemNumber ? ($maxItemNumber + 1) : 10000;

            if ($product->operations()->count() > 0) {
                foreach ($product->operations as $operation) {
                    $order->productionTasks()->create([
                        'item_number' => $nextItemNumber, // Фиксируем уникальный сквозной Item ID для чертежа
                        'operation_name' => "🌟 Item: {$nextItemNumber} | Опер. {$operation->operation_number} [{$operation->operation_name}] — {$product->name} (чёртеж {$product->sku})",
                        'status' => 'pending',
                        'quantity_to_do' => $requiredQuantity,
                    ]);
                }
            } else {
                $order->productionTasks()->create([
                    'item_number' => $nextItemNumber,
                    'operation_name' => "🌟 Item: {$nextItemNumber} | Производство детали: {$product->name} (чёртеж {$product->sku}) — Техпроцесс не задан!",
                    'status' => 'pending',
                    'quantity_to_do' => $requiredQuantity,
                ]);
            }
        }
        elseif ($product->type === 'assembly') {
            // Если заказана Сборка, проходим по всем деталям, которые в неё входят
            foreach ($product->components as $component) {
                $totalComponentQuantity = $component->pivot->quantity * $requiredQuantity;

                // Рекурсивно вызываем этот же метод для каждой детали внутри сборки
                // Каждая деталь получит свой индивидуальный сквозной Item ID
                $this->generateTasksForProduct($order, $component, $totalComponentQuantity);
            }

            // Вычисляем Item ID для самой финальной сборочной операции узла
            $maxItemNumber = ProductionTask::max('item_number');
            $nextItemNumber = $maxItemNumber ? ($maxItemNumber + 1) : 10000;

            $order->productionTasks()->create([
                'item_number' => $nextItemNumber,
                'operation_name' => "📦 Item: {$nextItemNumber} | Финальная сборка узла: {$product->name} (чёртеж {$product->sku})",
                'status' => 'pending',
                'quantity_to_do' => $requiredQuantity,
            ]);
        }
    }
}
