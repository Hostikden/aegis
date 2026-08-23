<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use Filament\Resources\Pages\CreateRecord;

class CreateOrder extends CreateRecord
{
    protected static string $resource = OrderResource::class;

    /**
     * Автогенерация этапов цеха сразу после создания заказа
     */
    protected function afterCreate(): void
    {
        $order = $this->record;

        $defaultStages = [
            '🛠️ Подготовка сырья и разметка металлопроката',
            '📐 Механическая обработка (Резка / Гибка / Токарные работы)',
            '👨‍🏭 Сборочно-сварочные работы (если применимо)',
            '🔍 Технический контроль (ОТК) и проверка геометрии',
            '📦 Упаковка и передача на склад готовой продукции',
        ];

        foreach ($defaultStages as $stageName) {
            $order->productionTasks()->create([
                'operation_name' => $stageName,
                'status' => 'pending',
                'quantity_to_do' => $order->total_quantity, // Автоматически подставляем план из заказа
            ]);
        }
    }
}
