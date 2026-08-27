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

    /**
     * Снимок количеств по product_id ДО сохранения формы — нужен, чтобы после
     * сохранения понять, изменилось ли количество (или состав) позиций заказа,
     * и требуется ли автоматический пересчёт технологических задач.
     */
    protected array $originalItemQuantities = [];

    protected function beforeSave(): void
    {
        $this->originalItemQuantities = $this->record->orderItems()
            ->pluck('quantity', 'product_id')
            ->toArray();
    }

    /**
     * Автоматический пересчёт необходимых деталей (технологических задач) при
     * изменении количества изделий в заказе.
     *
     * Пересчёт выполняется только если по заказу ещё НИ ОДНА технологическая
     * задача не взята "В работу" и не выполнена — иначе автоматическая
     * перегенерация удалила бы реальный прогресс цеха. В этом случае вместо
     * пересчёта показывается предупреждение с рекомендацией скорректировать
     * задачи вручную.
     */
    protected function afterSave(): void
    {
        $order = $this->record->fresh('orderItems');

        $newItemQuantities = $order->orderItems->pluck('quantity', 'product_id')->toArray();

        if ($newItemQuantities === $this->originalItemQuantities) {
            // Состав и количество позиций не менялись — пересчёт не нужен.
            return;
        }

        $service = app(ProductionService::class);

        if ($service->hasProductionStarted($order)) {
            Notification::make()
                ->title('⚠️ Количество изменено, пересчёт НЕ выполнен автоматически')
                ->body('По заказу уже есть технологические этапы "в работе" или выполненные — автоматический пересчёт мог бы затереть реальный прогресс производства. Скорректируйте количество деталей и резерв материалов вручную на вкладке "Технологические этапы выполнения".')
                ->warning()
                ->persistent()
                ->send();

            return;
        }

        $service->regenerateProductionTasksForOrder($order);

        Notification::make()
            ->title('✅ Технологические этапы пересчитаны')
            ->body('Количество деталей и объём необходимых материалов автоматически обновлены под новое количество в заказе.')
            ->success()
            ->send();

        $this->refreshFormData([]);
    }

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
