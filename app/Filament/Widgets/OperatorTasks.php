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
                // ИСПРАВЛЕНО: реальный статус завершения — 'completed', а не
                // 'finished'. Такого значения в БД никогда не бывает (см.
                // миграцию fix_status_in_production_tasks_table), поэтому
                // раньше это условие было истинно всегда и завершённые
                // задачи никогда не пропадали из сменного задания.
                ProductionTask::query()->where('status', '!=', 'completed')->latest()
            )
            ->columns([
                Tables\Columns\TextColumn::make('order.order_number')
                    ->label('Заказ')
                    ->weight('bold'),

                // ИСПРАВЛЕНО: order.product.name почти всегда пусто — заказы
                // многопозиционные (orderItems), а старое поле Order::product_id
                // оставлено только для обратной совместимости и не заполняется
                // формой заказа. Показываем реальный состав заказа через orderItems.
                Tables\Columns\TextColumn::make('order.orderItems')
                    ->label('Изделие')
                    ->getStateUsing(function (ProductionTask $record): string {
                        $items = $record->order?->orderItems ?? collect();

                        $names = $items
                            ->map(fn ($item) => $item->product?->name)
                            ->filter()
                            ->unique();

                        return $names->isNotEmpty() ? $names->implode(', ') : '—';
                    }),

                Tables\Columns\TextColumn::make('operation_name')
                    ->label('Этап обработки')
                    ->badge()
                    ->color('gray')
                    // ИСПРАВЛЕНО: убираем служебный префикс "🌟 Item: N |" /
                    // "📦 Item: N |" из отображения. Сам $record->operation_name
                    // в БД не меняется — только вид в таблице.
                    ->formatStateUsing(fn (string $state): string => trim(preg_replace('/^[^\|]*\|\s*/u', '', $state))),

                Tables\Columns\TextColumn::make('quantity_to_do')
                    ->label('План (шт)')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('quantity_done')
                    ->label('Сделано (шт)')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Статус')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'gray',
                        'in_progress' => 'warning',
                        'completed' => 'success',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => '⏳ В очереди',
                        'in_progress' => '⚙️ В работе',
                        'completed' => '✅ Выполнен',
                        default => $state,
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
                    // ИСПРАВЛЕНО: было 'waiting' — статуса с таким значением
                    // в системе не существует, кнопка не показывалась никогда.
                    ->visible(fn (ProductionTask $record) => $record->status === 'pending')
                    ->action(function (ProductionTask $record) {
                        $record->update([
                            'status' => 'in_progress', // ИСПРАВЛЕНО: было 'active'
                            'operator_id' => auth()->id(), // теперь реально сохранится — поле в $fillable
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
                    // ИСПРАВЛЕНО: было 'active' — статуса с таким значением
                    // в системе не существует, кнопка не показывалась никогда.
                    ->visible(fn (ProductionTask $record) => $record->status === 'in_progress' && $record->operator_id === auth()->id())
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
                            'quantity_done' => $data['quantity_done'], // теперь реально сохранится
                            'quantity_scrapped' => $data['quantity_scrapped'], // теперь реально сохранится
                            'status' => 'completed', // ИСПРАВЛЕНО: было 'finished'
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
