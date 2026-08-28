<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductionTask;
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
     * Проверяет, началось ли уже реальное производство по заказу — то есть есть
     * ли хотя бы один технологический этап со статусом "в работе" или "выполнен".
     *
     * Используется, чтобы не допустить автоматический пересчёт технологических
     * задач поверх уже реального прогресса цеха при изменении количества в
     * заказе на странице редактирования.
     */
    public function hasProductionStarted(Order $order): bool
    {
        return $order->productionTasks()->where('status', '!=', 'pending')->exists();
    }

    /**
     * Полностью пересоздаёт технологические задачи заказа на основе его текущих
     * позиций (orderItems) — вызывается при изменении количества на странице
     * редактирования заказа. Безопасно вызывать ТОЛЬКО когда
     * hasProductionStarted() === false, иначе будет удалён реальный прогресс
     * производства (задачи "в работе"/"выполнено").
     *
     * @param array $previousItemQuantities Снимок [product_id => quantity] ДО того,
     *   как форма была сохранена. ИСПРАВЛЕНО: раньше отмена старого резерва
     *   считалась по $order->orderItems, но на момент вызова afterSave() Filament
     *   уже успевает сохранить НОВОЕ количество в БД — то есть "отмена" на самом
     *   деле вычитала объём по новому, а не по старому количеству. Из-за этого
     *   при увеличении количества с материала списывалось больше резерва, чем
     *   реально держал этот заказ, и задевало резервы ДРУГИХ заказов на тот же
     *   материал. Теперь отмена всегда идёт по явно переданному снимку старых
     *   количеств, а не по уже перезаписанным данным.
     */
    public function regenerateProductionTasksForOrder(Order $order, array $previousItemQuantities = []): void
    {
        // Снимаем ИМЕННО СТАРЫЙ резерв (по количествам ДО правки), чтобы не задеть
        // резервы других заказов на тот же материал.
        if (!empty($previousItemQuantities)) {
            $this->cancelReservationForQuantities($previousItemQuantities);
        } else {
            // Фолбэк на случай вызова без снимка (например, из другого места кода) —
            // прежнее поведение, менее точное, но лучше, чем ничего.
            $this->cancelReservationForOrder($order);
        }

        // Удаляем старые технологические задачи заказа (все ещё в статусе "pending",
        // это гарантировано вызывающим кодом через hasProductionStarted())
        $order->productionTasks()->delete();

        foreach ($order->orderItems as $item) {
            if ($item->product) {
                $this->generateTasksForProduct($order, $item->product, $item->quantity);
            }
        }

        // Ставим материалы в резерв заново — уже под обновлённое количество
        $this->reserveMaterialsForOrder($order);
    }

    /**
     * Отменяет резерв материалов по явно переданному набору [product_id => quantity],
     * а не по текущим (уже изменённым) позициям заказа. Используется
     * regenerateProductionTasksForOrder(), чтобы корректно снять именно СТАРЫЙ
     * резерв перед пересчётом под новое количество.
     */
    protected function cancelReservationForQuantities(array $itemQuantities): void
    {
        $allRequirements = [];

        foreach ($itemQuantities as $productId => $quantity) {
            $product = Product::find($productId);
            if ($product) {
                $itemRequirements = $this->calculateRequiredMaterials($product, $quantity);
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
     * Рекурсивный метод создания технологических задач для рабочих с расчётом Item ID.
     *
     * Перенесено сюда из CreateOrder::generateTasksForProduct(), чтобы одна и та же
     * логика использовалась и при первичном создании заказа (CreateOrder::afterCreate),
     * и при пересчёте после изменения количества (regenerateProductionTasksForOrder).
     */
    public function generateTasksForProduct(Order $order, Product $product, int $requiredQuantity): void
    {
        if ($product->type === 'detail') {
            $maxItemNumber = ProductionTask::max('item_number');
            $nextItemNumber = $maxItemNumber ? ($maxItemNumber + 1) : 10000;

            if ($product->operations()->count() > 0) {
                foreach ($product->operations as $operation) {
                    $pieceTime = floatval($operation->piece_time ?? 0);
                    $prepTime = floatval($operation->prep_time ?? 0);

                    $order->productionTasks()->create([
                        'item_number' => $nextItemNumber,
                        'operation_name' => "🌟 Item: {$nextItemNumber} | Опер. {$operation->operation_number} [{$operation->operation_name}] — {$product->name} (чёртеж {$product->sku})",
                        'equipment_type' => $operation->operation_name,
                        'status' => 'pending',
                        'quantity_to_do' => $requiredQuantity,
                        'planned_minutes' => $prepTime + ($pieceTime * $requiredQuantity),
                        // Новая задача встаёт в конец очереди своего типа оборудования —
                        // дальше диспетчер может вручную поднять её выше на странице
                        // "Планирование по оборудованию" (drag-and-drop).
                        'queue_position' => $this->nextQueuePosition($operation->operation_name),
                    ]);
                }
            } else {
                $order->productionTasks()->create([
                    'item_number' => $nextItemNumber,
                    'operation_name' => "🌟 Item: {$nextItemNumber} | Производство детали: {$product->name} (чёртеж {$product->sku}) — Техпроцесс не задан!",
                    'equipment_type' => null,
                    'status' => 'pending',
                    'quantity_to_do' => $requiredQuantity,
                    'planned_minutes' => 0,
                ]);
            }
        } elseif ($product->type === 'assembly') {
            // Если в одной из позиций заказа указана сборка, бежим по её деталям
            foreach ($product->components as $component) {
                // Рассчитываем количество: сколько детали нужно на 1 узел * количество узлов в данной позиции
                $totalComponentQuantity = $component->pivot->quantity * $requiredQuantity;

                // Рекурсивно создаём задачи для каждого вложенного компонента
                $this->generateTasksForProduct($order, $component, $totalComponentQuantity);
            }

            // Создаём финальную сборочную операцию для самого узла данной позиции
            $maxItemNumber = ProductionTask::max('item_number');
            $nextItemNumber = $maxItemNumber ? ($maxItemNumber + 1) : 10000;

            $order->productionTasks()->create([
                'item_number' => $nextItemNumber,
                'operation_name' => "📦 Item: {$nextItemNumber} | Финальная сборка узла: {$product->name} (чёртеж {$product->sku})",
                'equipment_type' => 'Сборка',
                'status' => 'pending',
                'quantity_to_do' => $requiredQuantity,
                'planned_minutes' => 0,
                'queue_position' => $this->nextQueuePosition('Сборка'),
            ]);
        }
    }

    /**
     * Следующая свободная позиция в очереди для конкретного типа оборудования —
     * новая задача встаёт в конец очереди этого типа. Для задач без техпроцесса
     * (equipment_type = null) очерёдность не имеет смысла.
     */
    protected function nextQueuePosition(?string $equipmentType): ?int
    {
        if (!$equipmentType) {
            return null;
        }

        $max = ProductionTask::where('equipment_type', $equipmentType)->max('queue_position');

        return $max !== null ? $max + 1 : 1;
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
     * ЕДИНАЯ точка завершения технологической операции.
     *
     * Раньше эта логика была написана только внутри
     * ProductionTasksRelationManager::table()->actions() (кнопка "Выполнить"
     * на странице заказа), и дублировать её ещё раз для кнопки на дашборде
     * означало бы в третий раз переписать одну и ту же логику списания
     * материала — а именно из-за таких дублей в проекте уже несколько раз
     * возникали расхождения (статусы, форматы operation_name и т.д.).
     * Теперь и RelationManager, и Dashboard вызывают этот метод.
     *
     * @return array{
     *     success: bool,
     *     error: string|null,
     *     order: \App\Models\Order|null,
     *     order_completed: bool,
     * }
     */
    public function completeProductionTask(ProductionTask $task): array
    {
        $order = $task->order;

        if ($order && stripos($task->operation_name, 'Заготовительная') !== false) {
            // $order->product всегда null для многопозиционных заказов
            // (product_id больше не заполняется формой заказа). Ищем нужную
            // деталь по SKU, зашитому в operation_name, обходя все позиции
            // заказа и, рекурсивно, все компоненты сборок.
            $targetProduct = $this->resolveProductForTask($order, $task->operation_name);

            if (!$targetProduct) {
                return [
                    'success' => false,
                    'error' => 'Не удалось определить деталь по названию операции — списание материала невозможно. Проверьте, что operation_name содержит артикул (SKU) детали.',
                    'order' => $order,
                    'order_completed' => false,
                ];
            }

            if (!$this->hasMaterialsInBom($targetProduct)) {
                return [
                    'success' => false,
                    'error' => 'Для обрабатываемой детали не настроены нормы расхода материалов (BOM). Списание невозможно.',
                    'order' => $order,
                    'order_completed' => false,
                ];
            }

            $this->debitMaterialsFromReserve($order, $targetProduct);
        }

        $task->update([
            'status' => 'completed',
            // Фиксируем фактическое время окончания этапа — нужно для отчёта
            // по загрузке оборудования (getEquipmentLoadReport), иначе колонка
            // "Факт" там всегда будет нулевой.
            'completed_at' => now(),
        ]);

        $orderCompleted = false;

        if ($order) {
            if ($order->status === 'pending') {
                $order->update(['status' => 'in_progress']);
            }

            $uncompletedTasksCount = $order->productionTasks()
                ->where('id', '!=', $task->id)
                ->where('status', '!=', 'completed')
                ->count();

            if ($uncompletedTasksCount === 0) {
                $order->update(['status' => 'completed']);
                $orderCompleted = true;
            }
        }

        return [
            'success' => true,
            'error' => null,
            'order' => $order,
            'order_completed' => $orderCompleted,
        ];
    }

    /**
     * Находит конкретную деталь (Product), к которой относится технологическая задача,
     * сопоставляя её SKU с текстом operation_name (в него SKU зашивается при генерации
     * задач в CreateOrder::generateTasksForProduct(), например "... (чёртеж SKU-123)").
     *
     * Обходит все позиции многокомпонентного заказа (orderItems) и, если позиция —
     * сборка, рекурсивно все входящие в неё компоненты.
     */
    public function resolveProductForTask(Order $order, string $operationName): ?Product
    {
        foreach ($order->orderItems as $item) {
            if (!$item->product) {
                continue;
            }

            $found = $this->findProductBySkuInText($item->product, $operationName);

            if ($found) {
                return $found;
            }
        }

        return null;
    }

    protected function findProductBySkuInText(Product $product, string $text): ?Product
    {
        if ($product->sku && str_contains($text, (string) $product->sku)) {
            return $product;
        }

        if ($product->type === 'assembly') {
            foreach ($product->components as $component) {
                $found = $this->findProductBySkuInText($component, $text);

                if ($found) {
                    return $found;
                }
            }
        }

        return null;
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

                // ИСПРАВЛЕНО: CreateOrder::generateTasksForProduct() пишет артикул в виде
                // "(чёртеж {$product->sku})", а не "({$product->sku})" — из-за слова
                // "чёртеж" внутри скобок старое сравнение никогда не совпадало, и
                // оставшееся время всегда считалось нулевым.
                if ($product->type === 'detail' && str_contains($task->operation_name, "(чёртеж {$product->sku})")) {
                    foreach ($product->operations as $operation) {
                        if (stripos($task->operation_name, $operation->operation_name) !== false) {
                            $remainingMinutes += floatval($operation->prep_time ?? 0) + (floatval($operation->piece_time ?? 0) * $item->quantity);
                            break 2;
                        }
                    }
                } elseif ($product->type === 'assembly') {
                    foreach ($product->components as $component) {
                        if (str_contains($task->operation_name, "(чёртеж {$component->sku})")) {
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
     * Отчёт по загрузке оборудования, сгруппированный по типу операции
     * (Токарная / Фрезерная / Сварочная и т.д.).
     *
     * Для каждого типа возвращает:
     *  - backlog_minutes: плановая трудоёмкость всех ещё не завершённых задач
     *    этого типа по всем заказам ("сколько стоит в очереди")
     *  - overdue_minutes: то же самое, но только по заказам с просроченным
     *    дедлайном (deadline < сегодня) и незавершённым статусом
     *  - fact_minutes: суммарное фактическое время (completed_at - started_at)
     *    по задачам, завершённым в переданном периоде [$from; $to]
     *
     * @return array<string, array{backlog_minutes: float, overdue_minutes: float, fact_minutes: float}>
     */
    public function getEquipmentLoadReport(?\Carbon\Carbon $from = null, ?\Carbon\Carbon $to = null): array
    {
        $report = [];

        $ensureType = function (string $type) use (&$report) {
            if (!isset($report[$type])) {
                $report[$type] = [
                    'backlog_minutes' => 0.0,
                    'overdue_minutes' => 0.0,
                    'fact_minutes' => 0.0,
                ];
            }
        };

        // --- План: всё, что ещё не выполнено ---
        ProductionTask::query()
            ->whereNotNull('equipment_type')
            ->where('status', '!=', 'completed')
            ->with('order')
            ->get()
            ->each(function (ProductionTask $task) use (&$report, $ensureType) {
                $type = $task->equipment_type;
                $ensureType($type);

                $report[$type]['backlog_minutes'] += (float) $task->planned_minutes;

                $deadline = $task->order?->deadline;
                if ($deadline && $deadline->isPast() && $task->order->status !== 'completed') {
                    $report[$type]['overdue_minutes'] += (float) $task->planned_minutes;
                }
            });

        // --- Факт: что реально выполнено за период ---
        $factQuery = ProductionTask::query()
            ->whereNotNull('equipment_type')
            ->whereNotNull('started_at')
            ->whereNotNull('completed_at');

        if ($from) {
            $factQuery->where('completed_at', '>=', $from->copy()->startOfDay());
        }
        if ($to) {
            $factQuery->where('completed_at', '<=', $to->copy()->endOfDay());
        }

        $factQuery->get()->each(function (ProductionTask $task) use (&$report, $ensureType) {
            $type = $task->equipment_type;
            $ensureType($type);

            $minutes = $task->started_at->diffInMinutes($task->completed_at);
            $report[$type]['fact_minutes'] += $minutes;
        });

        ksort($report);

        return $report;
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
