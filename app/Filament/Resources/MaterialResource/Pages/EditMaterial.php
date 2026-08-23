<?php

namespace App\Filament\Resources\MaterialResource\Pages;

use App\Filament\Resources\MaterialResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMaterial extends EditRecord
{
    protected static string $resource = MaterialResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Кнопку "Удалить" вверху страницы видит только админ
            Actions\DeleteAction::make()->visible(fn () => auth()->user()->isAdmin()),
        ];
    }

    /**
     * Отключаем кнопку "Сохранить" внизу формы для всех, кроме админа
     */
    protected function getFormActions(): array
    {
        if (!auth()->user()->isAdmin()) {
            return []; // Пустой массив скрывает кнопки "Сохранить" и "Отмена" для склада и менеджера
        }

        return parent::getFormActions();
    }
}
