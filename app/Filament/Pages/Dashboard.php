<?php

namespace App\Filament\Pages;

use App\Models\ProductionTask;
use App\Filament\Resources\OrderResource;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Section;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;

class Dashboard extends BaseDashboard implements HasTable
{
    use InteractsWithTable;

    protected static ?string $title = 'Оперативный мониторинг цеха';
    protected static ?string $navigationLabel = 'Инфопанель';
    protected static string $view = 'filament.pages.dashboard';

    /**
     * Конструктор поисковой таблицы и фильтров на главной странице
     */
    public function table(Table $table): Table
    {
        return $table
            ->query(ProductionTask::query()->latest())
            ->columns([
                Tables\Columns\TextColumn::make('item_number')
                    ->label('ID Итема (Чертёж)')
                    ->badge()
                    ->color('info')
                    ->fontFamily('mono')
                    ->sortable(),

                Tables\Columns\TextColumn::make('order.order_number')
                    ->label('№ Заказа')
                    ->fontFamily('mono')
                    ->sortable(),

                Tables\Columns\TextColumn::make('operation_name')
                    ->label('Технологическая операция / Задача цеха')
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
            ->filters([
                Tables\Filters\Filter::make('task_search_filter')
                    ->form([
                        Section::make('Панель быстрого поиска по цеху')
                            ->description('Заполните любое поле для мгновенной фильтрации технологических задач и чертежей')
                            ->schema([
                                Grid::make(4) // 4 поля ввода встанут строго в один горизонтальный ряд
                                    ->schema([
                                        TextInput::make('order_number')
                                            ->label('Номер заказа')
                                            ->placeholder('ЗП-2026-001')
                                            ->prefixIcon('heroicon-m-document-text'),

                                        TextInput::make('item_number')
                                            ->label('Номер итема (ID)')
                                            ->placeholder('10001')
                                            ->numeric()
                                            ->prefixIcon('heroicon-m-star'),

                                        TextInput::make('product_name')
                                            ->label('Название готового изделия')
                                            ->placeholder('Кронштейн / Вал')
                                            ->prefixIcon('heroicon-m-cube'),

                                        TextInput::make('operation_keyword')
                                            ->label('Название детали / Операция')
                                            ->placeholder('Токарная / Корпус')
                                            ->prefixIcon('heroicon-m-cog-6-tooth'),
                                    ]),
                            ]),
                    ])
                    ->query(function (\Illuminate\Database\Eloquent\Builder $query, array $data): \Illuminate\Database\Eloquent\Builder {
                        return $query
                            ->when(
                                $data['order_number'],
                                fn ($q, $val) => $q->whereHas('order', fn ($inner) => $inner->where('order_number', 'like', "%{$val}%"))
                            )
                            ->when(
                                $data['item_number'],
                                fn ($q, $val) => $q->where('item_number', $val)
                            )
                            ->when(
                                $data['product_name'],
                                fn ($q, $val) => $q->whereHas('order.product', fn ($inner) => $inner->where('name', 'like', "%{$val}%"))
                            )
                            ->when(
                                $data['operation_keyword'],
                                fn ($q, $val) => $q->where('operation_name', 'like', "%{$val}%")
                            );
                    })
            ], layout: Tables\Enums\FiltersLayout::AboveContent)
            // ИСПРАВЛЕНО: Растягиваем контейнер фильтров на всю ширину (убираем сжатие в 1/3 часть слева)
            ->filtersFormColumns(1)
            ->actions([
                Tables\Actions\Action::make('dashboard_start')
                    ->label('В работу')
                    ->icon('heroicon-m-play')
                    ->color('warning')
                    ->visible(fn (ProductionTask $record) => $record->status === 'pending')
                    ->action(fn (ProductionTask $record) => $record->update(['status' => 'in_progress'])),

                Tables\Actions\Action::make('view_order')
                    ->label('Открыть заказ')
                    ->icon('heroicon-m-eye')
                    ->color('gray')
                    ->url(fn (ProductionTask $record): string => OrderResource::getUrl('edit', ['record' => $record->order_id])),
            ]);
    }

    public function getWidgets(): array
    {
        return [];
    }
}
