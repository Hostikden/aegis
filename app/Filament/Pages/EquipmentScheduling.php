<?php

namespace App\Filament\Pages;

use App\Filament\Resources\OrderResource;
use App\Models\ProductionTask;
use App\Services\ProductionService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

/**
 * Планирование очерёдности изготовления деталей по типу оборудования.
 *
 * Показывает единую очередь технологических задач конкретного типа
 * (Фрезерная, Токарная и т.д.) СРАЗУ ПО ВСЕМ заказам — то есть отвечает на
 * вопрос "что и в каком порядке должно встать на фрезерный станок дальше",
 * а не "что нужно сделать по конкретному заказу" (это уже есть на странице
 * заказа). Порядок можно менять перетаскиванием строк — используется
 * встроенный механизм сортировки таблиц Filament (queue_position).
 */
class EquipmentScheduling extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-queue-list';
    protected static ?string $navigationLabel = 'Планирование по оборудованию';
    protected static ?string $title = 'Планирование по оборудованию';
    protected static ?int $navigationSort = 5;

    protected static string $view = 'filament.pages.equipment-scheduling';

    /**
     * Тот же список типов операций, что и в ProductResource — держим синхронно,
     * чтобы фильтр всегда предлагал полный набор станков/участков, даже если
     * по какому-то из них пока нет ни одной задачи в очереди.
     */
    public static function equipmentTypeOptions(): array
    {
        return [
            'Заготовительная' => '🪓 Заготовительная',
            'Токарная' => '🌀 Токарная',
            'Фрезерная' => '🪵 Фрезерная',
            'Токарно-фрезерная' => '🔄 Токарно-фрезерная',
            'Электроэрозия' => '⚡ Электроэрозия',
            'Слесарная' => '🪛 Слесарная',
            'Сварочная' => '👨‍🏭 Сварочная',
            '3D печать' => '🖨️ 3D печать',
            'Подряд' => '🚚 Подряд (Сторонние работы)',
            'ОТК' => '🔍 Контроль (ОТК)',
            'Сборка' => '📦 Сборка',
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                ProductionTask::query()
                    ->where('status', '!=', 'completed')
                    ->whereNotNull('equipment_type')
            )
            // Сортировка ИМЕННО по очереди — без этого drag-and-drop не имеет смысла.
            ->defaultSort('queue_position')
            // Включаем перетаскивание строк мышкой — Filament сам обновляет
            // queue_position у всех задач при перестановке.
            ->reorderable('queue_position')
            // Перетаскивание должно работать по всей очереди сразу, а не только
            // в пределах одной страницы — поэтому отключаем пагинацию здесь.
            ->paginated(false)
            ->columns([
                Tables\Columns\TextColumn::make('item_number')
                    ->label('Item')
                    ->badge()
                    ->color('info')
                    ->fontFamily('mono'),

                Tables\Columns\TextColumn::make('order.order_number')
                    ->label('№ Заказа')
                    ->fontFamily('mono'),

                Tables\Columns\TextColumn::make('order.deadline')
                    ->label('Дедлайн')
                    ->date('d.m.Y')
                    ->color(fn (ProductionTask $record): string => ($record->order?->deadline?->isPast() && $record->order?->status !== 'completed')
                        ? 'danger'
                        : 'gray'),

                Tables\Columns\TextColumn::make('operation_name')
                    ->label('Деталь / операция')
                    ->weight('medium')
                    ->wrap()
                    // Убираем служебный префикс "🌟 Item: N |" — номер итема уже
                    // выведен отдельной колонкой item_number (тот же приём, что
                    // и на главной инфопанели цеха).
                    ->formatStateUsing(fn (string $state): string => trim(preg_replace('/^[^\|]*\|\s*/u', '', $state))),

                Tables\Columns\TextColumn::make('quantity_to_do')
                    ->label('План, шт')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('planned_minutes')
                    ->label('Трудоёмкость')
                    ->alignCenter()
                    ->formatStateUsing(fn (float $state): string => number_format($state / 60, 1, ',', ' ') . ' ч.'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Статус')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'gray',
                        'in_progress' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => '⏳ В очереди',
                        'in_progress' => '⚙️ В работе',
                        default => $state,
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('equipment_type')
                    ->label('Тип оборудования')
                    ->options(self::equipmentTypeOptions())
                    ->default('Фрезерная')
                    // Фильтр обязателен: без выбранного типа очередь не имеет смысла
                    // (порядок задач разных станков друг с другом не связан).
                    ->query(function ($query, array $data) {
                        $value = $data['value'] ?? 'Фрезерная';

                        return $query->where('equipment_type', $value);
                    }),
            ], layout: Tables\Enums\FiltersLayout::AboveContent)
            ->filtersFormColumns(1)
            ->actions([
                Tables\Actions\Action::make('scheduling_start')
                    ->label('В работу')
                    ->icon('heroicon-m-play')
                    ->color('warning')
                    ->visible(fn (ProductionTask $record) => $record->status === 'pending')
                    ->action(function (ProductionTask $record) {
                        $record->update([
                            'status' => 'in_progress',
                            'started_at' => $record->started_at ?? now(),
                        ]);

                        $order = $record->order;
                        if ($order && $order->status === 'pending') {
                            $order->update(['status' => 'in_progress']);
                        }
                    }),

                Tables\Actions\Action::make('scheduling_complete')
                    ->label('Выполнить')
                    ->icon('heroicon-m-check-circle')
                    ->color('success')
                    ->visible(fn (ProductionTask $record) => $record->status === 'in_progress')
                    ->requiresConfirmation()
                    ->modalHeading('✅ Завершение технологического этапа')
                    ->action(function (ProductionTask $record) {
                        $service = app(ProductionService::class);
                        $result = $service->completeProductionTask($record);

                        if (!$result['success']) {
                            Notification::make()
                                ->title('🚨 Операция заблокирована!')
                                ->body($result['error'])
                                ->danger()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title('Успешно')
                            ->body($result['order_completed']
                                ? "Все этапы заказа №{$result['order']->order_number} выполнены — заказ закрыт."
                                : 'Операция успешно завершена!')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('scheduling_view_order')
                    ->label('Открыть заказ')
                    ->icon('heroicon-m-eye')
                    ->color('gray')
                    ->url(fn (ProductionTask $record): string => OrderResource::getUrl('edit', ['record' => $record->order_id])),
            ]);
    }
}
