<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Material;
use App\Models\MaterialHistory;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

class ProductionService
{
    /**
     * Рекурсивный расчет потребности в сырье для изделия (детали или сборки)
     */
    public function calculateRequiredMaterials(Product $product, float $quantity): array
    {
        $materialsNeed = [];

        if ($product->type === 'detail') {
            foreach ($product->productMaterials as $pm) {
                $totalNeed = $pm->consumption_rate * $quantity;
                $materialsNeed[$pm->material_id] = ($materialsNeed[$pm->material_id] ?? 0) + $totalNeed;
            }
        } elseif ($product->type === 'assembly') {
            foreach ($product->components as $component) {
                $componentQuantity = $component->pivot->quantity * $quantity;
                $subMaterials = $this->calculateRequiredMaterials($component, $componentQuantity);
                foreach ($subMaterials as $materialId => $neededVolume) {
                    $materialsNeed[$materialId] = ($materialsNeed[$materialId] ?? 0) + $neededVolume;
                }
            }
        }

        return $materialsNeed;
    }

    /**
     * ШАГ 1: Поставить материалы в резерв (Вызывается при создании заказа)
     */
    public function reserveMaterialsForOrder(Order $order): void
    {
        $requirements = $this->calculateRequiredMaterials($order->product, $order->total_quantity);

        DB::transaction(function () use ($requirements) {
            foreach ($requirements as $materialId => $volume) {
                $material = Material::findOrFail($materialId);
                $material->increment('reserved', $volume);
            }
        });
    }





       /**
     * ШАГ 2: Умное точечное списание из резерва (Вызывается для конкретной детали)
     */
    public function debitMaterialsFromReserve(Order $order, Product $specificProduct): void
    {
        // 1. Рассчитываем потребность в металле СТРОГО для этой одной конкретной детали
        // Нам нужно учесть, сколько этой детали требуется на 1 сборку и умножить на объем заказа
        $quantityForThisProduct = $order->total_quantity;

        // Если эта деталь входит в состав сборки, ищем её коэффициент (количество на 1 узел)
        if ($order->product->type === 'assembly') {
            $component = $order->product->components()->where('child_id', $specificProduct->id)->first();
            if ($component && $component->pivot) {
                $quantityForThisProduct = $component->pivot->quantity * $order->total_quantity;
            }
        }

        // Считаем чистые нормы расхода именно для этой детали
        $requirements = $this->calculateRequiredMaterials($specificProduct, $quantityForThisProduct);

        DB::transaction(function () use ($requirements, $order, $specificProduct) {
            foreach ($requirements as $materialId => $volumeToDebit) {
                $material = Material::find($materialId);

                if (!$material) continue;

                // Защита: Проверяем, не списали ли мы этот материал ранее
                // (если резерв уже равен 0, значит списание по этой детали уже прошло)
                if ($material->reserved <= 0) {
                    continue;
                }

                // Уменьшаем физический остаток на складе
                $material->decrement('quantity', $volumeToDebit);
                // Снимаем бронь строго в рамках зарезервированного объема
                $material->decrement('reserved', min($material->reserved, $volumeToDebit));

                // Фиксируем точечную операцию расхода в истории
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
     * ШАГ 3: Аннулирование резерва (Вызывается при отмене ИЛИ удалении заказа)
     */
    public function cancelReservationForOrder(Order $order): void
    {
        // Рекурсивно собираем всю потребность в металле и штуках для этого изделия
        $requirements = $this->calculateRequiredMaterials($order->product, $order->total_quantity);

        DB::transaction(function () use ($requirements) {
            foreach ($requirements as $materialId => $volume) {
                $material = Material::find($materialId);

                if ($material) {
                    // Уменьшаем резерв, но следим, чтобы он не ушел в минус ниже нуля (защита базы)
                    $newReserved = max(0, $material->reserved - $volume);
                    $material->update(['reserved' => $newReserved]);
                }
            }
        });
    }



    /**
     * ШАГ 4: Умное до-резервирование (Вызывается кнопкой из заказа при изменении BOM технологом)
     */
    public function syncAndFixOrderReservations(Order $order): array
    {
        // Рассчитываем чистую актуальную потребность в металле на текущую секунду
        $currentRequirements = $this->calculateRequiredMaterials($order->product, $order->total_quantity);

        $addedCount = 0;
        $warnings = [];

        DB::transaction(function () use ($currentRequirements, &$addedCount, &$warnings) {
            foreach ($currentRequirements as $materialId => $requiredVolume) {
                $material = Material::find($materialId);

                if (!$material) {
                    continue;
                }

                // Проверяем, хватает ли свободного металла на складе для обеспечения новой брони
                $available = $material->quantity - $material->reserved;

                if ($available < $requiredVolume) {
                    $warnings[] = "🚨 На складе дефицит! Для \"{$material->grade}\" требуется забронировать {$requiredVolume}, но свободно всего {$available}. Пополните склад.";
                }

                // Накатываем честный актуальный резерв
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
     * Рассчитать общее время изготовления изделия (в минутах) с учетом Тшт и Тпз
     */
    public function calculateProductionTimeInMinutes(Product $product, int $orderQuantity): float
    {
        $totalMinutes = 0;

        if ($product->type === 'detail') {
            // Если это деталь, берем время из её техпроцесса
            foreach ($product->operations as $operation) {
                $pieceTime = floatval($operation->piece_time ?? 0);
                $prepTime = floatval($operation->prep_time ?? 0);

                // Формула: Тпз + (Тшт * Кол-во деталей)
                $totalMinutes += $prepTime + ($pieceTime * $orderQuantity);
            }
        } elseif ($product->type === 'assembly') {
            // Если это сборка, рекурсивно считаем время изготовления всех деталей
            foreach ($product->components as $component) {
                // Кол-во детали в 1 сборке * Общий объем заказа на сборку
                $totalComponentQuantity = $component->pivot->quantity * $orderQuantity;

                $totalMinutes += $this->calculateProductionTimeInMinutes($component, $totalComponentQuantity);
            }

            // Добавляем 60 минут на финальную сборку самого узла в цеху
            $totalMinutes += 60;
        }

        return $totalMinutes;
    }

    /**
     * Отформатировать минуты в красивую строку (дни, часы, минуты)
     */
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

        // Остаток минут после вычета полных рабочих дней переводим в часы
        $remainingMinutesAfterDays = $minutes % 480;
        $hours = floor($remainingMinutesAfterDays / 60);

        // Остаток чистых минут
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









        /**
     * Рекурсивная проверка: есть ли вообще материалы (BOM) у детали или внутри компонентов сборки
     */
    public function hasMaterialsInBom(Product $product): bool
    {
        if ($product->type === 'detail') {
            // Для простой детали проверяем её прямую спецификацию
            return $product->productMaterials()->count() > 0;
        }

        if ($product->type === 'assembly') {
            // Для сборки проверяем, есть ли материалы хотя бы у одной из входящих деталей
            if ($product->components()->count() === 0) {
                return false;
            }

            foreach ($product->components as $component) {
                if ($this->hasMaterialsInBom($component)) {
                    return true; // Нашли металл во вложенной детали — сборка валидна!
                }
            }
        }

        return false;
    }







        /**
     * Рассчитать оставшееся время работы по заказу (в минутах) на основе только незакрытых операций
     */
    public function calculateRemainingProductionTimeInMinutes(Order $order): float
    {
        // Если заказ уже полностью выполнен или отменен — остаток времени равен нулю
        if (in_array($order->status, ['completed', 'cancelled'])) {
            return 0;
        }

        $remainingMinutes = 0;
        $product = $order->product;

        if (!$product) {
            return 0;
        }

        // Вытаскиваем из базы данных все технологические задачи этого заказа, которые еще НЕ выполнены
        $activeTasks = $order->productionTasks()->where('status', '!=', 'completed')->get();

        if ($product->type === 'detail') {
            // Для простой детали сопоставляем активные задачи с нормами времени из техпроцесса
            foreach ($activeTasks as $task) {
                foreach ($product->operations as $operation) {
                    // Ищем связь по названию операции (например, "Токарная")
                    if (stripos($task->operation_name, "[{$operation->operation_name}]") !== false ||
                        stripos($task->operation_name, $operation->operation_name) !== false) {

                        $pieceTime = floatval($operation->piece_time ?? 0);
                        $prepTime = floatval($operation->prep_time ?? 0);

                        // Прибавляем время невыполненного этапа: Тпз + (Тшт * Объем заказа)
                        $remainingMinutes += $prepTime + ($pieceTime * $order->total_quantity);
                        break;
                    }
                }
            }
        } elseif ($product->type === 'assembly') {
            // Для сборки проходим по всем незакрытым задачам (включая вложенные детали)
            foreach ($activeTasks as $task) {
                // Ищем, к какому компоненту сборки относится эта незакрытая задача
                foreach ($product->components as $component) {
                    if (str_contains($task->operation_name, "({$component->sku})") ||
                        str_contains($task->operation_name, "{$component->sku}")) {

                        // Ищем норму времени операции внутри этой вложенной детали
                        foreach ($component->operations as $operation) {
                            if (stripos($task->operation_name, "[{$operation->operation_name}]") !== false ||
                                stripos($task->operation_name, $operation->operation_name) !== false) {

                                $totalComponentQuantity = $component->pivot->quantity * $order->total_quantity;
                                $remainingMinutes += floatval($operation->prep_time ?? 0) + (floatval($operation->piece_time ?? 0) * $totalComponentQuantity);
                                break 2; // Переходим к следующей задаче
                            }
                        }
                    }
                }

                // Если это незакрытый этап финальной сборки самого узла, добавляем оставшиеся 60 минут
                if (stripos($task->operation_name, 'Финальная сборка узла') !== false) {
                    $remainingMinutes += 60;
                }
            }
        }

        return $remainingMinutes;
    }



}
