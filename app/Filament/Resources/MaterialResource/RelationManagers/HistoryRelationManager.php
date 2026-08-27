<?php

namespace App\Filament\Resources\MaterialResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class HistoryRelationManager extends RelationManager
{
    protected static string $relationship = 'histories';

    protected static ?string $title = 'История движения и складские операции';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('type')
                    ->label('Тип операции')
                    ->options([
                        'addition' => '📥 Приход (Пополнение склада)',
                        'deduction' => '📤 Расход (Ручное списание)',
                    ])
                    ->required(),

                Forms\Components\TextInput::make('quantity')
                    ->label('Количество')
                    ->numeric()
                    ->minValue(0.0001)
                    ->required()
                    // ИСПРАВЛЕНО: раньше суффикс брался из сохранённого поля unit
                    // (Hidden-поле в MaterialResource), которое могло сохраниться
                    // некорректно и показывать "м" для плиты вместо "м²". Теперь
                    // единица измерения вычисляется напрямую по типу материала —
                    // так же, как в основном списке материалов.
                    ->suffix(fn () => ' ' . match ($this->getOwnerRecord()->name) {
                        'Плита' => 'м²',
                        'Покупное изделие' => 'шт',
                        default => 'м',
                    }),

                Forms\Components\TextInput::make('description')
                    ->label('Основание / Комментарий')
                    ->placeholder('Накладная №123 / Исправление пересортицы')
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

                Tables\Columns\TextColumn::make('type')
                    ->label('Операция')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'addition' ? 'success' : 'danger')
                    ->formatStateUsing(fn (string $state) => $state === 'addition' ? '📥 Приход' : '📤 Расход'),

                Tables\Columns\TextColumn::make('quantity')
                    ->label('Объем')
                    ->weight('bold')
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
                    ->label('Добавить складскую операцию')
                    // МАГИЯ АВТОПЕРЕСЧЕТА: Выполняется в БД прямо в момент нажатия кнопки "Создать"
                    ->after(function (\App\Models\MaterialHistory $record) {
                        // Берем открытый в данный момент материал на складе
                        $material = $record->material;
                        $quantity = floatval($record->quantity);

                        if ($record->type === 'addition') {
                            // Если это приход — физически увеличиваем остаток на складе
                            $material->increment('quantity', $quantity);
                        } elseif ($record->type === 'deduction') {
                            // Если это ручной расход — физически уменьшаем остаток на складе
                            $material->decrement('quantity', $quantity);
                        }
                    }),
            ]);

    }
}
