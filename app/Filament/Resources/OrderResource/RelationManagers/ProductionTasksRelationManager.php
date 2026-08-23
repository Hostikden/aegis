<?php

namespace App\Filament\Resources\OrderResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ProductionTasksRelationManager extends RelationManager
{
    protected static string $relationship = 'productionTasks';

    protected static ?string $title = 'Технологические этапы выполнения';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('operation_name')
                    ->label('Название технологического этапа')
                    ->required()
                    ->maxLength(255),

                Forms\Components\TextInput::make('quantity_to_do')
                    ->label('Количество к выполнению (шт)')
                    ->integer()
                    ->required()
                    ->default(1),

                Forms\Components\Select::make('status')
                    ->label('Текущий статус этапа')
                    ->options([
                        'pending' => '⏳ В очереди',
                        'in_progress' => '⚙️ В работе',
                        'completed' => '✅ Выполнен',
                    ])
                    ->default('pending')
                    ->required(),
            ])->columns(1);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('operation_name')
            ->columns([
                Tables\Columns\TextColumn::make('operation_name')
                    ->label('Технологическая операция / Задача')
                    ->weight('medium')
                    ->searchable(),

                Tables\Columns\TextColumn::make('quantity_to_do')
                    ->label('План (шт)')
                    ->alignCenter(),

                Tables\Columns\SelectColumn::make('status')
                    ->label('Статус операции')
                    ->options([
                        'pending' => '⏳ В очереди',
                        'in_progress' => '⚙️ В работе',
                        'completed' => '✅ Выполнен',
                    ])
                    ->selectablePlaceholder(false),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Обновлено')
                    ->dateTime('d.m.Y H:i')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Добавить нестандартный этап'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->label('Редактировать'),
                Tables\Actions\DeleteAction::make()->label('Удалить'),
            ]);
    }
}
