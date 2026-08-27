<?php

namespace App\Filament\Resources\OrderResource\RelationManagers;

use App\Models\ProductionTask;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ProductionTasksRelationManager extends RelationManager
{
    protected static string $relationship = 'productionTasks';

    protected static ?string $title = 'Технологические этапы выполнения';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('operation_name')
                    ->label('Название технологического этапа')
                    ->required()
                    ->maxLength(255),

                Forms\Components\TextInput::make('quantity_to_do')
                    ->label('Количество к выполнению (шт)')
                    ->integer()
                    ->required()
                    ->default(1),

                Forms\Components\Select::make('status')
                    ->label('Текущий статус этапа')
                    ->options([
                        'pending' => '⏳ В очереди',
                        'in_progress' => '⚙️ В работе',
                        'completed' => '✅ Выполнен',
                    ])
                    ->default('pending')
                    ->required(),
            ])->columns(1);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('operation_name')
            ->columns([
                Tables\Columns\TextColumn::make('item_number')
                    ->label('Item')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('operation_name')
                    ->label('Технологическая операция / Задача цеха')
                    ->weight('medium')
                    ->searchable()
                    // Убираем служебный префикс "🌟 Item: N |" / "📦 Item: N |" из отображения —
                    // сам номер позиции уже вынесен в отдельную колонку item_number выше.
                    // Меняется только вид в таблице: $record->operation_name в БД остаётся
                    // прежним и полностью, так что вся логика (поиск SKU, "Заготовительная",
                    // расчёт оставшегося времени) продолжает работать без изменений.
                    ->formatStateUsing(fn (string $state): string => trim(preg_replace('/^[^\|]*\|\s*/u', '', $state))),

                Tables\Columns\TextColumn::make('quantity_to_do')
                    ->label('План (шт)')
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
            ])
            ->filters([])
            ->headerActions([
                // Добавление нестандартных тех. операций теперь доступно только
                // на странице создания/редактирования деталей и тех. операций
                // (ProductResource), поэтому кнопка "Добавить нестандартный этап"
                // здесь убрана.
            ])
            ->actions([
                // 1. КНОПКА "В РАБОТУ"
                Tables\Actions\Action::make('start_work')
                    ->label('В работу')
                    ->icon('heroicon-m-play')
                    ->color('warning')
                    ->visible(fn (ProductionTask $record) => $record->status === 'pending')
                    ->action(function (ProductionTask $record) {
                        $record->update([
                            'status' => 'in_progress',
                            // Фиксируем момент фактического начала работы над этапом —
                            // нужно для отчёта по загрузке оборудования (колонка "Факт").
                            'started_at' => $record->started_at ?? now(),
                        ]);

                        $order = $record->order;
                        if ($order && $order->status === 'pending') {
                            $order->update(['status' => 'in_progress']);
                        }
                    }),

                // 2. КНОПКА "ВЫПОЛНИТЬ" С АВТОСПИСАНИЕМ И ИНТЕЛЛЕКТУАЛЬНЫМ ФИНИШЕМ ЗАКАЗА
                Tables\Actions\Action::make('complete_work')
                    ->label('Выполнить')
                    ->icon('heroicon-m-check-circle')
                    ->color('success')
                    ->visible(fn (ProductionTask $record) => $record->status === 'in_progress')
                    ->requiresConfirmation()

                    ->modalHeading(function (ProductionTask $record): string {
                        if (stripos($record->operation_name, 'Заготовительная') !== false) {
                            return '🪓 Выполнение заготовительной операции';
                        }
                        return '✅ Завершение технологического этапа';
                    })

                    ->modalDescription(function (ProductionTask $record): string {
                        if (stripos($record->operation_name, 'Заготовительная') !== false) {
                            return 'Подтвердите выполнение этапа. Внимание: металл для этой детали будет автоматически снят с резерва и списан со склада!';
                        }
                        return 'Вы подтверждаете завершение данной технологической операции? Изменений на складе материалов не произойдет.';
                    })

                    ->action(function (ProductionTask $record) {
                        $service = app(\App\Services\ProductionService::class);
                        $result = $service->completeProductionTask($record);

                        if (!$result['success']) {
                            \Filament\Notifications\Notification::make()
                                ->title('🚨 Операция заблокирована!')
                                ->body($result['error'])
                                ->danger()
                                ->send();

                            return;
                        }

                        if ($result['order_completed']) {
                            \Filament\Notifications\Notification::make()
                                ->title('🎉 Заказ полностью готов!')
                                ->body("Все технологические этапы выполнены. Заказ №{$result['order']->order_number} автоматически переведен в статус 'Выполнен'.")
                                ->success()
                                ->persistent()
                                ->send();
                        } else {
                            \Filament\Notifications\Notification::make()
                                ->title('Успешно')
                                ->body('Операция успешно завершена!')
                                ->success()
                                ->send();
                        }
                    }),

                // Редактирование и удаление тех. операций перенесено на страницу
                // создания/редактирования деталей и тех. операций (ProductResource).
                // На странице заказа этап можно только двигать по статусам
                // ("В работу" / "Выполнить"), поэтому EditAction и DeleteAction убраны.
            ])
            ->bulkActions([]);
    }
}
