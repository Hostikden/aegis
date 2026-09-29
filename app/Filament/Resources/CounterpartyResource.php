<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CounterpartyResource\Pages;
use App\Models\Counterparty;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CounterpartyResource extends Resource
{
    protected static ?string $model = Counterparty::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';
    protected static ?string $navigationLabel = 'Контрагенты';
    protected static ?string $modelLabel = 'Контрагент';
    protected static ?string $pluralModelLabel = 'Контрагенты';
    protected static ?string $navigationGroup = 'Входной контроль';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Реквизиты контрагента')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Наименование')
                            ->required()
                            ->columnSpanFull(),

                        Forms\Components\Select::make('type')
                            ->label('Тип отношений')
                            ->options([
                                'customer' => 'Заказчик (присылает давальческий материал)',
                                'supplier' => 'Поставщик (продаёт материал нам)',
                                'both' => 'И заказчик, и поставщик',
                            ])
                            ->required()
                            ->default('customer'),

                        Forms\Components\TextInput::make('inn')
                            ->label('ИНН')
                            ->helperText('Для сверки с документом о качестве по ГОСТ 7566'),

                        Forms\Components\TextInput::make('contact_person')
                            ->label('Контактное лицо'),

                        Forms\Components\TextInput::make('phone')
                            ->label('Телефон')
                            ->tel(),

                        Forms\Components\TextInput::make('email')
                            ->label('Email')
                            ->email(),

                        Forms\Components\TextInput::make('code_1c')
                            ->label('Код в 1С')
                            ->helperText('Заполняется позже, при интеграции с бухгалтерией'),
                    ])->columns(2),

                Forms\Components\Textarea::make('notes')
                    ->label('Примечания')
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Наименование')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('type')
                    ->label('Тип')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'customer' => 'Заказчик',
                        'supplier' => 'Поставщик',
                        'both' => 'Заказчик + Поставщик',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'customer' => 'info',
                        'supplier' => 'warning',
                        'both' => 'success',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('inn')
                    ->label('ИНН')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('contact_person')
                    ->label('Контакт')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('phone')
                    ->label('Телефон')
                    ->placeholder('—'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('Тип')
                    ->options([
                        'customer' => 'Заказчик',
                        'supplier' => 'Поставщик',
                        'both' => 'Заказчик + Поставщик',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCounterparties::route('/'),
            'create' => Pages\CreateCounterparty::route('/create'),
            'edit' => Pages\EditCounterparty::route('/{record}/edit'),
        ];
    }
}
