<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ReceiptResource\Pages;
use App\Models\Material;
use App\Models\Receipt;
use App\Services\MaterialReceiptService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ReceiptResource extends Resource
{
    protected static ?string $model = Receipt::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck';
    protected static ?string $navigationLabel = 'Поступления материалов';
    protected static ?string $modelLabel = 'Поступление';
    protected static ?string $pluralModelLabel = 'Поступления материалов';
    protected static ?string $navigationGroup = 'Входной контроль';
    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Документ поступления')
                    ->schema([
                        Forms\Components\TextInput::make('receipt_number')
                            ->label('Номер документа')
                            ->default(fn () => Receipt::generateNextNumber())
                            ->unique(ignoreRecord: true)
                            ->disabled()
                            ->dehydrated()
                            ->required(),

                        Forms\Components\Select::make('counterparty_id')
                            ->label('Заказчик / Поставщик')
                            ->relationship('counterparty', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')->label('Наименование')->required(),
                                Forms\Components\Select::make('type')->label('Тип')->options([
                                    'customer' => 'Заказчик',
                                    'supplier' => 'Поставщик',
                                    'both' => 'Заказчик + Поставщик',
                                ])->default('customer')->required(),
                            ]),

                        Forms\Components\DatePicker::make('receipt_date')
                            ->label('Дата поступления')
                            ->default(now())
                            ->required()
                            ->native(false),

                        Forms\Components\TextInput::make('document_number')
                            ->label('№ накладной / УПД поставщика'),

                        Forms\Components\DatePicker::make('document_date')
                            ->label('Дата накладной')
                            ->native(false),
                    ])
                    ->columns(3)
                    ->disabled(fn ($livewire) => optional($livewire->record ?? null)->status === 'posted'),

                Forms\Components\Textarea::make('notes')
                    ->label('Примечание к документу')
                    ->rows(2)
                    ->columnSpanFull()
                    ->disabled(fn ($livewire) => optional($livewire->record ?? null)->status === 'posted'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('receipt_number')
                    ->label('№ документа')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('counterparty.name')
                    ->label('Заказчик / Поставщик')
                    ->searchable(),

                Tables\Columns\TextColumn::make('receipt_date')
                    ->label('Дата')
                    ->date('d.m.Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('document_number')
                    ->label('№ накладной')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('lines_count')
                    ->label('Строк')
                    ->counts('lines')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Статус')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'gray',
                        'posted' => 'success',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'draft' => '📝 Черновик',
                        'posted' => '✅ Проведён',
                        default => $state,
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Статус')
                    ->options([
                        'draft' => 'Черновик',
                        'posted' => 'Проведён',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label(fn (Receipt $record) => $record->status === 'posted' ? 'Просмотр' : 'Редактировать'),

                Tables\Actions\Action::make('post')
                    ->label('Провести')
                    ->icon('heroicon-m-check-badge')
                    ->color('success')
                    ->visible(fn (Receipt $record) => $record->status === 'draft')
                    ->requiresConfirmation()
                    ->modalHeading('Провести документ поступления?')
                    ->modalDescription('После проведения по каждой строке будет создана партия на складе, а сам документ станет недоступен для редактирования.')
                    ->action(function (Receipt $record) {
                        try {
                            app(MaterialReceiptService::class)->postReceipt($record);

                            Notification::make()
                                ->title('Документ проведён')
                                ->body('Партии материалов созданы и добавлены на склад.')
                                ->success()
                                ->send();
                        } catch (\RuntimeException $e) {
                            Notification::make()
                                ->title('🚨 Провести не удалось')
                                ->body($e->getMessage())
                                ->danger()
                                ->persistent()
                                ->send();
                        }
                    }),

                Tables\Actions\DeleteAction::make()
                    ->visible(fn (Receipt $record) => $record->status === 'draft'),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            \App\Filament\Resources\ReceiptResource\RelationManagers\LinesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReceipts::route('/'),
            'create' => Pages\CreateReceipt::route('/create'),
            'edit' => Pages\EditReceipt::route('/{record}/edit'),
        ];
    }
}
