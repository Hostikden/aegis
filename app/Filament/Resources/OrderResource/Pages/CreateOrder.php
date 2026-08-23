<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use App\Models\Product;
use App\Models\Order;
use Filament\Resources\Pages\CreateRecord;

class CreateOrder extends CreateRecord
{
    protected static string $resource = OrderResource::class;

    /**
     * Автогенерация задач цеха на основе реального техпроцесса заказываемых изделий
     */
    protected function afterCreate(): void
    {
        $order = $this->record;
        $product = $order->product;

        if (!$product) {
            return;
        }

        // Запускаем генерацию задач в зависимости от типа (деталь или сборка)
        $this->generateTasksForProduct($order, $product, $order->total_quantity);

                // В конец метода afterCreate() добавьте строку:
        app(\App\Services\ProductionService::class)->reserveMaterialsForOrder($order);


    }

    /**
     * Рекурсивный метод создания технологических задач для рабочих
     */
    protected function generateTasksForProduct(Order $order, Product $product, int $requiredQuantity): void
    {
        if ($product->type === 'detail') {
            // Если у детали прописан свой техпроцесс в карточке — берем его!
            if ($product->operations()->count() > 0) {
                foreach ($product->operations as $operation) {
                    $order->productionTasks()->create([
                        // Формируем понятное название: "Опер. 10 [Токарная] (Чертеж №...)"
                        'operation_name' => "Опер. {$operation->operation_number} [{$operation->operation_name}] — {$product->name} (чёртеж {$product->sku})",
                        'status' => 'pending',
                        'quantity_to_do' => $requiredQuantity,
                    ]);
                }
            } else {
                // Если технолог забыл составить техпроцесс, создаем одну базовую задачу, чтобы производство не встало
                $order->productionTasks()->create([
                    'operation_name' => "Производство детали: {$product->name} (чёртеж {$product->sku}) — Техпроцесс не задан!",
                    'status' => 'pending',
                    'quantity_to_do' => $requiredQuantity,
                ]);
            }
        }
        elseif ($product->type === 'assembly') {
            // Если заказана Сборка, проходим по всем деталям, которые в неё входят
            foreach ($product->components as $component) {
                // Количество детали на 1 сборку * общее количество сборок в текущем заказе
                $totalComponentQuantity = $component->pivot->quantity * $requiredQuantity;

                // Рекурсивно вызываем этот же метод для каждой вложенной детали
                $this->generateTasksForProduct($order, $component, $totalComponentQuantity);
            }

            // Добавляем финальный этап общей сборки всего изделия из готовых деталей
            $order->productionTasks()->create([
                'operation_name' => "📦 Финальная сборка узла: {$product->name} (чёртеж {$product->sku})",
                'status' => 'pending',
                'quantity_to_do' => $requiredQuantity,
            ]);
        }
    }
} // Конец класса CreateOrder
