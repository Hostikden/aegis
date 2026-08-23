<?php

namespace App\Filament\Resources\MaterialResource\Widgets;

use App\Models\Material;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class MaterialDeficitWidget extends BaseWidget
{
    /**
     * Конструктор карточек предупреждений о дефиците металлов и комплектующих
     */
    protected function getStats(): array
    {
        // Находим на складе позиции, где зарезервировано больше, чем есть физически в наличии
        $deficitMaterials = Material::whereRaw('reserved > quantity')->get();

        $stats = [];

        foreach ($deficitMaterials as $material) {
            // Вычисляем чистый объем нехватки
            $shortage = $material->reserved - $material->quantity;

            // Определяем единицу измерения для красивого вывода
            $unit = match ($material->name) {
                'Плита' => 'м²',
                'Покупное изделие' => 'шт.',
                default => 'м'
            };

            // Формируем красную карточку-предупреждение
            $stats[] = Stat::make(
                label: "🚨 ДЕФИЦИТ: {$material->name} ({$material->grade})",
                value: "- {$shortage} {$unit}"
            )
            ->description("В наличии: {$material->quantity} | Требуется брони: {$material->reserved}")
            ->descriptionIcon('heroicon-m-exclamation-triangle')
            ->color('danger');
        }

        // Если дефицита на заводе нет, выводим одну зелёную карточку «Всё в порядке»
        if (empty($stats)) {
            $stats[] = Stat::make('Состояние склада сырья', 'Дефицит отсутствует')
                ->description('Все текущие заказы цеха полностью обеспечены материалами')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success');
        }

        return $stats;
    }
}
