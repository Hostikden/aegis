<?php

namespace App\Filament\Pages;

use App\Services\ProductionService;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;

/**
 * Учёт времени работников — сколько технологических этапов выполнил каждый
 * сотрудник за выбранный период и сколько фактического времени на это ушло
 * (started_at → completed_at). Заполняется автоматически: исполнитель
 * выбирается из выпадающего списка при нажатии "Выполнить" на любой из
 * страниц (заказ / инфопанель / планирование по оборудованию).
 */
class WorkerTimeTracking extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $navigationLabel = 'Учёт времени работников';
    protected static ?string $title = 'Учёт времени работников';
    protected static ?int $navigationSort = 6;

    protected static string $view = 'filament.pages.worker-time-tracking';

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
                Forms\Components\Section::make('Период')
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
     * (учитывают выбранный период).
     */
    public function getReport(): array
    {
        $state = $this->form->getState();

        $from = !empty($state['date_from']) ? Carbon::parse($state['date_from']) : null;
        $to = !empty($state['date_to']) ? Carbon::parse($state['date_to']) : null;

        return app(ProductionService::class)->getWorkerTimeReport($from, $to);
    }

    public function toHours(float $minutes): string
    {
        return number_format($minutes / 60, 1, ',', ' ') . ' ч.';
    }
}
