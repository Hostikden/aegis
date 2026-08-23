<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use App\Models\Material;
use App\Models\MaterialHistory;
use Illuminate\Support\Facades\DB;

class ProductionService
{
    /**
     * ШАГ 1: Постановка в резерв при создании заказа (Учитывает многопозиционность)
     */
    public function reserveMaterialsForOrder(Order $order): void
    {
        $allRequirements = [];

        // Собираем общую потребность в материалах по всем позициям заказа циклом
        foreach ($order->orderItems as $item) {
            if ($item->product) {
                $itemRequirements = $this->calculateRequiredMaterials($item->product, $item->quantity);
                foreach ($itemRequirements as $materialId => $volume) {
                    if (!isset($allRequirements[$materialId])) {
                        $allRequirements[$materialId] = 0;
                    }
                    $allRequirements[$materialId] += $volume;
                }
            }
        }

        // Замораживаем собранные объёмы на складе в рамках одной транзакции
        DB::transaction(function () use ($allRequirements) {
            foreach ($allRequirements as $materialId => $volume) {
                $material = Material::find($materialId);
                if ($material) {
                    $material->increment('reserved', $volume);
                }
            }
        });
    }

    /**
     * ШАГ 2: Умное точечное списание из резерва под конкретную выполняемую деталь
     */
    public function debitMaterialsFromReserve(Order $order, Product $specificProduct): void
    {
        // По умолчанию берем объем из позиций заказа, если это простая деталь верхнего уровня
        $quantityForThisProduct = 0;
        foreach ($order->orderItems as $item) {
            if ($item->product_id === $specificProduct->id) {
                $quantityForThisProduct = $item->quantity;
                break;
            }
        }

        // Если эта деталь не найдена на верхнем уровне, ищем её внутри сборочных узлов заказа
        if ($quantityForThisProduct === 0) {
            foreach ($order->orderItems as $item) {
                if ($item->product && $item->product->type === 'assembly') {
                    $component = $item->product->components()->where('child_id', $specificProduct->id)->first();
                    if ($component && $component->pivot) {
                        $quantityForThisProduct = $component->pivot->quantity * $item->quantity;
                        break;
                    }
                }
            }
        }

        if ($quantityForThisProduct === 0) {
            return; // Защита: деталь не принадлежит спецификациям этого заказа
        }

        // Считаем чистые нормы расхода именно для этой одной детали
        $requirements = $this->calculateRequiredMaterials($specificProduct, $quantityForThisProduct);

        DB::transaction(function () use ($requirements, $order, $specificProduct) {
            foreach ($requirements as $materialId => $volumeToDebit) {
                $material = Material::find($materialId);

                if (!$material) continue;

                if ($material->reserved <= 0) continue;

                // Уменьшаем физический остаток на складе
                $material->decrement('quantity', $volumeToDebit);
                // Снимаем бронь строго в рамках зарезервированного объема
                $material->decrement('reserved', min($material->reserved, $volumeToDebit));

                // Фиксируем операцию расхода в истории склада
                MaterialHistory::create([
                    'material_id' => $material->id,
                    'type' => 'deduction',
                    'quantity' => $volumeToDebit,
                    'description' => "Автосписание под деталь \"{$specificProduct->name}\" (чертёж {$specificProduct->sku}) по заказу №{$order->order_number}",
                ]);
            }
        });
    }
    /**
     * ШАГ 3: Аннулирование резерва при удалении/отмене (Учитывает многопозиционность)
     */
    public function cancelReservationForOrder(Order $order): void
    {
        $allRequirements = [];

        foreach ($order->orderItems as $item) {
            if ($item->product) {
                $itemRequirements = $this->calculateRequiredMaterials($item->product, $item->quantity);
                foreach ($itemRequirements as $materialId => $volume) {
                    if (!isset($allRequirements[$materialId])) {
                        $allRequirements[$materialId] = 0;
                    }
                    $allRequirements[$materialId] += $volume;
                }
            }
        }

        DB::transaction(function () use ($allRequirements) {
            foreach ($allRequirements as $materialId => $volume) {
                $material = Material::find($materialId);
                if ($material) {
                    $newReserved = max(0, $material->reserved - $volume);
                    $material->update(['reserved' => $newReserved]);
                }
            }
        });
    }

    /**
     * ШАГ 4: Умное до-резервирование (Вызывается кнопкой синхронизации из многокомпонентного заказа)
     */
    public function syncAndFixOrderReservations(Order $order): array
    {
        $allRequirements = [];

        foreach ($order->orderItems as $item) {
            if ($item->product) {
                $itemRequirements = $this->calculateRequiredMaterials($item->product, $item->quantity);
                foreach ($itemRequirements as $materialId => $volume) {
                    if (!isset($allRequirements[$materialId])) {
                        $allRequirements[$materialId] = 0;
                    }
                    $allRequirements[$materialId] += $volume;
                }
            }
        }

        $addedCount = 0;
        $warnings = [];

        DB::transaction(function () use ($allRequirements, &$addedCount, &$warnings) {
            foreach ($allRequirements as $materialId => $requiredVolume) {
                $material = Material::find($materialId);

                if (!$material) continue;

                $available = $material->quantity - $material->reserved;

                if ($available < $requiredVolume) {
                    $warnings[] = "🚨 На складе дефицит! Для \"{$material->grade}\" требуется забронировать {$requiredVolume}, но свободно всего {$available}. Пополните склад.";
                }

                $material->increment('reserved', $requiredVolume);
                $addedCount++;
            }
        });

        return [
            'success' => $addedCount > 0,
            'warnings' => $warnings
        ];
    }
    /**
     * ГЛАВНЫЙ КАЛЬКУЛЯТОР: Рекурсивный сбор чистой потребности в металле и комплектующих
     */
    public function calculateRequiredMaterials(Product $product, int $totalQuantity): array
    {
        $requirements = [];

        if ($product->type === 'detail') {
            // Для простой детали собираем её нормы расхода
            foreach ($product->productMaterials as $pm) {
                if ($pm->material_id) {
                    if (!isset($requirements[$pm->material_id])) {
                        $requirements[$pm->material_id] = 0;
                    }
                    $requirements[$pm->material_id] += floatval($pm->consumption_rate) * $totalQuantity;
                }
            }
        } elseif ($product->type === 'assembly') {
            // Для сборки рекурсивно ныряем во все входящие компоненты спецификации
            foreach ($product->components as $component) {
                $componentQuantity = $component->pivot->quantity * $totalQuantity;
                $componentRequirements = $this->calculateRequiredMaterials($component, $componentQuantity);

                foreach ($componentRequirements as $materialId => $volume) {
                    if (!isset($requirements[$materialId])) {
                        $requirements[$materialId] = 0;
                    }
                    $requirements[$materialId] += $volume;
                }
            }
        }

        return $requirements;
    }

    /**
     * РЕКУРСИВНАЯ ВАЛИДАЦИЯ: Проверка наличия заполненного BOM у детали или внутри узлов сборки
     */
    public function hasMaterialsInBom(Product $product): bool
    {
        if ($product->type === 'detail') {
            return $product->productMaterials()->count() > 0;
        }

        if ($product->type === 'assembly') {
            if ($product->components()->count() === 0) {
                return false;
            }

            foreach ($product->components as $component) {
                if ($this->hasMaterialsInBom($component)) {
                    return true;
                }
            }
        }

        return false;
    }
    /**
     * Расчет общего времени изготовления изделия (в минутах)
     */
    public function calculateProductionTimeInMinutes(Product $product, int $orderQuantity): float
    {
        $totalMinutes = 0;

        if ($product->type === 'detail') {
            foreach ($product->operations as $operation) {
                $pieceTime = floatval($operation->piece_time ?? 0);
                $prepTime = floatval($operation->prep_time ?? 0);
                $totalMinutes += $prepTime + ($pieceTime * $orderQuantity);
            }
        } elseif ($product->type === 'assembly') {
            foreach ($product->components as $component) {
                $totalComponentQuantity = $component->pivot->quantity * $orderQuantity;
                $totalMinutes += $this->calculateProductionTimeInMinutes($component, $totalComponentQuantity);
            }
            // Настраиваемое время на финальную сборку самого узла (0 минут)
            $totalMinutes += 0;
        }

        return $totalMinutes;
    }

    /**
     * Расчет оставшегося времени работы по многокомпонентному заказу (в минутах)
     */
    public function calculateRemainingProductionTimeInMinutes(Order $order): float
    {
        if (in_array($order->status, ['completed', 'cancelled'])) {
            return 0;
        }

        $remainingMinutes = 0;
        $activeTasks = $order->productionTasks()->where('status', '!=', 'completed')->get();

        foreach ($activeTasks as $task) {
            foreach ($order->orderItems as $item) {
                $product = $item->product;
                if (!$product) continue;

                if ($product->type === 'detail' && str_contains($task->operation_name, "({$product->sku})")) {
                    foreach ($product->operations as $operation) {
                        if (stripos($task->operation_name, $operation->operation_name) !== false) {
                            $remainingMinutes += floatval($operation->prep_time ?? 0) + (floatval($operation->piece_time ?? 0) * $item->quantity);
                            break 2;
                        }
                    }
                } elseif ($product->type === 'assembly') {
                    foreach ($product->components as $component) {
                        if (str_contains($task->operation_name, "({$component->sku})")) {
                            foreach ($component->operations as $operation) {
                                if (stripos($task->operation_name, $operation->operation_name) !== false) {
                                    $totalQty = $component->pivot->quantity * $item->quantity;
                                    $remainingMinutes += floatval($operation->prep_time ?? 0) + (floatval($operation->piece_time ?? 0) * $totalQty);
                                    break 3;
                                }
                            }
                        }
                    }
                    if (stripos($task->operation_name, 'Финальная сборка узла') !== false && str_contains($task->operation_name, "{$product->name}")) {
                        $remainingMinutes += 0;
                    }
                }
            }
        }

        return $remainingMinutes;
    }

    /**
     * Отформатировать минуты в красивую строку на основе 8-часового рабочего дня (смены)
     */
    public function formatMinutesToHumanTime(float $minutes): string
    {
        if ($minutes <= 0) {
            return 'не задано';
        }

        $minutes = round($minutes);

        // 1 рабочий день (смена) = 8 часов * 60 минут = 480 минут
        $workDays = floor($minutes / 480);
        $remainingMinutesAfterDays = $minutes % 480;
        $hours = floor($remainingMinutesAfterDays / 60);
        $remainingMinutes = $remainingMinutesAfterDays % 60;

        $result = [];
        if ($workDays > 0) {
            $result[] = "{$workDays} раб. дн.";
        }
        if ($hours > 0) {
            $result[] = "{$hours} ч.";
        }
        if ($remainingMinutes > 0 || empty($result)) {
            $result[] = "{$remainingMinutes} мин.";
        }

        return implode(' ', $result);
    }
} // Конец класса ProductionService
