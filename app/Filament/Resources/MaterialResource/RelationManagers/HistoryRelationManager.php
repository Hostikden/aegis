<?php

namespace App\Filament\Resources\MaterialResource\RelationManagers;

use App\Models\MaterialLot;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * ИСПРАВЛЕНО: раньше здесь можно было и добавлять остаток ("Приход"), и
 * списывать его — оба варианта трогали только общий Material::quantity,
 * ничего не зная про партии. Из-за этого сумма остатков по партиям рано или
 * поздно расходилась с общим остатком материала:
 *  - ручной "приход" создавал остаток без плавки/сертификата, то есть заново
 *    возвращал ту же проблему прослеживаемости, которую и решают партии;
 *  - ручной "расход" уменьшал общий остаток, но ни у одной партии не убавлял
 *    remaining_native_quantity — сумма по партиям становилась БОЛЬШЕ, чем
 *    реальный остаток.
 *
 * Теперь: "Приход" полностью убран — единственный способ добавить материал
 * на склад официально — это проведённый документ поступления (ReceiptResource),
 * там партия создаётся всегда, без исключений. "Расход" остался только для
 * ручных корректировок (пересортица, порча вне производства и т.п.), но
 * теперь обязан списывать остаток с конкретной партии — так остаток партии
 * и общий остаток материала больше никогда не расходятся.
 */
class HistoryRelationManager extends RelationManager
{
    protected static string $relationship = 'histories';

    protected static ?string $title = 'История списаний (ручная корректировка остатка)';

    public function form(Form $form): Form
    {
        $hasLots = $this->getOwnerRecord()->lots()->where('status', 'available')->where('remaining_native_quantity', '>', 0)->exists();

        return $form
            ->schema([
                Forms\Components\Hidden::make('type')->default('deduction'),

                Forms\Components\Placeholder::make('no_lots_warning')
                    ->label('')
                    ->content('⚠️ У этого материала нет ни одной партии с остатком. Списать без привязки к партии можно, но это создаст точно такое же расхождение, от которого мы уходим. Рекомендуется сначала нажать "Завести партию" в списке материалов (если остаток ещё из версии 1) или провести документ поступления.')
                    ->visible(!$hasLots),

                Forms\Components\Select::make('material_lot_id')
                    ->label('Партия, с которой списываем')
                    ->options(fn () => $this->getOwnerRecord()->lots()
                        ->where('status', 'available')
                        ->where('remaining_native_quantity', '>', 0)
                        ->orderBy('received_at')
                        ->get()
                        ->mapWithKeys(fn (MaterialLot $lot) => [
                            $lot->id => "{$lot->lot_number} — остаток " . number_format($lot->remaining_native_quantity, 2, ',', ' ') . " {$lot->native_unit}"
                                . ($lot->melt_number ? ", плавка {$lot->melt_number}" : ' (без плавки — начальный остаток)'),
                        ]))
                    ->searchable()
                    ->live()
                    ->required($hasLots)
                    ->visible($hasLots)
                    ->helperText('Партии отсортированы по дате поступления — сверху самая старая (списывайте в первую очередь именно с неё, принцип FIFO)'),

                Forms\Components\TextInput::make('quantity')
                    ->label('Количество')
                    ->numeric()
                    ->minValue(0.0001)
                    ->required()
                    // Не даём списать больше, чем реально осталось в выбранной партии —
                    // без этой проверки remaining_native_quantity партии мог бы уйти в минус.
                    ->maxValue(function (Get $get) {
                        $lotId = $get('material_lot_id');
                        if (!$lotId) {
                            return null;
                        }
                        return MaterialLot::find($lotId)?->remaining_native_quantity;
                    })
                    ->suffix(fn () => ' ' . match ($this->getOwnerRecord()->name) {
                        'Плита' => 'м²',
                        'Покупное изделие' => 'шт',
                        default => 'м',
                    }),

                Forms\Components\TextInput::make('description')
                    ->label('Основание / Комментарий')
                    ->placeholder('Исправление пересортицы / Порча при хранении')
                    ->maxLength(255)
                    ->required(),
            ])->columns(1);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('description')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Дата и время')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('lot.lot_number')
                    ->label('Партия')
                    ->placeholder('— (без привязки к партии)')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('quantity')
                    ->label('Списано')
                    ->weight('bold')
                    ->color('danger')
                    ->suffix(fn () => ' ' . match ($this->getOwnerRecord()->name) {
                        'Плита' => 'м²',
                        'Покупное изделие' => 'шт',
                        default => 'м',
                    }),

                Tables\Columns\TextColumn::make('description')
                    ->label('Основание / Комментарий')
                    ->searchable(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Списать вручную')
                    ->after(function (\App\Models\MaterialHistory $record) {
                        $material = $record->material;
                        $quantity = floatval($record->quantity);

                        if ($record->material_lot_id) {
                            // Списываем именно с выбранной партии — её остаток и общий
                            // остаток материала уменьшаются синхронно, расхождения не будет.
                            $lot = $record->lot;
                            $lot?->debitNativeQuantity($quantity);
                        }

                        // Общий остаток материала уменьшаем в любом случае (в том числе
                        // для материалов, у которых партий ещё нет вообще) — это
                        // единственное поле, которым пользуется резервирование в заказах.
                        $material->decrement('quantity', $quantity);
                    }),
            ]);
    }
}
