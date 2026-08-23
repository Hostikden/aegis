<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Models\Product;
use App\Models\Material;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static ?string $navigationLabel = 'Готовые изделия';
    protected static ?string $modelLabel = 'Изделие';
    protected static ?string $pluralModelLabel = 'Изделия';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // Секция 1: Основные данные изделия
                Forms\Components\Section::make('Информация об изделии')
                    ->schema([
                        Forms\Components\TextInput::make('sku')
                            ->label('Артикул / Чертеж №')
                            ->placeholder('СБ-104-02')
                            ->unique(ignoreRecord: true)
                            ->required(),

                        Forms\Components\TextInput::make('name')
                            ->label('Наименование')
                            ->placeholder('Кронштейн опорный / Узел в сборе')
                            ->required(),

                        Forms\Components\Select::make('type')
                            ->label('Тип изделия')
                            ->options([
                                'detail' => '🔩 Простая деталь (из сырья)',
                                'assembly' => '📦 Сборочная единица (из деталей)',
                            ])
                            ->default('detail')
                            ->required()
                            ->live(), // Перерисовывает форму при изменении типа
                    ])->columns(3),

                // Секция 2: Спецификация металла (BOM) — Видна ТОЛЬКО для деталей
                Forms\Components\Section::make('Нормы расхода сырья (BOM)')
                    ->description('Укажите металл со склада для производства этой детали')
                    ->visible(fn (Forms\Get $get) => $get('type') === 'detail')
                    ->schema([
                        Forms\Components\Repeater::make('productMaterials')
                            ->relationship()
                            ->schema([
                                Forms\Components\Select::make('material_id')
                                    ->label('Материал со склада')
                                    ->options(fn () => Material::all()->mapWithKeys(function ($mat) {
                                        $thickness = $mat->thickness ? ", {$mat->thickness}мм" : '';
                                        return [$mat->id => "{$mat->name} ({$mat->grade}{$thickness})"];
                                    }))
                                    ->searchable()
                                    ->preload()
                                    ->required(),

                                Forms\Components\TextInput::make('consumption_rate')
                                    ->label('Норма расхода (м / м²)')
                                    ->numeric()
                                    ->minValue(0.001)
                                    ->required(),
                            ])
                            ->columns(2)
                            ->defaultItems(0)
                            ->addActionLabel('Добавить металл в спецификацию')
                    ]),
                // Секция 3: Состав сборки — Видна ТОЛЬКО для сборочных единиц
                Forms\Components\Section::make('Состав сборочной единицы')
                    ->description('Укажите, из каких вложенных деталей или узлов состоит данная сборка')
                    ->visible(fn (Forms\Get $get) => $get('type') === 'assembly')
                    ->schema([
                        Forms\Components\Repeater::make('components')
                            ->relationship('components')
                            ->schema([
                                Forms\Components\Select::make('product_id') // Имя связи в pivot-таблице
                                    ->label('Входящая деталь / узел')
                                    ->options(function (Forms\Get $get) {
                                        // Исключаем текущую сборку из списка выбора, чтобы не закольцевать связь
                                        $currentId = $get('../../id');
                                        return Product::when($currentId, fn ($query) => $query->where('id', '!=', $currentId))
                                            ->pluck('name', 'id');
                                    })
                                    ->searchable()
                                    ->preload()
                                    ->required(),

                                Forms\Components\TextInput::make('quantity') // Количество из pivot-таблицы
                                    ->label('Количество на 1 сборку (шт)')
                                    ->integer()
                                    ->minValue(1)
                                    ->default(1)
                                    ->required(),
                            ])
                            ->columns(2)
                            ->defaultItems(0)
                            ->addActionLabel('Добавить деталь в состав сборки')
                    ]),
            ]);
    }
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sku')
                    ->label('Чертеж / Артикул')
                    ->searchable()
                    ->sortable()
                    ->fontFamily('mono')
                    ->copyable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Наименование')
                    ->searchable()
                    ->sortable(),

                // Отображение типа изделия: Сборка или Деталь
                Tables\Columns\TextColumn::make('type')
                    ->label('Тип')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'assembly' ? 'warning' : 'success')
                    ->formatStateUsing(fn (string $state) => $state === 'assembly' ? '📦 Сборка' : '🔩 Деталь'),

                // БЕЗОПАСНЫЙ ВЫВОД: Показывает количество компонентов в зависимости от типа изделия
                Tables\Columns\TextColumn::make('id')
                    ->label('Состав спецификации')
                    ->badge()
                    ->color('info')
                    ->formatStateUsing(function (Product $record): string {
                        if ($record->type === 'assembly') {
                            $count = $record->components()->count();
                            return "Деталей: {$count} шт.";
                        }

                        $count = $record->productMaterials()->count();
                        return "Материалов: {$count} наим.";
                    }),
            ])
            ->filters([])
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
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return auth()->user()->hasAnyRole(['admin', 'manager']);
    }
} // Конец класса ProductResource

