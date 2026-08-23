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
    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Шапка заказа')
                    ->schema([
                        Forms\Components\TextInput::make('order_number')
                            ->label('Номер заказа')
                            ->placeholder('ЗП-2026-001')
                            ->unique(ignoreRecord: true)
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
                    ])->columns(3),

                Forms\Components\Section::make('Состав заказа (Позиции)')
                    ->description('Добавьте одно или несколько изделий, которые необходимо изготовить в рамках этого заказа')
                    ->schema([
                        // ДИНАМИЧЕСКАЯ ТАБЛИЦА: Позволяет добавлять неограниченное число изделий в один заказ
                        Forms\Components\Repeater::make('orderItems')
                            ->relationship('orderItems')
                            ->schema([
                                Forms\Components\Select::make('product_id')
                                    ->label('Изделие')
                                    ->options(fn () => Product::all()->mapWithKeys(function ($prod) {
                                        $typeLabel = $prod->type === 'assembly' ? '📦 Сборка' : '🔩 Деталь';
                                        return [$prod->id => "{$prod->sku} — {$prod->name} ({$typeLabel})"];
                                    }))
                                    ->searchable()
                                    ->preload()
                                    ->required(),

                                Forms\Components\TextInput::make('quantity')
                                    ->label('Количество (шт)')
                                    ->integer()
                                    ->minValue(1)
                                    ->default(1)
                                    ->required(),
                            ])
                            ->columns(2)
                            ->defaultItems(1)
                            ->addActionLabel('Добавить изделие в заказ'),
                    ]),
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

                // ИСПРАВЛЕНО: Выводим все заказанные изделия компактным списком через запятую
                Tables\Columns\TextColumn::make('orderItems.product.name')
                    ->label('Состав заказа (Изделия)')
                    ->searchable()
                    ->badge()
                    ->color('gray')
                    ->separator(', '),

                // Выводим общее суммарное количество всех штук в заказе
                Tables\Columns\TextColumn::make('orderItems')
                    ->label('Всего (шт)')
                    ->alignCenter()
                    ->state(fn (Order $record): int => $record->orderItems()->sum('quantity')),

                Tables\Columns\TextColumn::make('status')
                    ->label('Статус')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'gray',
                        'in_progress' => 'warning',
                        'completed' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => 'В очереди',
                        'in_progress' => 'В работе',
                        'completed' => 'Выполнен',
                        'cancelled' => 'Отменен',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('deadline')
                    ->label('Срок сдачи / Время')
                    ->date('d.m.Y')
                    ->sortable()
                    ->color(function (Order $record): string {
                        if (!$record->deadline || $record->status === 'completed') return 'gray';
                        return $record->deadline->isPast() ? 'danger' : 'gray';
                    })
                    ->description(function (Order $record): string|\Illuminate\Contracts\Support\Htmlable {
                        if ($record->status === 'completed') {
                            $completedDate = $record->updated_at ? $record->updated_at->format('d.m.Y') : date('d.m.Y');
                            return "🛠️ Выполнено: {$completedDate}";
                        }

                        $service = app(\App\Services\ProductionService::class);

                        // Считаем общую трудоемкость по всем позициям этого многокомпонентного заказа
                        $totalMinutes = 0;
                        foreach ($record->orderItems as $item) {
                            if ($item->product) {
                                $totalMinutes += $service->calculateProductionTimeInMinutes($item->product, $item->quantity);
                            }
                        }
                        $humanTotalTime = $service->formatMinutesToHumanTime($totalMinutes);

                        // Считаем динамический остаток незакрытых часов
                        $remainingMinutes = $service->calculateRemainingProductionTimeInMinutes($record);
                        $humanRemainingTime = $service->formatMinutesToHumanTime($remainingMinutes);

                        return new \Illuminate\Support\HtmlString("
                            <div class='text-xs space-y-0.5 text-gray-500'>
                                <div>⏱️ Общий план: {$humanTotalTime}</div>
                                <div class='text-amber-600 font-medium'>⏳ Осталось работ: {$humanRemainingTime}</div>
                            </div>
                        ");
                    }),
            ])
            ->filters([
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
            'create' => Pages\CreateOrder::route('/create'),
            'edit' => Pages\EditOrder::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return auth()->user()->hasAnyRole(['admin', 'director', 'manager', 'worker']);
    }
}
