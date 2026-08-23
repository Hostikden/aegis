<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MaterialResource\Pages;
use App\Models\Material;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class MaterialResource extends Resource
{
    protected static ?string $model = Material::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube';
    protected static ?string $navigationLabel = 'Склад материалов';
    protected static ?string $modelLabel = 'Материал';
    protected static ?string $pluralModelLabel = 'Материалы';

    public static function form(Form $form): Form
    {
        $isNotAdmin = !auth()->user()->isAdmin();

        return $form
            ->schema([
                Forms\Components\Section::make('Основная информация')
                    ->schema([
                        Forms\Components\Select::make('name')
                            ->label('Наименование (Тип)')
                            ->options([
                                'Пруток' => 'Пруток',
                                'Труба' => 'Труба',
                                'Плита' => 'Плита',
                                'Покупное изделие' => '📦 Покупное изделие (шт.)',
                            ])
                            ->required()
                            ->live()
                            ->disabled($isNotAdmin),

                        Forms\Components\TextInput::make('grade')
                            ->label('Марка стали / Наименование изделия')
                            ->placeholder('09Г2С / Болт М12х40, Подшипник 204')
                            ->required()
                            ->disabled($isNotAdmin),
                    ])->columns(2),
                Forms\Components\Section::make('Характеристики геометрии')
                    ->schema([
                        Forms\Components\TextInput::make('length')
                            ->label(fn (Get $get): string => $get('name') === 'Плита' ? 'Длина плиты (мм)' : 'Длина единицы / хлыста (м)')
                            ->numeric()
                            ->minValue(0)
                            ->required()
                            ->live(onBlur: true)
                            ->disabled($isNotAdmin),

                        Forms\Components\TextInput::make('diameter')
                            ->label('Диаметр (мм)')
                            ->numeric()
                            ->required()
                            ->visible(fn (Get $get): bool => in_array($get('name'), ['Пруток', 'Труба']))
                            ->disabled($isNotAdmin),

                        Forms\Components\TextInput::make('thickness')
                            ->label('Толщина плиты (мм)')
                            ->numeric()
                            ->required()
                            ->visible(fn (Get $get): bool => $get('name') === 'Пliта')
                            ->disabled($isNotAdmin),

                        Forms\Components\TextInput::make('width')
                            ->label('Ширина плиты (мм)')
                            ->numeric()
                            ->required()
                            ->live(onBlur: true)
                            ->visible(fn (Get $get): bool => $get('name') === 'Плита')
                            ->disabled($isNotAdmin),
                    ])
                    ->visible(fn (Get $get): bool => filled($get('name')) && $get('name') !== 'Покупное изделие')
                    ->columns(3),

                Forms\Components\Section::make('Складской остаток')
                    ->schema([
                        Forms\Components\TextInput::make('quantity')
                            ->label(fn (Get $get): string => match ($get('name')) {
                                'Плита' => 'Площадь плиты, м² (остаток)',
                                'Покупное изделие' => 'Количество на складе (шт.)',
                                default => 'Текущий остаток на складе, м'
                            })
                            ->numeric()
                            ->default(0)
                            ->required()
                            ->disabled($isNotAdmin),

                        Forms\Components\Hidden::make('unit')
                            ->default(fn (Get $get) => match ($get('name')) {
                                'Плита' => 'м²',
                                'Покупное изделие' => 'шт',
                                default => 'м'
                            })
                            ->key(fn (Get $get) => 'hidden_unit_' . ($get('name') ?? 'default')),
                    ])->columns(1),
            ]);
    }
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Наименование')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('grade')
                    ->label('Марка / Наименование')
                    ->searchable()
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('diameter')
                    ->label('Диаметр')
                    ->suffix(' мм')
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('thickness')
                    ->label('Толщина')
                    ->suffix(' мм')
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('quantity')
                    ->label('Всего на складе')
                    ->sortable()
                    ->weight('bold')
                    ->color(fn (Material $record): string => $record->quantity <= 0 ? 'danger' : 'gray')
                    ->suffix(fn (Material $record): string => match ($record->name) {
                        'Плита' => ' м²',
                        'Покупное изделие' => ' шт.',
                        default => ' м'
                    }),

                Tables\Columns\TextColumn::make('reserved')
                    ->label('В резерве')
                    ->sortable()
                    ->color(fn (Material $record): string => $record->reserved > 0 ? 'warning' : 'gray')
                    ->suffix(fn (Material $record): string => match ($record->name) {
                        'Плита' => ' м²',
                        'Покупное изделие' => ' шт.',
                        default => ' м'
                    }),

                Tables\Columns\TextColumn::make('available_quantity')
                    ->label('Доступно')
                    ->weight('bold')
                    ->color(fn (Material $record): string => ($record->quantity - $record->reserved) <= 0 ? 'danger' : 'success')
                    ->suffix(fn (Material $record): string => match ($record->name) {
                        'Плита' => ' м²',
                        'Покупное изделие' => ' шт.',
                        default => ' м'
                    })
                    ->state(fn (Material $record): float => max(0, $record->quantity - $record->reserved)),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('name')
                    ->label('Тип материала')
                    ->options([
                        'Пруток' => 'Пруток',
                        'Труба' => 'Труба',
                        'Плита' => 'Плита',
                        'Покупное изделие' => 'Покупное изделие',
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMaterials::route('/'),
            'create' => Pages\CreateMaterial::route('/create'),
            'edit' => Pages\EditMaterial::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return auth()->user()->hasAnyRole(['admin', 'director', 'storekeeper']);
    }

    public static function canCreate(): bool
    {
        return auth()->user()->role === 'admin';
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()->role === 'admin';
    }

        public static function getRelations(): array
    {
        return [
            // ВОЗВРАЩАЕМ ИСТОРИЮ: Подключаем отображение вкладки движений материала
            \App\Filament\Resources\MaterialResource\RelationManagers\HistoryRelationManager::class,
        ];
    }

}
