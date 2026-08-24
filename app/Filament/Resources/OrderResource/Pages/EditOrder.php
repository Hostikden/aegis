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
            // НОВАЯ КНОПКА: Печать производственного паспорта деталей
            Actions\Action::make('print_passport')
                ->label('🖨️ Печать паспорта заказа')
                ->color('success')
                // Открывает созданный нами Route в новой вкладке браузера
                ->url(fn () => route('orders.print-passport', ['order' => $this->record->id]))
                ->openUrlInNewTab(),

            // Кнопка обновления резервов
            Actions\Action::make('sync_reservations')
                ->label('🔄 Проверить и обновить резервы')
                ->color('info')
                ->requiresConfirmation()
                ->modalHeading('Синхронизация складских резервов')
                ->modalDescription('Система проверит текущие спецификации (BOM) всех входящих деталей. Если технолог добавил параметры заготовок, под них будет выделен дополнительный резерв на складе.')
                ->action(function () {
                    $order = $this->record;
                    $service = app(ProductionService::class);

                    $service->cancelReservationForOrder($order);
                    $result = $service->syncAndFixOrderReservations($order);

                    if (!empty($result['warnings'])) {
                        foreach ($result['warnings'] as $warning) {
                            Notification::make()
                                ->title('Внимание! Дефицит на складе')
                                ->body($warning)
                                ->warning()
                                ->persistent()
                                ->send();
                        }
                    }

                    Notification::make()
                        ->title('Резервы успешно обновлены')
                        ->body('Спецификации деталей синхронизированы со складом. Заготовительные операции разблокированы!')
                        ->success()
                        ->send();

                    $this->refreshFormData([]);
                }),

            Actions\DeleteAction::make(),
        ];
    }
}
