<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use App\Services\ProductionService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditOrder extends EditRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // УМНАЯ КНОПКА ОБНОВЛЕНИЯ РЕЗЕРВОВ
            Actions\Action::make('sync_reservations')
                ->label('🔄 Проверить и обновить резервы')
                ->color('info')
                ->requiresConfirmation()
                ->modalHeading('Синхронизация складских резервов')
                ->modalDescription('Система проверит текущие спецификации (BOM) всех входящих деталей. Если технолог добавил параметры заготовок, под них будет выделен дополнительный резерв на складе.')
                ->action(function () {
                    $order = $this->record;
                    $service = app(ProductionService::class);

                    // Сначала очищаем старый (неполный) резерв, чтобы избежать задваивания
                    $service->cancelReservationForOrder($order);

                    // Накатываем новый актуальный резерв с учетом заполненных карточек деталей
                    $result = $service->syncAndFixOrderReservations($order);

                    // Если склад выдал предупреждения о дефиците металла
                    if (!empty($result['warnings'])) {
                        foreach ($result['warnings'] as $warning) {
                            Notification::make()
                                ->title('Внимание! Дефицит на складе')
                                ->body($warning)
                                ->warning()
                                ->persistent() // Уведомление не исчезнет, пока не закроют
                                ->send();
                        }
                    }

                    Notification::make()
                        ->title('Резервы успешно обновлены')
                        ->body('Спецификации деталей синхронизированы со складом. Заготовительные операции разблокированы!')
                        ->success()
                        ->send();

                    // Перерисовываем страницу, чтобы обновились свободные остатки
                    $this->refreshFormData([]);
                }),

            Actions\DeleteAction::make(),
        ];
    }
}
