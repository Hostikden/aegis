<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use App\Services\ProductionService;
use Filament\Resources\Pages\CreateRecord;

class CreateOrder extends CreateRecord
{
    protected static string $resource = OrderResource::class;

    /**
     * Автогенерация задач цеха циклом по всем позициям многокомпонентного заказа.
     *
     * Логика генерации (generateTasksForProduct) вынесена в ProductionService,
     * чтобы её же переиспользовать при пересчёте задач после изменения
     * количества на странице редактирования заказа
     * (см. ProductionService::regenerateProductionTasksForOrder).
     */
    protected function afterCreate(): void
    {
        $order = $this->record;
        $service = app(ProductionService::class);

        // Бежим циклом по всем изделиям, которые директор добавил в заказ
        foreach ($order->orderItems as $item) {
            if ($item->product) {
                $service->generateTasksForProduct($order, $item->product, $item->quantity);
            }
        }

        // Ставим материалы в резерв на складе под все изделия сразу
        $service->reserveMaterialsForOrder($order);
    }
}
