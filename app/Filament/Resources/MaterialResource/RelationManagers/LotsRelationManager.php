<?php

namespace App\Filament\Resources\MaterialResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Партии материала с прослеживаемостью до плавки/сертификата. Только для
 * просмотра — партии создаются исключительно проведением документа
 * "Поступление материалов" (ReceiptResource), вручную здесь не добавляются
 * и не редактируются, чтобы остаток веса и метража партии никогда не
 * разошёлся с тем, что реально проведено по складу.
 */
class LotsRelationManager extends RelationManager
{
    protected static string $relationship = 'lots';

    protected static ?string $title = 'Партии (плавка, сертификат, остатки)';

    protected static ?string $recordTitleAttribute = 'lot_number';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('lot_number')
                    ->label('№ партии')
                    ->weight('bold')
                    ->searchable(),

                Tables\Columns\TextColumn::make('melt_number')
                    ->label('№ плавки')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('certificate_number')
                    ->label('№ сертификата')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('weight_kg')
                    ->label('Вес поступления, кг')
                    ->suffix(' кг'),

                Tables\Columns\TextColumn::make('remaining_weight_kg')
                    ->label('Остаток, кг')
                    ->weight('bold')
                    ->suffix(' кг')
                    ->color(fn ($record) => $record->remaining_weight_kg <= 0 ? 'danger' : 'success'),

                Tables\Columns\TextColumn::make('remaining_native_quantity')
                    ->label('Остаток в цеховых единицах')
                    ->formatStateUsing(fn ($state, $record) => number_format($state, 2, ',', ' ') . ' ' . $record->native_unit),

                Tables\Columns\TextColumn::make('status')
                    ->label('Статус')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'available' => 'success',
                        'quarantine' => 'warning',
                        'rejected' => 'danger',
                        'depleted' => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'available' => '✅ Доступна',
                        'quarantine' => '⏳ Карантин',
                        'rejected' => '🚫 Забракована',
                        'depleted' => '⬜ Исчерпана',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('received_at')
                    ->label('Дата поступления')
                    ->date('d.m.Y')
                    ->sortable(),
            ])
            ->defaultSort('received_at', 'desc')
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}
