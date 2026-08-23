<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Models\Product;
use App\Models\Material;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
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
                            ->live(),
                ])->columns(3),
                // Секция 2: Умный калькулятор заготовок (BOM) — только для простых деталей
                Forms\Components\Section::make('Нормы расхода сырья (BOM)')
                    ->description('Выберите параметры материала и укажите габариты заготовки для авторасчета')
                    ->visible(fn (Get $get) => $get('type') === 'detail')
                    ->schema([
                        Forms\Components\Repeater::make('productMaterials')
                            ->relationship('productMaterials')

                            // Безопасное удаление интерфейсных полей перед SQL-запросом INSERT/UPDATE
                            ->mutateRelationshipDataBeforeCreateUsing(function (array $data): array {
                                unset($data['material_type'], $data['material_grade'], $data['detail_length'], $data['detail_width']);
                                return $data;
                            })
                            ->mutateRelationshipDataBeforeSaveUsing(function (array $data): array {
                                unset($data['material_type'], $data['material_grade'], $data['detail_length'], $data['detail_width']);
                                return $data;
                            })
                            ->schema([

                                Forms\Components\Select::make('material_type')
                                    ->label('Тип проката')
                                    ->options([
                                        'Пруток' => '🔩 Пруток',
                                        'Труба' => '🧪 Труба',
                                        'Плита' => '⬜ Плита / Лист',
                                    ])
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function (Set $set) {
                                        $set('material_grade', null);
                                        $set('material_id', null);
                                    }),

                                Forms\Components\Select::make('material_grade')
                                    ->label('Марка стали / Сплав')
                                    ->options(function (Get $get) {
                                        $type = $get('material_type');
                                        if (!$type) return [];
                                        return Material::where('name', $type)
                                            ->whereNotNull('grade')
                                            ->distinct()
                                            ->pluck('grade', 'grade');
                                    })
                                    ->searchable()
                                    ->required()
                                    ->live()
                                    ->disabled(fn (Get $get) => !$get('material_type'))
                                    ->afterStateUpdated(fn (Set $set) => $set('material_id', null)),

                                Forms\Components\Select::make('material_id')
                                    ->label('Профиль / Сортамент со склада')
                                    ->options(function (Get $get) {
                                        $type = $get('material_type');
                                        $grade = $get('material_grade');
                                        if (!$type || !$grade) return [];

                                        return Material::where('name', $type)
                                            ->where('grade', $grade)
                                            ->get()
                                            ->mapWithKeys(function ($mat) {
                                                $sizeInfo = '';
                                                if ($mat->name === 'Плита' && $mat->thickness) $sizeInfo = " (Толщина: {$mat->thickness} мм)";
                                                if (in_array($mat->name, ['Пруток', 'Труба']) && $mat->diameter) $sizeInfo = " (Ø {$mat->diameter} мм)";
                                                return [$mat->id => "ID {$mat->id}{$sizeInfo}"];
                                            });
                                    })
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->live()
                                    ->disabled(fn (Get $get) => !$get('material_grade')),

                                // Поля чистых габаритов заготовки (Без лишних коэффициентов припуска)
                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\TextInput::make('detail_length')
                                            ->label('Длина заготовки (мм)')
                                            ->numeric()
                                            ->minValue(1)
                                            ->required()
                                            ->live(onBlur: true)
                                            ->placeholder('150')
                                            ->visible(fn (Get $get) => filled($get('material_id')))
                                            ->afterStateUpdated(fn (Get $get, Set $set) => self::calculateBomRate($get, $set)),

                                        Forms\Components\TextInput::make('detail_width')
                                            ->label('Ширина заготовки (мм)')
                                            ->numeric()
                                            ->minValue(1)
                                            ->required()
                                            ->live(onBlur: true)
                                            ->placeholder('200')
                                            ->visible(fn (Get $get) => $get('material_type') === 'Плита' && filled($get('material_id')))
                                            ->afterStateUpdated(fn (Get $get, Set $set) => self::calculateBomRate($get, $set)),
                                    ]),

                                // Итоговый расход для БД
                                Forms\Components\TextInput::make('consumption_rate')
                                    ->label(fn (Get $get) => $get('material_type') === 'Плита' ? 'Итого расход (м²)' : 'Итого расход (пог. м)')
                                    ->numeric()
                                    ->required()
                                    ->disabled()
                                    ->dehydrated()
                                    ->prefix('📊')
                                    ->visible(fn (Get $get) => filled($get('material_id'))),
                            ])
                            ->columns(3)
                            ->defaultItems(0)
                            ->addActionLabel('Добавить материал в спецификацию')
                ]),





                // Секция 3: Состав сборки — Изолированный репитер (Решает проблему с ошибкой SKU)
                Forms\Components\Section::make('Состав сборочной единицы')
                    ->description('Укажите, из каких существующих деталей состоит данная сборка')
                    ->visible(fn (Get $get) => $get('type') === 'assembly')
                    ->schema([
                        Forms\Components\Repeater::make('assembly_components') // Чисто интерфейсное имя
                            ->schema([
                                Forms\Components\Select::make('component_product_id')
                                    ->label('Входящая деталь / узел')
                                    ->options(function (Get $get) {
                                        $currentId = $get('../../id');
                                        return Product::where('type', 'detail')
                                            ->when($currentId, fn ($query) => $query->where('id', '!=', $currentId))
                                            ->pluck('name', 'id');
                                    })
                                    ->searchable()
                                    ->preload()
                                    ->required(),

                                Forms\Components\TextInput::make('component_quantity')
                                    ->label('Количество на 1 сборку (шт)')
                                    ->integer()
                                    ->minValue(1)
                                    ->default(1)
                                    ->required(),
                            ])
                            ->columns(2)
                            ->defaultItems(0)
                            ->addActionLabel('Добавить деталь в состав сборки'),
                    ]),

            ]);
    }

    /**
     * Статический калькулятор перевода заготовок из мм в метры/м²
     */
    public static function calculateBomRate(Get $get, Set $set): void
    {
        $type = $get('material_type');
        $length = floatval($get('detail_length'));
        $width = floatval($get('detail_width'));

        if (!$type || $length <= 0) {
            $set('consumption_rate', 0);
            return;
        }

        if ($type === 'Плита') {
            if ($width > 0) {
                $areaSquareMeters = ($length * $width) / 1000000;
                $set('consumption_rate', round($areaSquareMeters, 5));
            } else {
                $set('consumption_rate', 0);
            }
        } else {
            $linearMeters = $length / 1000;
            $set('consumption_rate', round($linearMeters, 5));
        }
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

                Tables\Columns\TextColumn::make('type')
                    ->label('Тип')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'assembly' ? 'warning' : 'success')
                    ->formatStateUsing(fn (string $state) => $state === 'assembly' ? '📦 Сборка' : '🔩 Деталь'),

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
        return auth()->user()->hasAnyRole(['admin', 'director', 'technologist']);
    }
} // Конец класса ProductResource

