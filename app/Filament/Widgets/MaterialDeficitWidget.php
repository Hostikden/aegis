<?php

namespace App\Filament\Resources\MaterialResource\Widgets;

use App\Models\Material;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class MaterialDeficitWidget extends BaseWidget
{
    /**
     * Конструктор карточек дефицита (Исправлен баг с ошибкой 419 / Page Expired)
     */
    protected function getStats(): array
    {
        // ИСПРАВЛЕНО: Заменили капризный whereRaw на чистый, безопасный метод фильтрации через коллекции Eloquent
        $deficitMaterials = Material::all()->filter(function ($material) {
            return (float) $material->reserved > (float) $material->quantity;
        });

        $stats = [];

        foreach ($deficitMaterials as $material) {
            // Вычисляем чистый объем нехватки
            $shortage = (float) $material->reserved - (float) $material->quantity;

            $unit = match ($material->name) {
                'Плита' => 'м²',
                'Покупное изделие' => 'шт.',
                default => 'м'
            };

            // Формируем карточку-предупреждение
            $stats[] = Stat::make(
                label: "🚨 ДЕФИЦИТ: {$material->name} ({$material->grade})",
                value: "- " . round($shortage, 4) . " {$unit}"
            )
            ->description("В наличии: {$material->quantity} | В резерве под заказы: {$material->reserved}")
            ->descriptionIcon('heroicon-m-exclamation-triangle')
            ->color('danger');
        }

        // Если дефицита на складе нет — выводим одну спокойную зелёную карточку
        if (empty($stats)) {
            $stats[] = Stat::make('Состояние склада сырья', 'Дефицит отсутствует')
                ->description('Все текущие заказы цеха полностью обеспечены материалами')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success');
        }

        return $stats;
    }
}
