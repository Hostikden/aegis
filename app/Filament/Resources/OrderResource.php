<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderResource\Pages;
use App\Models\Order;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';
    protected static ?string $navigationLabel = 'Заказы в производство';
    protected static ?string $modelLabel = 'Заказ';
    protected static ?string $pluralModelLabel = 'Заказы';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Параметры производственного заказа')
                    ->schema([
                        Forms\Components\TextInput::make('order_number')
                            ->label('Номер заказа')
                            ->placeholder('ЗНП-001')
                            ->required()
                            ->unique(ignoreRecord: true),

                        Forms\Components\Select::make('product_id')
                            ->label('Изделие для производства')
                            ->options(Product::pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->required(),

                        Forms\Components\TextInput::make('total_quantity')
                            ->label('Количество (шт)')
                            ->numeric()
                            ->minValue(1)
                            ->required(),

                        Forms\Components\DatePicker::make('deadline')
                            ->label('Срок сдачи')
                            ->required(),

                        Forms\Components\Select::make('status')
                            ->label('Статус')
                            ->options([
                                'pending' => 'Черновик / Ожидание',
                                'in_progress' => 'В производстве',
                                'completed' => 'Выполнен',
                                'shipped' => 'Отгружен',
                            ])
                            ->default('pending')
                            ->disabled() // Статус лучше менять через кнопки действий, а не вручную
                            ->required(),
                    ])->columns(2)
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('order_number')
                    ->label('№ Заказа')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('product.name')
                    ->label('Изделие')
                    ->searchable(),

                Tables\Columns\TextColumn::make('total_quantity')
                    ->label('Кол-во (шт)')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Статус')
                    ->badge()
                    ->colors([
                        'gray' => 'pending',
                        'warning' => 'in_progress',
                        'success' => 'completed',
                        'info' => 'shipped',
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => 'Ожидание',
                        'in_progress' => 'В работе',
                        'completed' => 'Готов',
                        'shipped' => 'Отгружен',
                    }),

                Tables\Columns\TextColumn::make('deadline')
                    ->label('Срок сдачи')
                    ->date('d.m.Y')
                    ->sortable(),
            ])
            ->filters([])
            ->actions([
                // Кнопка запуска в работу с проверкой и списанием металла
                Tables\Actions\Action::make('start_production')
                    ->label('Запустить в работу')
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->visible(fn (Order $record) => $record->status === 'pending')
                    ->requiresConfirmation()
                    ->action(function (Order $record) {
                        $product = $record->product;

                        // 1. Проверяем наличие материалов по спецификации (BOM)
                        foreach ($product->productMaterials as $pm) {
                            $requiredAmount = $pm->consumption_rate * $record->total_quantity;
                            $material = $pm->material;

                            if ($material->quantity < $requiredAmount) {
                                Notification::make()
                                    ->title('Ошибка запуска')
                                    ->body("Недостаточно металла на складе: {$material->name} ({$material->grade}). Требуется: {$requiredAmount} {$material->unit}, в наличии: {$material->quantity} {$material->unit}.")
                                    ->danger()
                                    ->send();
                                return;
                            }
                        }

                        // 2. Если металла хватает — списываем его со склада
                        foreach ($product->productMaterials as $pm) {
                            $requiredAmount = $pm->consumption_rate * $record->total_quantity;
                            $pm->material->decrement('quantity', $requiredAmount);
                        }

                        // 3. Создаем базовые задачи для цеха (например, Резка и Сборка)
                        $record->productionTasks()->createMany([
                            ['operation_name' => 'Заготовка / Резка металла', 'quantity_to_do' => $record->total_quantity],
                            ['operation_name' => 'Гибка / Слесарная обработка', 'quantity_to_do' => $record->total_quantity],
                            ['operation_name' => 'Сварка / Сборка изделия', 'quantity_to_do' => $record->total_quantity],
                        ]);

                        // 4. Обновляем статус заказа
                        $record->update(['status' => 'in_progress']);

                        Notification::make()
                            ->title('Заказ запущен')
                            ->body('Металл успешно списан со склада, задачи для цехов сформированы.')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'create' => Pages\CreateOrder::route('/create'),
            'edit' => Pages\EditOrder::route('/{record}/edit'),
        ];
    }
    public static function canViewAny(): bool
{
    // Разрешить просмотр только админам и менеджерам. Операторы (workers) не увидят этот раздел в меню.
    return auth()->user()->hasAnyRole(['admin', 'manager']);
}

}
