<?php

namespace App\Filament\Resources\MaterialResource\Pages;

use App\Filament\Resources\MaterialResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMaterials extends ListRecords
{
    protected static string $resource = MaterialResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Добавить материал'),
        ];
    }

    // МЫ ПОЛНОСТЬЮ УДАЛИЛИ МЕТОДЫ ВЫЗОВА ВИДЖЕТА, КОТОРЫЙ ВЫЗЫВАЛ ОШИБКУ 419!

        /**
     * НАСТРОЙКА НА ТИВНЫХ ВКЛАДОК: Фильтрация дефицита сырья прямо над таблицей склада
     */
    public function getTabs(): array
    {
        // Считаем количество позиций, где бронь под производство превышает остатки на складе
        $deficitCount = \App\Models\Material::all()->filter(function ($material) {
            return (float) $material->reserved > (float) $material->quantity;
        })->count();

        return [
            // Вкладка 1: Показывает весь склад без ограничений
            'all' => ListRecords\Tab::make('Все материалы')
                ->icon('heroicon-m-squares-2x2'),

            // Вкладка 2: Фильтрует и выводит только позиции с нехваткой сырья
            'deficit' => ListRecords\Tab::make('🚨 Дефицит' . ($deficitCount > 0 ? " ({$deficitCount})" : ''))
                ->badge($deficitCount > 0 ? $deficitCount : null)
                ->badgeColor('danger')
                ->modifyQueryUsing(function (\Illuminate\Database\Eloquent\Builder $query) {
                    // Передаем SQL-запрос для вывода только дефицитных строк
                    return $query->whereRaw('reserved > quantity');
                }),
        ];
    }

}
