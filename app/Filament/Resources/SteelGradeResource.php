<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SteelGradeResource\Pages;
use App\Models\SteelGrade;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SteelGradeResource extends Resource
{
    protected static ?string $model = SteelGrade::class;

    protected static ?string $navigationIcon = 'heroicon-o-beaker';
    protected static ?string $navigationLabel = 'Марки стали';
    protected static ?string $modelLabel = 'Марка стали';
    protected static ?string $pluralModelLabel = 'Марки стали';
    protected static ?string $navigationGroup = 'Входной контроль';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Марка материала')
                    ->description('Плотность используется для пересчёта веса (кг) в длину (м) — например, чтобы понять, сколько метров прутка получили при поступлении 500 кг материала.')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Марка')
                            ->placeholder('12Х18Н10Т, Ст3, 09Г2С...')
                            ->required()
                            ->unique(ignoreRecord: true),

                        Forms\Components\TextInput::make('gost')
                            ->label('ГОСТ на марку')
                            ->placeholder('ГОСТ 5632-2014'),

                        Forms\Components\TextInput::make('density_kg_m3')
                            ->label('Плотность, кг/м³')
                            ->numeric()
                            ->required()
                            ->default(7850)
                            ->helperText('Сталь ≈7850, нержавейка ≈7900, алюминий ≈2700, латунь ≈8500'),
                    ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Марка')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('gost')
                    ->label('ГОСТ')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('density_kg_m3')
                    ->label('Плотность')
                    ->suffix(' кг/м³')
                    ->sortable(),

                Tables\Columns\TextColumn::make('materials_count')
                    ->label('Материалов привязано')
                    ->counts('materials')
                    ->badge()
                    ->color('gray'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSteelGrades::route('/'),
            'create' => Pages\CreateSteelGrade::route('/create'),
            'edit' => Pages\EditSteelGrade::route('/{record}/edit'),
        ];
    }
}
