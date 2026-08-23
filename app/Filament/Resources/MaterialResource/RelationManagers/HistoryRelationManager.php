<?php



namespace App\Filament\Resources\MaterialResource\RelationManagers; // <-- Проверьте эту строку


use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class HistoryRelationManager extends RelationManager
{
    protected static string $relationship = 'history';

    protected static ?string $title = 'История движений (Приход / Расход)';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('type')
                    ->label('Тип операции')
                    ->options([
                        'addition' => 'Приход (Добавление)',
                        'deduction' => 'Расход (Списание)',
                    ])
                    ->required()
                    ->live(),

                Forms\Components\TextInput::make('quantity')
                    ->label('Количество')
                    ->numeric()
                    ->minValue(0.0001)
                    ->required()
                    // Подхватываем метры родительского материала
                    ->suffix(fn () => $this->getOwnerRecord()->unit),

                Forms\Components\TextInput::make('description')
                    ->label('Комментарий / Основание')
                    ->placeholder('Заказ №..., Поступление от ТД Металл')
                    ->maxLength(255),
            ])->columns(1);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('type')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Дата')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('type')
                    ->label('Операция')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'addition' => 'success',
                        'deduction' => 'danger',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'addition' => '➕ Приход',
                        'deduction' => '➖ Расход',
                    }),

                Tables\Columns\TextColumn::make('quantity')
                    ->label('Количество')
                    ->weight('bold')
                    ->suffix(fn () => " " . $this->getOwnerRecord()->unit),

                Tables\Columns\TextColumn::make('description')
                    ->label('Комментарий')
                    ->placeholder('—'),
            ])
            ->filters([])
            ->headerActions([
                // Кнопка создания записи истории прямо из карточки материала
                Tables\Actions\CreateAction::make()
                    ->label('Добавить операцию')
                    ->modalHeading('Регистрация движения материала')
                    ->after(function ($record) {
                        // АВТОМАТИЧЕСКИЙ ПЕРЕСЧЕТ ОСТАТКА НА СКЛАДЕ
                        $material = $this->getOwnerRecord();

                        if ($record->type === 'addition') {
                            $material->increment('quantity', $record->quantity);
                        } else {
                            $material->decrement('quantity', $record->quantity);
                        }
                    }),
            ])
            ->actions([
                Tables\Actions\DeleteAction::make()
                    ->label('Удалить')
                    // Если ошиблись и удалили запись из истории — остаток вернется назад
                    ->after(function ($record) {
                        $material = $this->getOwnerRecord();
                        if ($record->type === 'addition') {
                            $material->decrement('quantity', $record->quantity);
                        } else {
                            $material->increment('quantity', $record->quantity);
                        }
                    }),
            ]);
    }
}
