<?php

namespace App\Filament\Pages;

use App\Services\ProductionService;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;

class EquipmentLoad extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';
    protected static ?string $navigationLabel = 'Загрузка оборудования';
    protected static ?string $title = 'Загрузка оборудования';
    protected static ?int $navigationSort = 4;

    protected static string $view = 'filament.pages.equipment-load';

    // Период, за который считаем ФАКТИЧЕСКУЮ загрузку (план/просрочка не зависят от периода —
    // это всегда "текущий снимок" очереди).
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'date_from' => now()->startOfMonth()->toDateString(),
            'date_to' => now()->endOfMonth()->toDateString(),
        ]);
    }

    public function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Период для расчёта фактической загрузки')
                    ->description('Влияет только на колонку "Факт". Плановая загрузка и просрочка всегда показывают актуальный снимок очереди на текущий момент.')
                    ->schema([
                        Forms\Components\Grid::make(2)->schema([
                            Forms\Components\DatePicker::make('date_from')
                                ->label('С')
                                ->native(false)
                                ->displayFormat('d.m.Y')
                                ->live(),

                            Forms\Components\DatePicker::make('date_to')
                                ->label('По')
                                ->native(false)
                                ->displayFormat('d.m.Y')
                                ->live(),
                        ]),
                    ]),
            ])
            ->statePath('data');
    }

    /**
     * Данные отчёта, пересчитываются при каждой отрисовке страницы
     * (учитывают выбранный период факта).
     */
    public function getReport(): array
    {
        $state = $this->form->getState();

        $from = !empty($state['date_from']) ? Carbon::parse($state['date_from']) : null;
        $to = !empty($state['date_to']) ? Carbon::parse($state['date_to']) : null;

        return app(ProductionService::class)->getEquipmentLoadReport($from, $to);
    }

    /**
     * Человекочитаемое форматирование минут в часы (без разбивки на смены,
     * в отличие от formatMinutesToHumanTime — для отчёта удобнее видеть часы).
     */
    public function toHours(float $minutes): string
    {
        return number_format($minutes / 60, 1, ',', ' ') . ' ч.';
    }
}
