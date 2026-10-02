<?php

namespace App\Filament\Pages;

use App\Models\MaterialDebit;
use App\Services\ProductionService;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Отчёт по расходу материалов за период — для выгрузки в бухгалтерию.
 * Источник — журнал MaterialDebit (Этап 4): каждое списание в производство
 * с точной привязкой к партии/плавке, откуда брался материал.
 */
class MaterialConsumptionReport extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-document-currency-dollar';
    protected static ?string $navigationLabel = 'Расход материалов (для 1С)';
    protected static ?string $title = 'Отчёт по расходу материалов';
    protected static ?string $navigationGroup = 'Входной контроль';
    protected static ?int $navigationSort = 5;

    protected static string $view = 'filament.pages.material-consumption-report';

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
                Forms\Components\Section::make('Период отчёта')
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

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export_csv')
                ->label('Выгрузить CSV')
                ->icon('heroicon-m-arrow-down-tray')
                ->color('success')
                ->action(fn () => $this->exportCsv()),
        ];
    }

    /**
     * Данные отчёта — пересчитываются при каждой отрисовке страницы в
     * выбранном периоде.
     */
    public function getReport(): Collection
    {
        $state = $this->form->getState();

        $from = !empty($state['date_from']) ? Carbon::parse($state['date_from']) : null;
        $to = !empty($state['date_to']) ? Carbon::parse($state['date_to']) : null;

        return app(ProductionService::class)->getMaterialConsumptionReport($from, $to);
    }

    public function exportCsv(): StreamedResponse
    {
        $rows = $this->getReport();

        $fileName = 'rashod_materialov_' . now()->format('Y-m-d_His') . '.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');

            // BOM для корректного отображения кириллицы при открытии в Excel на Windows
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, [
                'Дата списания',
                '№ заказа',
                'Деталь',
                'Чертёж (SKU)',
                'Материал',
                'Марка',
                'Код материала в 1С',
                '№ партии',
                '№ плавки',
                '№ сертификата',
                'Количество',
                'Единица',
            ], ';');

            /** @var MaterialDebit $row */
            foreach ($rows as $row) {
                fputcsv($out, [
                    $row->created_at->format('d.m.Y H:i'),
                    $row->order->order_number ?? '—',
                    $row->product->name ?? '—',
                    $row->product->sku ?? '—',
                    $row->material->name ?? '—',
                    $row->material->grade ?? '—',
                    $row->material->code_1c ?? '',
                    $row->lot->lot_number ?? 'без партии',
                    $row->lot->melt_number ?? '—',
                    $row->lot->certificate_number ?? '—',
                    number_format($row->quantity, 4, '.', ''),
                    $row->lot->native_unit ?? match ($row->material->name ?? '') {
                        'Плита' => 'м²',
                        'Покупное изделие' => 'шт',
                        default => 'м',
                    },
                ], ';');
            }

            fclose($out);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function getTotalQuantityByMaterial(): Collection
    {
        return $this->getReport()
            ->groupBy(fn (MaterialDebit $row) => $row->material->name . ' ' . $row->material->grade)
            ->map(fn (Collection $group) => $group->sum('quantity'));
    }
}
