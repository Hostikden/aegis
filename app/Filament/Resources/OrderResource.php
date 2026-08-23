<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderResource\Pages;
use App\Filament\Resources\OrderResource\RelationManagers;
use App\Models\Order;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationLabel = 'Заказы на производство';
    protected static ?string $modelLabel = 'Заказ';
    protected static ?string $pluralModelLabel = 'Заказы';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Информация о заказе')
                    ->schema([
                        Forms\Components\TextInput::make('order_number')
                            ->label('Номер заказа')
                            ->placeholder('ЗП-2026-001')
                            ->unique(ignoreRecord: true)
                            ->required(),

                        Forms\Components\Select::make('product_id')
                            ->label('Изделие для производства')
                            ->options(fn () => Product::all()->mapWithKeys(function ($prod) {
                                $typeLabel = $prod->type === 'assembly' ? '📦 Сборка' : '🔩 Деталь';
                                return [$prod->id => "{$prod->sku} — {$prod->name} ({$typeLabel})"];
                            }))
                            ->searchable()
                            ->preload()
                            ->required(),

                        Forms\Components\TextInput::make('total_quantity')
                            ->label('Количество к производству (шт)')
                            ->integer()
                            ->minValue(1)
                            ->default(1)
                            ->required(),

                        Forms\Components\Select::make('status')
                            ->label('Статус заказа')
                            ->options([
                                'pending' => '⏳ В очереди',
                                'in_progress' => '⚙️ В производстве',
                                'completed' => '✅ Выполнен',
                                'cancelled' => '❌ Отменен',
                            ])
                            ->default('pending')
                            ->required(),

                        Forms\Components\DatePicker::make('deadline')
                            ->label('Срок сдачи (Дедлайн)')
                            ->native(false)
                            ->displayFormat('d.m.Y')
                            ->required(),
                    ])->columns(2),
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
                    ->fontFamily('mono'),

                Tables\Columns\TextColumn::make('product.name')
                    ->label('Изделие')
                    ->searchable()
                    ->description(fn (Order $record): string => "Артикул: " . ($record->product->sku ?? '-')),

                Tables\Columns\TextColumn::make('total_quantity')
                    ->label('Кол-во (шт)')
                    ->alignCenter()
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Статус')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'gray',
                        'in_progress' => 'warning',
                        'completed' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray', // Защита от unhandled case для цвета
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => 'В очереди',
                        'in_progress' => 'В работе',
                        'completed' => 'Выполнен',
                        'cancelled' => 'Отменен',
                        default => $state, // Защита от unhandled case для текста
                    }),

                Tables\Columns\TextColumn::make('deadline')
                    ->label('Срок сдачи')
                    ->date('d.m.Y')
                    ->sortable()
                    ->color(fn (Order $record): string =>
                        $record->deadline && $record->deadline->isPast() && $record->status !== 'completed' ? 'danger' : 'gray'
                    ),
            ])
            ->filters([
                // ИСПРАВЛЕНО: Явно прописали опции для фильтра, убрав любые скрытые вызовы match фреймворка
                Tables\Filters\SelectFilter::make('status')
                    ->label('Фильтр по статусу')
                    ->options([
                        'pending' => '⏳ В очереди',
                        'in_progress' => '⚙️ В работе',
                        'completed' => '✅ Выполнен',
                        'cancelled' => '❌ Отменен',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }


    public static function getRelations(): array
    {
        return [
            RelationManagers\ProductionTasksRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'create' => Pages\CreateOrder::route('/create'), // ИСПРАВЛЕНО: теперь ссылается на CreateOrder
            'edit' => Pages\EditOrder::route('/{record}/edit'),
        ];
    }


    public static function canViewAny(): bool
    {
        return auth()->user()->hasAnyRole(['admin', 'director', 'manager', 'worker']);
    }
}
