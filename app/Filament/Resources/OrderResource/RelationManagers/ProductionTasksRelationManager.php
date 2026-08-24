<?php

namespace App\Filament\Resources\OrderResource\RelationManagers;

use App\Models\Order;
use App\Models\Product;
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
                        $service = app(\App\Services\ProductionService::class);

                        if ($order && stripos($record->operation_name, 'Заготовительная') !== false) {
                            // ИСПРАВЛЕНО: $order->product всегда null для многопозиционных заказов
                            // (product_id больше не заполняется формой заказа, см. OrderResource::form()
                            // и orderItems-репитер). Ищем нужную деталь по SKU, зашитому в operation_name,
                            // обходя все позиции заказа и, рекурсивно, все компоненты сборок.
                            $targetProduct = $this->resolveProductForTask($order, $record->operation_name);

                            if (!$targetProduct) {
                                \Filament\Notifications\Notification::make()
                                    ->title('🚨 Операция заблокирована!')
                                    ->body('Не удалось определить деталь по названию операции — списание материала невозможно. Проверьте, что operation_name содержит артикул (SKU) детали.')
                                    ->danger()
                                    ->send();

                                return;
                            }

                            $hasMaterials = $service->hasMaterialsInBom($targetProduct);

                            if (!$hasMaterials) {
                                \Filament\Notifications\Notification::make()
                                    ->title('🚨 Операция заблокирована!')
                                    ->body('Для обрабатываемой детали не настроены нормы расхода материалов (BOM). Списание невозможно.')
                                    ->danger()
                                    ->send();

                                return;
                            }

                            $service->debitMaterialsFromReserve($order, $targetProduct);
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

    /**
     * Находит конкретную деталь (Product), к которой относится технологическая задача,
     * сопоставляя её SKU с текстом operation_name (в него SKU зашивается при генерации
     * задач в CreateOrder::generateTasksForProduct(), например "... (чёртеж SKU-123)").
     *
     * Обходит все позиции многокомпонентного заказа (orderItems) и, если позиция —
     * сборка, рекурсивно все входящие в неё компоненты.
     */
    protected function resolveProductForTask(Order $order, string $operationName): ?Product
    {
        foreach ($order->orderItems as $item) {
            if (!$item->product) {
                continue;
            }

            $found = $this->findProductBySkuInText($item->product, $operationName);

            if ($found) {
                return $found;
            }
        }

        return null;
    }

    protected function findProductBySkuInText(Product $product, string $text): ?Product
    {
        if ($product->sku && str_contains($text, (string) $product->sku)) {
            return $product;
        }

        if ($product->type === 'assembly') {
            foreach ($product->components as $component) {
                $found = $this->findProductBySkuInText($component, $text);

                if ($found) {
                    return $found;
                }
            }
        }

        return null;
    }
}
