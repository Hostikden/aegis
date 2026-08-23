<?php

namespace App\Filament\Resources\MaterialResource\Pages;

use App\Filament\Resources\MaterialResource;
use App\Filament\Resources\MaterialResource\Widgets\MaterialDeficitWidget;
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

    /**
     * ПОДКЛЮЧЕНИЕ ВИДЖЕТА: Выводим карточки дефицита в самом верху страницы
     */
    protected function getHeaderWidgets(): array
    {
        return [
            MaterialDeficitWidget::class,
        ];
    }

    /**
     * Задаем количество колонок для сетки виджетов (максимум 3 карточки в ряд)
     */
    public function getHeaderWidgetsColumns(): int | array
    {
        return 3;
    }
}
