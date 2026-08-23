<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use App\Services\ProductionService;
use Filament\Resources\Pages\EditRecord;

class EditOrder extends EditRecord
{
    protected static string $resource = OrderResource::class;

    protected function afterSave(): void
    {
        $order = $this->record;

        // Если статус заказа стал "Выполнен" (замените 'completed' на ваш статус из БД)
        if ($order->status === 'completed') {
            $productionService = app(ProductionService::class);

            // Запускаем автоматический расчет дерева спецификации и списание метров/м²
            $productionService->debitMaterialsForOrder(
                $order->product,
                $order->total_quantity,
                $order->order_number
            );
        }
    }
}
