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
                Tables\Columns\TextColumn::make('operation_name')
                    ->label('Технологическая операция / Задача')
                    ->weight('medium')
                    ->searchable(),

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
                Tables\Actions\CreateAction::make()->label('Добавить нестандартный этап'),
            ])
            ->actions([
                // 1. КНОПКА "В РАБОТУ"
                Tables\Actions\Action::make('start_work')
                    ->label('В работу')
                    ->icon('heroicon-m-play')
                    ->color('warning')
                    ->visible(fn (ProductionTask $record) => $record->status === 'pending')
                    ->action(function (ProductionTask $record) {
                        $record->update(['status' => 'in_progress']);

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
                        $order = $record->order;
                        $product = $order?->product;
                        $service = app(\App\Services\ProductionService::class);

                        if (stripos($record->operation_name, 'Заготовительная') !== false) {
                            if ($product) {
                                $targetProduct = null;

                                if ($product->type === 'detail') {
                                    $targetProduct = $product;
                                } elseif ($product->type === 'assembly') {
                                    foreach ($product->components as $component) {
                                        if (str_contains($record->operation_name, "({$component->sku})") ||
                                            str_contains($record->operation_name, "{$component->sku}")) {
                                            $targetProduct = $component;
                                            break;
                                        }
                                    }
                                }

                                $productForValidation = $targetProduct ?? $product;
                                $hasMaterials = $service->hasMaterialsInBom($productForValidation);

                                if (!$hasMaterials) {
                                    \Filament\Notifications\Notification::make()
                                        ->title('🚨 Операция заблокирована!')
                                        ->body("Для обрабатываемой детали не настроены нормы расхода материалов (BOM). Списание невозможно.")
                                        ->danger()
                                        ->send();
                                    return;
                                }

                                $service->debitMaterialsFromReserve($order, $productForValidation);
                            }
                        }

                        // Сохраняем статус текущей задачи
                        $record->update(['status' => 'completed']);

                        if ($order) {
                            if ($order->status === 'pending') {
                                $order->update(['status' => 'in_progress']);
                            }

                            // Сверяем количество незакрытых технологических этапов
                            $uncompletedTasksCount = $order->productionTasks()
                                ->where('id', '!=', $record->id)
                                ->where('status', '!=', 'completed')
                                ->count();

                            // Если все шаги закрыты — переводим сам заказ в выполненные!
                            if ($uncompletedTasksCount === 0) {
                                $order->update(['status' => 'completed']);

                                \Filament\Notifications\Notification::make()
                                    ->title('🎉 Заказ полностью готов!')
                                    ->body("Все технологические этапы выполнены. Заказ №{$order->order_number} автоматически переведен в статус 'Выполнен'.")
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
                        }
                    }),

                Tables\Actions\EditAction::make()->label('Редактировать'),
                Tables\Actions\DeleteAction::make()->label('Удалить'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
