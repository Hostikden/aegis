<?php

namespace App\Filament\Resources\MaterialResource\RelationManagers;

use App\Models\MaterialLot;
use App\Services\MaterialReceiptService;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Партии материала с прослеживаемостью до плавки/сертификата. Партии
 * создаются исключительно проведением документа "Поступление материалов"
 * (ReceiptResource) — вручную здесь их не добавляют и не редактируют, чтобы
 * остаток веса и метража партии никогда не разошёлся с тем, что реально
 * проведено по складу. Единственное действие здесь — решение по партиям,
 * оставшимся в карантине после входного контроля.
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
            ->actions([
                Tables\Actions\Action::make('approve_quarantine')
                    ->label('Разрешить к использованию')
                    ->icon('heroicon-m-check-circle')
                    ->color('success')
                    ->visible(fn (MaterialLot $record) => $record->status === 'quarantine')
                    ->form([
                        Forms\Components\Textarea::make('comment')
                            ->label('Основание (например, согласие заказчика)')
                            ->required(),
                    ])
                    ->requiresConfirmation()
                    ->modalHeading('Разрешить партию к использованию?')
                    ->modalDescription('Остаток материала увеличится на количество этой партии — она станет доступна для резервирования в заказах.')
                    ->action(function (MaterialLot $record, array $data) {
                        app(MaterialReceiptService::class)->resolveQuarantineLot($record, true, $data['comment']);

                        Notification::make()
                            ->title('Партия разрешена к использованию')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('create_nonconformity')
                    ->label('Оформить акт')
                    ->icon('heroicon-m-document-exclamation')
                    ->color('danger')
                    ->visible(fn (MaterialLot $record) => in_array($record->status, ['quarantine', 'rejected']))
                    ->url(fn (MaterialLot $record) => \App\Filament\Resources\NonconformityResource::getUrl('create', [
                        'material_lot_id' => $record->id,
                        'category' => 'other',
                    ])),

                Tables\Actions\Action::make('reject_quarantine')
                    ->label('Забраковать окончательно')
                    ->icon('heroicon-m-x-circle')
                    ->color('danger')
                    ->visible(fn (MaterialLot $record) => $record->status === 'quarantine')
                    ->form([
                        Forms\Components\Textarea::make('comment')
                            ->label('Причина окончательной браковки')
                            ->required(),
                    ])
                    ->requiresConfirmation()
                    ->action(function (MaterialLot $record, array $data) {
                        app(MaterialReceiptService::class)->resolveQuarantineLot($record, false, $data['comment']);

                        Notification::make()
                            ->title('Партия окончательно забракована')
                            ->body('Рекомендуем оформить акт о несоответствии для этой партии (будет доступно на Этапе 4).')
                            ->warning()
                            ->send();
                    }),
            ])
            ->bulkActions([]);
    }
}
