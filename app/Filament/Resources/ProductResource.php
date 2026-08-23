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

 // Секция 2: Автоматический расчет спецификации металла (BOM) — только для простых деталей
Forms\Components\Repeater::make('productMaterials')
    ->relationship('productMaterials')

    // МУТАТОР ДЛЯ СОЗДАНИЯ: Удаляет служебные поля калькулятора перед записью в БД
    ->mutateRelationshipDataBeforeCreateUsing(function (array $data): array {
        unset($data['material_type'], $data['material_grade'], $data['detail_length'], $data['detail_width'], $data['allowance_factor']);
        return $data;
    })

    // МУТАТОР ДЛЯ РЕДАКТИРОВАНИЯ: Удаляет служебные поля калькулятора перед обновлением БД
    ->mutateRelationshipDataBeforeSaveUsing(function (array $data): array {
        unset($data['material_type'], $data['material_grade'], $data['detail_length'], $data['detail_width'], $data['allowance_factor']);
        return $data;
    })
    ->schema([
        Forms\Components\Repeater::make('productMaterials')
            ->relationship('productMaterials')
            ->schema([

                // 1. Выбор типа проката
                Forms\Components\Select::make('material_type')
                    ->label('Тип проката')
                    ->options([
                        'Пруток' => '🔩 Пруток',
                        'Труба' => '🧪 Труба',
                        'Плита' => '⬜ Плита / Лист',
                    ])
                    ->required()
                    ->live()
                    ->dehydrated(false) // <--- ИЗМЕНЕНО: Не отправляем в БД
                    ->afterStateUpdated(function (Forms\Set $set) {
                        $set('material_grade', null);
                        $set('material_id', null);
                    }),

                // 2. Выбор марки стали
                Forms\Components\Select::make('material_grade')
                    ->label('Марка стали / Сплав')
                    ->options(function (Forms\Get $get) {
                        $type = $get('material_type');
                        if (!$type) return [];

                        return \App\Models\Material::where('name', $type)
                            ->whereNotNull('grade')
                            ->distinct()
                            ->pluck('grade', 'grade');
                    })
                    ->searchable()
                    ->required()
                    ->live()
                    ->dehydrated(false) // <--- ИЗМЕНЕНО: Не отправляем в БД
                    ->disabled(fn (Forms\Get $get) => !$get('material_type'))
                    ->afterStateUpdated(fn (Forms\Set $set) => $set('material_id', null)),

                // 3. Выбор конкретного профиля (Этот ID сохраняем, поэтому dehydrated НЕ НУЖЕН)
                Forms\Components\Select::make('material_id')
                    ->label('Профиль / Сортамент со склада')
                    ->options(function (Forms\Get $get) {
                        $type = $get('material_type');
                        $grade = $get('material_grade');
                        if (!$type || !$grade) return [];

                        return \App\Models\Material::where('name', $type)
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
                    ->disabled(fn (Forms\Get $get) => !$get('material_grade')),

                // Поля геометрии детали
                Forms\Components\Grid::make(3)
                    ->schema([
                        Forms\Components\TextInput::make('detail_length')
                            ->label('Длина детали (мм)')
                            ->numeric()
                            ->minValue(1)
                            ->required()
                            ->live(onBlur: true)
                            ->placeholder('150')
                            ->dehydrated(false) // <--- ИЗМЕНЕНО: Не отправляем в БД
                            ->visible(fn (Forms\Get $get) => filled($get('material_id')))
                            ->afterStateUpdated(fn (Forms\Get $get, Forms\Set $set) => self::calculateBomRate($get, $set)),

                        Forms\Components\TextInput::make('detail_width')
                            ->label('Ширина детали (мм)')
                            ->numeric()
                            ->minValue(1)
                            ->required()
                            ->live(onBlur: true)
                            ->placeholder('200')
                            ->dehydrated(false) // <--- ИЗМЕНЕНО: Не отправляем в БД
                            ->visible(fn (Forms\Get $get) => $get('material_type') === 'Плита' && filled($get('material_id')))
                            ->afterStateUpdated(fn (Forms\Get $get, Forms\Set $set) => self::calculateBomRate($get, $set)),

                        Forms\Components\TextInput::make('allowance_factor')
                            ->label('Коэф. припуска (отходов)')
                            ->numeric()
                            ->default(1.0)
                            ->minValue(1.0)
                            ->required()
                            ->live(onBlur: true)
                            ->dehydrated(false) // <--- ИЗМЕНЕНО: Не отправляем в БД
                            ->helperText('1.0 — без отходов, 1.05 — плюс 5% на рез')
                            ->visible(fn (Forms\Get $get) => filled($get('material_id')))
                            ->afterStateUpdated(fn (Forms\Get $get, Forms\Set $set) => self::calculateBomRate($get, $set)),
                    ]),

                // Итоговый расход (Его мы СОХРАНЯЕМ, поэтому dehydrated(true))
                Forms\Components\TextInput::make('consumption_rate')
                    ->label(fn (Forms\Get $get) => $get('material_type') === 'Плита' ? 'Итого норма расхода (м²)' : 'Итого норма расхода (пог. м)')
                    ->numeric()
                    ->required()
                    ->disabled()
                    ->dehydrated() // Принудительно сохраняем в базу данных рассчитанное значение
                    ->prefix('📊')
                    ->helperText('Высчитывается автоматически на основе геометрии детали')
                    ->visible(fn (Forms\Get $get) => filled($get('material_id'))),
            ])
            ->columns(3)
            ->defaultItems(0)
            ->addActionLabel('Добавить материал в спецификацию')
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


    /**
 * Функция автоматического пересчета норм расхода (BOM) на основе габаритов детали
 */
public static function calculateBomRate(Forms\Get $get, Forms\Set $set): void
{
    $type = $get('material_type');
    $length = floatval($get('detail_length'));
    $width = floatval($get('detail_width'));
    $allowance = floatval($get('allowance_factor') ?? 1.0);

    if (!$type || $length <= 0) {
        $set('consumption_rate', 0);
        return;
    }

    if ($type === 'Плита') {
        // Расчет для плит: (Длина (мм) * Ширина (мм) / 1 000 000) * Коэффициент отхода = Квадратные метры (м²)
        if ($width > 0) {
            $areaSquareMeters = ($length * $width) / 1000000;
            $finalRate = $areaSquareMeters * $allowance;
            $set('consumption_rate', round($finalRate, 5)); // Округляем до 5 знаков для точности
        } else {
            $set('consumption_rate', 0);
        }
    } else {
        // Расчет для Прутков и Труб: Длина детали (мм) / 1000 * Коэффициент отхода = Погонные метры (м)
        $linearMeters = $length / 1000;
        $finalRate = $linearMeters * $allowance;
        $set('consumption_rate', round($finalRate, 5));
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
    return auth()->user()->hasAnyRole(['admin', 'director', 'technologist']);
}


} // Конец класса ProductResource

