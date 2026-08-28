<?php

namespace App\Console\Commands;

use App\Models\Material;
use App\Models\Order;
use App\Services\ProductionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Полная пересборка резервов материалов "с нуля".
 *
 * Нужна как разовое лекарство для бага: cancelReservationForOrder() ранее
 * пересчитывала объём для отмены резерва по УЖЕ ИЗМЕНЁННОМУ количеству
 * позиций заказа (после сохранения формы), а не по тому, что было
 * забронировано изначально. Из-за этого при редактировании количества в
 * заказе резерв мог задеть резервы ДРУГИХ заказов на тот же материал, и
 * текущие значения reserved в БД могли стать некорректными.
 *
 * Команда полностью игнорирует накопленную историю reserved и пересчитывает
 * её заново: обнуляет reserved у всех материалов, затем заново суммирует
 * фактическую потребность по всем ЕЩЁ АКТУАЛЬНЫМ заказам (status: pending
 * или in_progress — выполненные/отменённые в резерве не участвуют).
 *
 * Запуск: php artisan materials:rebuild-reservations
 */
class RebuildMaterialReservations extends Command
{
    protected $signature = 'materials:rebuild-reservations {--dry-run : Только показать, что изменится, без реального сохранения}';

    protected $description = 'Полностью пересобрать резервы материалов с нуля на основе актуальных заказов (pending/in_progress)';

    public function handle(ProductionService $service): int
    {
        $isDryRun = $this->option('dry-run');

        $this->info($isDryRun
            ? 'Режим предпросмотра (--dry-run) — в БД ничего не изменится.'
            : 'Пересборка резервов материалов...');

        // Снимок старых значений reserved — для наглядного отчёта в консоли
        $oldReserved = Material::pluck('reserved', 'id')->toArray();

        $activeOrders = Order::whereIn('status', ['pending', 'in_progress'])
            ->with('orderItems.product')
            ->get();

        $this->info("Найдено активных заказов (pending/in_progress): {$activeOrders->count()}");

        if ($isDryRun) {
            // Считаем прогноз В ПАМЯТИ, ничего не сохраняя в БД
            $projectedReserved = [];

            foreach ($activeOrders as $order) {
                foreach ($order->orderItems as $item) {
                    if (!$item->product) {
                        continue;
                    }

                    foreach ($service->calculateRequiredMaterials($item->product, $item->quantity) as $materialId => $volume) {
                        $projectedReserved[$materialId] = ($projectedReserved[$materialId] ?? 0) + $volume;
                    }
                }
            }

            $this->newLine();
            $this->table(
                ['ID материала', 'Марка / наименование', 'Было (резерв)', 'Станет (резерв)', 'Остаток на складе'],
                Material::all()->map(function (Material $material) use ($oldReserved, $projectedReserved) {
                    return [
                        $material->id,
                        "{$material->name} / {$material->grade}",
                        $oldReserved[$material->id] ?? 0,
                        $projectedReserved[$material->id] ?? 0,
                        $material->quantity,
                    ];
                })
            );

            $this->newLine();
            $this->info('Предпросмотр завершён. Запустите без --dry-run, чтобы применить.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($service, $activeOrders) {
            // 1. Обнуляем текущий (потенциально некорректный) резерв у всех материалов
            Material::query()->update(['reserved' => 0]);

            // 2. Заново считаем резерв по всем ещё актуальным заказам
            foreach ($activeOrders as $order) {
                $service->reserveMaterialsForOrder($order);
            }
        });

        $this->newLine();
        $this->table(
            ['ID материала', 'Марка / наименование', 'Было (резерв)', 'Стало (резерв)', 'Остаток на складе'],
            Material::all()->map(function (Material $material) use ($oldReserved) {
                return [
                    $material->id,
                    "{$material->name} / {$material->grade}",
                    $oldReserved[$material->id] ?? 0,
                    $material->reserved,
                    $material->quantity,
                ];
            })
        );

        $this->newLine();
        $this->info('Резервы материалов успешно пересобраны с нуля.');

        return self::SUCCESS;
    }
}
