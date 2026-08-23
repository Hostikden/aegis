<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use Filament\Resources\Pages\CreateRecord;

class CreateOrder extends CreateRecord
{
    protected static string $resource = OrderResource::class;

    /**
     * Выполняется автоматически СРАЗУ после того, как заказ записан в базу данных
     */
    protected function afterCreate(): void
    {
        $order = $this->record;

        // Массив стандартных этапов для производства изделия
        $defaultStages = [
            '🛠️ Подготовка сырья и разметка металлопроката',
            '📐 Механическая обработка (Резка / Гибка / Токарные работы)',
            '👨‍🏭 Сборочно-сварочные работы (если применимо)',
            '🔍 Технический контроль (ОТК) и проверка геометрии',
            '📦 Упаковка и передача на склад готовой продукции',
        ];

        // Автоматически генерируем задачи в БД для созданного заказа
        foreach ($defaultStages as $stageName) {
            $order->productionTasks()->create([
                'name' => $stageName,
                'status' => 'pending', // Статус по умолчанию: "В очереди"
            ]);
        }
    }
}
