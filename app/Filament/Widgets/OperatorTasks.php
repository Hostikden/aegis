<?php

namespace App\Filament\Widgets;

use App\Models\ProductionTask;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Filament\Forms;
use Filament\Notifications\Notification;

class OperatorTasks extends BaseWidget
{
    // Растянем виджет на всю ширину экрана планшета
    protected int | string | array $columnSpan = 'full';

    protected static ?string $heading = 'Сменное задание / Задачи на участках';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                // Показываем задачи, которые еще не завершены
                ProductionTask::query()->where('status', '!=', 'finished')->latest()
            )
            ->columns([
                Tables\Columns\TextColumn::make('order.order_number')
                    ->label('Заказ')
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('order.product.name')
                    ->label('Изделие'),

                Tables\Columns\TextColumn::make('operation_name')
                    ->label('Этап обработки')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('quantity_to_do')
                    ->label('План (шт)')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('quantity_done')
                    ->label('Сделано (шт)')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Статус')
                    ->badge()
                    ->colors([
                        'danger' => 'waiting',
                        'warning' => 'active',
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'waiting' => 'В очереди',
                        'active' => 'В работе',
                    }),

                Tables\Columns\TextColumn::make('operator.name')
                    ->label('Исполнитель')
                    ->placeholder('Не назначен'),
            ])
            ->actions([
                // 1. Кнопка «Взять в работу»
                Tables\Actions\Action::make('start_task')
                    ->label('Начать работу')
                    ->icon('heroicon-o-play')
                    ->color('warning')
                    ->visible(fn (ProductionTask $record) => $record->status === 'waiting')
                    ->action(function (ProductionTask $record) {
                        $record->update([
                            'status' => 'active',
                            'operator_id' => auth()->id() // Назначаем текущего пользователя исполнителем
                        ]);

                        Notification::make()
                            ->title('Работа начата')
                            ->success()
                            ->send();
                    }),

                // 2. Кнопка «Завершить этап» с модальным окном ввода данных
                Tables\Actions\Action::make('finish_task')
                    ->label('Сдать работу')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (ProductionTask $record) => $record->status === 'active' && $record->operator_id === auth()->id())
                    ->form([
                        Forms\Components\TextInput::make('quantity_done')
                            ->label('Количество годных деталей (шт)')
                            ->numeric()
                            ->default(fn (ProductionTask $record) => $record->quantity_to_do)
                            ->required(),
                        Forms\Components\TextInput::make('quantity_scrapped')
                            ->label('Количество брака (шт)')
                            ->numeric()
                            ->default(0)
                            ->required(),
                    ])
                    ->action(function (ProductionTask $record, array $data) {
                        $record->update([
                            'quantity_done' => $data['quantity_done'],
                            'quantity_scrapped' => $data['quantity_scrapped'],
                            'status' => 'finished',
                        ]);

                        // Логика: если это был финальный этап сборки, можно автоматически закрывать весь заказ.
                        // Пока просто уведомляем мастера.
                        Notification::make()
                            ->title('Этап завершен')
                            ->body("Готово: {$data['quantity_done']} шт. Брак: {$data['quantity_scrapped']} шт.")
                            ->success()
                            ->send();
                    }),
            ])
            ->paginated(false); // Отключаем пагинацию, чтобы видеть смену на одном экране
    }
}
