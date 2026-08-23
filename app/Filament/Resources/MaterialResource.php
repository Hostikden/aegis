<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MaterialResource\RelationManagers\HistoryRelationManager;
use App\Filament\Resources\MaterialResource\Pages;
use App\Models\Material;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set; // Импортируем Set для изменения других полей
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class MaterialResource extends Resource
{
    protected static ?string $model = Material::class;

    // НАСТРОЙКА МЕНЮ: Добавляем иконку куба/коробки (отлично подходит под склад/материалы)
    protected static ?string $navigationIcon = 'heroicon-o-cube';

    // Название пункта в боковом меню
    protected static ?string $navigationLabel = 'Склад материалов';

    // Название внутри самого раздела (заголовки кнопок "Создать материал" и т.д.)
    protected static ?string $modelLabel = 'Материал';
    protected static ?string $pluralModelLabel = 'Материалы';

public static function form(Form $form): Form
{
   // Проверка: является ли текущий пользователь сотрудником цеха/склада (не админом)
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
                    ])
                    ->required()
                    ->live()
                    ->disabled($isNotAdmin), // Блокируем для не-админов

                Forms\Components\TextInput::make('grade')
                    ->label('Марка стали / Сплав')
                    ->required()
                    ->disabled($isNotAdmin), // Блокируем для не-админов
            ])->columns(2),

        Forms\Components\Section::make('Характеристики геометрии')
            ->schema([
                Forms\Components\TextInput::make('length')
                    ->label(fn (Get $get): string => $get('name') === 'Плита' ? 'Длина плиты (мм)' : 'Длина единицы / хлыста (м)')
                    ->numeric()
                    ->minValue(0)
                    ->required()
                    ->live(onBlur: true)
                    ->disabled($isNotAdmin) // Блокируем для не-админов
                    ->afterStateUpdated(function (Get $get, Set $set) {
                        if ($get('name') === 'Плита') {
                            $length = floatval($get('length'));
                            $width = floatval($get('width'));
                            if ($length > 0 && $width > 0) {
                                $area = ($length * $width) / 1000000;
                                $set('quantity', round($area, 4));
                            }
                        }
                    }),

                Forms\Components\TextInput::make('diameter')
                    ->label('Диаметр (мм)')
                    ->numeric()
                    ->required()
                    ->visible(fn (Get $get): bool => in_array($get('name'), ['Пруток', 'Труба']))
                    ->disabled($isNotAdmin), // Блокируем для не-админов

                Forms\Components\TextInput::make('thickness')
                    ->label('Толщина плиты (мм)')
                    ->numeric()
                    ->required()
                    ->visible(fn (Get $get): bool => $get('name') === 'Плита')
                    ->disabled($isNotAdmin), // Блокируем для не-админов

                Forms\Components\TextInput::make('width')
                    ->label('Ширина плиты (мм)')
                    ->numeric()
                    ->required()
                    ->live(onBlur: true)
                    ->visible(fn (Get $get): bool => $get('name') === 'Плита')
                    ->disabled($isNotAdmin) // Блокируем для не-админов
                    ->afterStateUpdated(function (Get $get, Set $set) {
                        $length = floatval($get('length'));
                        $width = floatval($get('width'));
                        if ($length > 0 && $width > 0) {
                            $area = ($length * $width) / 1000000;
                            $set('quantity', round($area, 4));
                        }
                    }),
            ])
            ->visible(fn (Get $get): bool => filled($get('name')))
            ->columns(3),

        Forms\Components\Section::make('Складской остаток')
            ->schema([
                Forms\Components\TextInput::make('quantity')
                    ->label(fn (Get $get): string => $get('name') === 'Плита' ? 'Площадь плиты, м² (остаток)' : 'Текущий остаток на складе, м')
                    ->numeric()
                    ->default(0)
                    ->required()
                    ->disabled($isNotAdmin) // Не-админы не могут вручную перезаписать остаток! Только через историю
                    ->helperText(fn (Get $get): ?string => $get('name') === 'Плита' ? 'Высчитывается автоматически в м²' : null),

                Forms\Components\Hidden::make('unit')
                    ->default(fn (Get $get) => match ($get('name')) {
                        'Плита' => 'м²',
                        'Пруток', 'Труба' => 'м',
                        default => 'м'
                    })
                    ->key(fn (Get $get) => 'hidden_unit_' . ($get('name') ?? 'default')),
            ])->columns(1),
    ]);

}




    /**
     * Конструктор ТАБЛИЦЫ (Вывод списка на экран)
     */
  public static function table(Table $table): Table
{
    return $table
        ->columns([
            Tables\Columns\TextColumn::make('name')
                ->label('Наименование')
                ->searchable()
                ->sortable(),

            Tables\Columns\TextColumn::make('grade')
                ->label('Марка стали')
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

            // МЫ ПОЛНОСТЬЮ УБРАЛИ КОЛОНКУ ДЛИНЫ ХЛЫСТА ОТСЮДА

            Tables\Columns\TextColumn::make('quantity')
                ->label('Остаток')
                ->sortable()
                ->weight('bold')
                ->color(fn (Material $record): string => $record->quantity <= 0 ? 'danger' : 'success')
                ->suffix(fn (Material $record): string => match ($record->name) {
                    'Плита' => ' м²',
                    'Пруток', 'Труба' => ' м',
                    default => " {$record->unit}"
                }),
        ])
        ->filters([
            Tables\Filters\SelectFilter::make('name')
                ->label('Тип материала')
                ->options([
                    'Пруток' => 'Пруток',
                    'Труба' => 'Труба',
                    'Плита' => 'Плита',
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

public static function getRelations(): array
{
    return [
        HistoryRelationManager::class,
    ];
}


public static function canViewAny(): bool
{
    return auth()->user()->hasAnyRole(['admin', 'director', 'storekeeper']);
}

public static function canCreate(): bool
{
    // Кнопку "Создать новый материал" видит ТОЛЬКО администратор
    return auth()->user()->role === 'admin';
}

public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
{
    // Удалять материалы из базы данных может ТОЛЬКО админ
    return auth()->user()->role === 'admin';
}


}
