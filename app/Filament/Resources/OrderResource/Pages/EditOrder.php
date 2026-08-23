<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use App\Services\ProductionService;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditOrder extends EditRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    /**
     * Выполняется автоматически СРАЗУ после того, как заказ обновлен в базе данных
     */
    protected function afterSave(): void
    {
        $order = $this->record;

        // Безопасная проверка статуса: списываем металл со склада ТОЛЬКО если заказ переведен в статус "Выполнен"
        if ($order->status === 'completed') {
            // Проверяем, существует ли класс ProductionService, чтобы избежать фатальных ошибок
            if (class_exists(ProductionService::class)) {
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
}
