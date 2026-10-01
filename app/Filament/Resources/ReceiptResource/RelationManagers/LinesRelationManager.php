<?php

namespace App\Filament\Resources\ReceiptResource\RelationManagers;

use App\Models\InspectionResult;
use App\Models\Material;
use App\Models\ReceiptLine;
use App\Services\MaterialReceiptService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class LinesRelationManager extends RelationManager
{
    protected static string $relationship = 'lines';

    protected static ?string $title = 'Строки поступления и входной контроль';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('material_id')
                    ->label('Материал')
                    ->options(fn () => Material::with('steelGrade')->get()->mapWithKeys(
                        fn (Material $m) => [$m->id => "{$m->name} {$m->grade}" . ($m->diameter ? " Ø{$m->diameter}" : '') . ($m->thickness ? " S{$m->thickness}" : '')]
                    ))
                    ->searchable()
                    ->required()
                    ->columnSpanFull(),

                Forms\Components\TextInput::make('declared_weight_kg')
                    ->label('Вес по накладной, кг')
                    ->numeric()
                    ->required()
                    ->minValue(0.01),

                Forms\Components\TextInput::make('actual_weight_kg')
                    ->label('Вес фактический (взвешенный), кг')
                    ->numeric()
                    ->helperText('Если не заполнено — примется вес по накладной'),

                Forms\Components\TextInput::make('pieces_count')
                    ->label('Кол-во штук')
                    ->numeric()
                    ->helperText('Обязательно для покупных изделий'),

                Forms\Components\TextInput::make('measured_length_m')
                    ->label('Замеренная длина хлыста, м')
                    ->numeric(),

                Forms\Components\TextInput::make('melt_number')
                    ->label('№ плавки'),

                Forms\Components\TextInput::make('supplier_lot_number')
                    ->label('№ партии поставщика'),

                Forms\Components\TextInput::make('certificate_number')
                    ->label('№ сертификата / документа о качестве'),

                Forms\Components\DatePicker::make('certificate_date')
                    ->label('Дата сертификата')
                    ->native(false),

                Forms\Components\Toggle::make('certificate_attached')
                    ->label('Сертификат приложен')
                    ->inline(false),

                Forms\Components\Textarea::make('notes')
                    ->label('Примечание')
                    ->columnSpanFull()
                    ->rows(2),
            ])
            ->columns(4);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('material.name')
            ->columns([
                Tables\Columns\TextColumn::make('material.name')
                    ->label('Материал')
                    ->formatStateUsing(fn (ReceiptLine $record) => "{$record->material->name} {$record->material->grade}")
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('effective_weight_kg')
                    ->label('Вес, кг')
                    ->state(fn (ReceiptLine $record) => $record->effective_weight_kg)
                    ->suffix(' кг'),

                Tables\Columns\TextColumn::make('melt_number')
                    ->label('№ плавки')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('certificate_number')
                    ->label('№ сертификата')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('inspection_status')
                    ->label('Входной контроль')
                    ->state(function (ReceiptLine $record) {
                        $done = $record->inspectionResults()->count();
                        $total = count(InspectionResult::CHECKPOINTS);
                        return "{$done} / {$total} пунктов";
                    })
                    ->badge()
                    ->color(fn (ReceiptLine $record) => $record->isFullyInspected() ? 'success' : 'warning'),

                Tables\Columns\TextColumn::make('decision')
                    ->label('Решение')
                    ->badge()
                    ->placeholder('Не принято')
                    ->formatStateUsing(fn (?string $state) => $state ? ReceiptLine::DECISIONS[$state] : 'Не принято')
                    ->color(fn (?string $state): string => match ($state) {
                        'accept' => 'success',
                        'accept_with_limitation' => 'warning',
                        'quarantine' => 'warning',
                        'return' => 'danger',
                        default => 'gray',
                    }),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('+ Добавить строку')
                    ->visible(fn () => $this->getOwnerRecord()->status === 'draft'),
            ])
            ->actions([
                Tables\Actions\Action::make('inspect')
                    ->label('Пройти контроль')
                    ->icon('heroicon-m-clipboard-document-check')
                    ->color('warning')
                    ->visible(fn () => $this->getOwnerRecord()->status === 'draft')
                    ->modalWidth('2xl')
                    ->modalHeading('Входной контроль по строке поступления')
                    ->fillForm(function (ReceiptLine $record): array {
                        $results = [];
                        foreach ($record->inspectionResults as $ir) {
                            $results[$ir->checkpoint] = [
                                'result' => $ir->result,
                                'measured_value' => $ir->measured_value,
                                'comment' => $ir->comment,
                            ];
                        }

                        return [
                            'results' => $results,
                            'decision' => $record->decision,
                            'decision_comment' => $record->decision_comment,
                        ];
                    })
                    ->form([
                        Forms\Components\Placeholder::make('weight_info')
                            ->label('Заявлено / Фактически / Расхождение')
                            ->content(function (ReceiptLine $record): string {
                                $declared = number_format($record->declared_weight_kg, 2, ',', ' ');
                                $actual = $record->actual_weight_kg ? number_format($record->actual_weight_kg, 2, ',', ' ') : '—';
                                $diff = $record->weight_discrepancy_percent;
                                $diffText = $diff === null ? '—' : ($diff > 0 ? "+{$diff}%" : "{$diff}%");

                                return "{$declared} кг / {$actual} кг / {$diffText}";
                            }),

                        Forms\Components\Section::make(InspectionResult::CHECKPOINTS['documents'])
                            ->schema([
                                Forms\Components\Select::make('results.documents.result')
                                    ->label('Результат')
                                    ->options(['pass' => '✅ Соответствует', 'fail' => '❌ Несоответствие'])
                                    ->required(),
                                Forms\Components\TextInput::make('results.documents.comment')
                                    ->label('Комментарий'),
                            ])->columns(2),

                        Forms\Components\Section::make(InspectionResult::CHECKPOINTS['quantity'])
                            ->schema([
                                Forms\Components\Select::make('results.quantity.result')
                                    ->label('Результат')
                                    ->options(['pass' => '✅ Соответствует', 'fail' => '❌ Несоответствие'])
                                    ->required(),
                                Forms\Components\TextInput::make('results.quantity.comment')
                                    ->label('Комментарий'),
                            ])->columns(2),

                        Forms\Components\Section::make(InspectionResult::CHECKPOINTS['geometry'])
                            ->schema([
                                Forms\Components\Select::make('results.geometry.result')
                                    ->label('Результат')
                                    ->options(['pass' => '✅ Соответствует', 'fail' => '❌ Несоответствие'])
                                    ->required(),
                                Forms\Components\TextInput::make('results.geometry.measured_value')
                                    ->label('Замеренное значение, мм'),
                                Forms\Components\TextInput::make('results.geometry.comment')
                                    ->label('Комментарий'),
                            ])->columns(3),

                        Forms\Components\Section::make(InspectionResult::CHECKPOINTS['surface'])
                            ->schema([
                                Forms\Components\Select::make('results.surface.result')
                                    ->label('Результат')
                                    ->options(['pass' => '✅ Соответствует', 'fail' => '❌ Несоответствие'])
                                    ->required(),
                                Forms\Components\TextInput::make('results.surface.comment')
                                    ->label('Комментарий'),
                            ])->columns(2),

                        Forms\Components\Select::make('decision')
                            ->label('Итоговое решение')
                            ->options(ReceiptLine::DECISIONS)
                            ->required()
                            ->live()
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('decision_comment')
                            ->label('Обоснование решения')
                            ->required(fn (Forms\Get $get) => in_array($get('decision'), ['accept_with_limitation', 'quarantine', 'return']))
                            ->helperText('Обязательно, если решение отличается от простого "Принять"')
                            ->columnSpanFull(),
                    ])
                    ->action(function (ReceiptLine $record, array $data) {
                        try {
                            app(MaterialReceiptService::class)->recordInspectionResults(
                                $record,
                                $data['results'],
                                $data['decision'],
                                $data['decision_comment'] ?? null
                            );

                            Notification::make()
                                ->title('Входной контроль зафиксирован')
                                ->success()
                                ->send();
                        } catch (\RuntimeException $e) {
                            Notification::make()
                                ->title('🚨 Не удалось сохранить')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Tables\Actions\Action::make('create_nonconformity')
                    ->label('Оформить акт')
                    ->icon('heroicon-m-document-exclamation')
                    ->color('danger')
                    ->visible(fn (ReceiptLine $record) => $record->decision === 'return' || $record->hasFailedCheckpoint())
                    ->url(fn (ReceiptLine $record) => \App\Filament\Resources\NonconformityResource::getUrl('create', [
                        'receipt_line_id' => $record->id,
                        'category' => 'other',
                        'description' => $record->decision_comment,
                    ])),

                Tables\Actions\EditAction::make()
                    ->visible(fn () => $this->getOwnerRecord()->status === 'draft'),

                Tables\Actions\DeleteAction::make()
                    ->visible(fn () => $this->getOwnerRecord()->status === 'draft'),
            ]);
    }
}
