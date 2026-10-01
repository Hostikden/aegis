<?php

namespace App\Filament\Resources;

use App\Filament\Resources\NonconformityResource\Pages;
use App\Models\Nonconformity;
use App\Models\ReceiptLine;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class NonconformityResource extends Resource
{
    protected static ?string $model = Nonconformity::class;

    protected static ?string $navigationIcon = 'heroicon-o-exclamation-triangle';
    protected static ?string $navigationLabel = 'Акты о несоответствии';
    protected static ?string $modelLabel = 'Акт о несоответствии';
    protected static ?string $pluralModelLabel = 'Акты о несоответствии';
    protected static ?string $navigationGroup = 'Входной контроль';
    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Акт о несоответствии')
                    ->schema([
                        Forms\Components\TextInput::make('number')
                            ->label('Номер акта')
                            ->default(fn () => Nonconformity::generateNextNumber())
                            ->disabled()
                            ->dehydrated()
                            ->required(),

                        Forms\Components\Select::make('material_lot_id')
                            ->label('Партия материала')
                            ->relationship('lot', 'lot_number')
                            ->default(fn () => request()->query('material_lot_id'))
                            ->searchable()
                            ->preload()
                            ->helperText('Заполните это ИЛИ строку поступления ниже — смотря на каком этапе обнаружен дефект'),

                        Forms\Components\Select::make('receipt_line_id')
                            ->label('Строка поступления (если партия ещё не создана)')
                            ->relationship('receiptLine', 'id')
                            ->default(fn () => request()->query('receipt_line_id'))
                            ->getOptionLabelFromRecordUsing(fn (ReceiptLine $record) => "{$record->receipt->receipt_number} — {$record->material->name} {$record->material->grade}")
                            ->searchable()
                            ->preload(),

                        Forms\Components\Select::make('category')
                            ->label('Категория несоответствия')
                            ->options(Nonconformity::CATEGORIES)
                            ->default(fn () => request()->query('category'))
                            ->required(),

                        Forms\Components\TextInput::make('ntd_reference')
                            ->label('Пункт ГОСТ / ТУ, которому не соответствует')
                            ->placeholder('ГОСТ 5632-2014, п. 3.2'),

                        Forms\Components\TextInput::make('affected_weight_kg')
                            ->label('Затронутая масса, кг')
                            ->numeric(),

                        Forms\Components\Textarea::make('description')
                            ->label('Описание несоответствия')
                            ->default(fn () => request()->query('description'))
                            ->required()
                            ->rows(3)
                            ->columnSpanFull(),

                        Forms\Components\FileUpload::make('photos')
                            ->label('Фото дефекта')
                            ->disk('public')
                            ->directory('nonconformities')
                            ->multiple()
                            ->image()
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Решение')
                    ->schema([
                        Forms\Components\Select::make('decision')
                            ->label('Принятое решение')
                            ->options(Nonconformity::DECISIONS),

                        Forms\Components\Select::make('status')
                            ->label('Статус')
                            ->options(Nonconformity::STATUSES)
                            ->default('open')
                            ->required(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('number')
                    ->label('№ акта')
                    ->weight('bold')
                    ->searchable(),

                Tables\Columns\TextColumn::make('category')
                    ->label('Категория')
                    ->formatStateUsing(fn (string $state) => Nonconformity::CATEGORIES[$state] ?? $state)
                    ->badge(),

                Tables\Columns\TextColumn::make('description')
                    ->label('Описание')
                    ->limit(60)
                    ->searchable(),

                Tables\Columns\TextColumn::make('lot.material.name')
                    ->label('Материал')
                    ->formatStateUsing(fn ($record) => $record->material?->name . ' ' . $record->material?->grade)
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Статус')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => Nonconformity::STATUSES[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        'open' => 'danger',
                        'pending_customer' => 'warning',
                        'closed' => 'success',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Дата')
                    ->date('d.m.Y')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Статус')
                    ->options(Nonconformity::STATUSES),
                Tables\Filters\SelectFilter::make('category')
                    ->label('Категория')
                    ->options(Nonconformity::CATEGORIES),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('close')
                    ->label('Закрыть акт')
                    ->icon('heroicon-m-check')
                    ->color('success')
                    ->visible(fn (Nonconformity $record) => $record->status !== 'closed')
                    ->requiresConfirmation()
                    ->action(fn (Nonconformity $record) => $record->update([
                        'status' => 'closed',
                        'resolved_by' => auth()->id(),
                        'resolved_at' => now(),
                    ])),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNonconformities::route('/'),
            'create' => Pages\CreateNonconformity::route('/create'),
            'edit' => Pages\EditNonconformity::route('/{record}/edit'),
        ];
    }
}
