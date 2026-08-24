<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;

class OrderPrintController extends Controller
{
    /**
     * Сбор данных заказа для генерации печатного паспорта деталей
     */
    public function print(Order $order)
    {
        // Проверяем права доступа (печать разрешена админу, директору и начальнику цеха)
        if (!auth()->user()->hasAnyRole(['admin', 'director', 'manager'])) {
            abort(403, 'Доступ к печати документов ограничен.');
        }

        // Загружаем позиции заказа и привязанные к заказу технологические задачи цеха
        $order->load(['orderItems.product.operations', 'productionTasks']);

        $passportItems = [];

        // Бежим по всем позициям заказа
        foreach ($order->orderItems as $item) {
            $product = $item->product;
            if (!$product) continue;

            if ($product->type === 'detail') {
                // Ищем уникальный Item ID, сгенерированный под эту деталь в рамках текущего заказа
                $task = $order->productionTasks()
                    ->where('operation_name', 'like', "%— {$product->name}%")
                    ->first();
                $itemNumber = $task ? $task->item_number : '10000';

                $passportItems[] = [
                    'product' => $product,
                    'quantity' => $item->quantity,
                    'item_number' => $itemNumber,
                    'materials' => $product->productMaterials,
                    'operations' => $product->operations,
                ];
            } elseif ($product->type === 'assembly') {
                // Если заказана сборка, выводим паспорт для каждого её вложенного компонента отдельно
                foreach ($product->components as $component) {
                    $task = $order->productionTasks()
                        ->where('operation_name', 'like', "%— {$component->name}%")
                        ->first();
                    $itemNumber = $task ? $task->item_number : '10000';

                    $totalComponentQty = $component->pivot->quantity * $item->quantity;

                    $passportItems[] = [
                        'product' => $component,
                        'quantity' => $totalComponentQty,
                        'item_number' => $itemNumber,
                        'materials' => $component->productMaterials,
                        'operations' => $component->operations,
                    ];
                }
            }
        }

        // Передаем собранный массив в Blade-шаблон печати
        return view('print.order-passport', compact('order', 'passportItems'));
    }
}
