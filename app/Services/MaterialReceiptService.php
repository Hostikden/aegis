<?php

namespace App\Services;

use App\Models\Material;
use App\Models\MaterialLot;
use App\Models\Receipt;
use App\Models\ReceiptLine;
use Illuminate\Support\Facades\DB;

class MaterialReceiptService
{
    /**
     * Провести документ поступления: по каждой строке создаём партию на
     * складе и увеличиваем остаток материала (Material::quantity) в его
     * родной единице (метры/м²/шт) — пересчитанной из веса в кг через
     * плотность привязанной марки стали.
     *
     * После проведения документ не редактируется (как и положено проведённым
     * документам в 1С) — исправление возможно только через сторно, это
     * отдельное действие, которое можно добавить позже при необходимости.
     *
     * @throws \RuntimeException если хотя бы одна строка не может быть
     *   пересчитана в родную единицу (материал не привязан к марке стали,
     *   не задан диаметр/толщина, либо не указано количество штук для
     *   покупного изделия) — в этом случае НИЧЕГО не проводится (всё в
     *   одной транзакции), чтобы не оставить документ проведённым наполовину.
     */
    public function postReceipt(Receipt $receipt): void
    {
        if ($receipt->status === 'posted') {
            throw new \RuntimeException('Документ уже проведён.');
        }

        $lines = $receipt->lines()->with('material.steelGrade')->get();

        if ($lines->isEmpty()) {
            throw new \RuntimeException('В документе нет ни одной строки — нечего проводить.');
        }

        DB::transaction(function () use ($receipt, $lines) {
            foreach ($lines as $line) {
                $this->createLotFromReceiptLine($line);
            }

            $receipt->update([
                'status' => 'posted',
                'posted_at' => now(),
                'posted_by' => auth()->id(),
            ]);
        });
    }

    /**
     * Пересчитать вес строки поступления (кг) в родную единицу материала и
     * создать под неё партию на складе.
     */
    protected function createLotFromReceiptLine(ReceiptLine $line): MaterialLot
    {
        $material = $line->material;
        $weightKg = $line->effective_weight_kg;

        [$nativeQuantity, $nativeUnit] = $this->convertWeightToNativeQuantity($material, $weightKg, $line->pieces_count);

        $lot = MaterialLot::create([
            'lot_number' => MaterialLot::generateNextLotNumber(),
            'material_id' => $material->id,
            'receipt_line_id' => $line->id,
            'melt_number' => $line->melt_number,
            'certificate_number' => $line->certificate_number,
            'weight_kg' => $weightKg,
            'remaining_weight_kg' => $weightKg,
            'native_quantity' => $nativeQuantity,
            'remaining_native_quantity' => $nativeQuantity,
            'native_unit' => $nativeUnit,
            // Этап 3 (входной контроль) переопределит статус на 'quarantine'
            // для непроверенных партий — пока проводим сразу как доступные.
            'status' => 'available',
            'received_at' => $line->receipt->receipt_date,
        ]);

        // Материал остаётся единым источником истины для резервирования и
        // списания (ProductionService) — партии лишь дают прослеживаемость
        // поверх него, поэтому просто увеличиваем общий остаток.
        $material->increment('quantity', $nativeQuantity);

        return $lot;
    }

    /**
     * @return array{0: float, 1: string} [количество в родной единице, единица измерения]
     */
    protected function convertWeightToNativeQuantity(Material $material, float $weightKg, ?int $piecesCount): array
    {
        if ($material->name === 'Покупное изделие') {
            if (!$piecesCount || $piecesCount <= 0) {
                throw new \RuntimeException(
                    "Материал «{$material->grade}» — покупное изделие: укажите количество штук в строке поступления, вес в кг для пересчёта здесь не применяется."
                );
            }

            return [(float) $piecesCount, 'шт'];
        }

        $weightPerUnit = $material->calculateTheoreticalWeightPerUnit();

        if (!$weightPerUnit || $weightPerUnit <= 0) {
            throw new \RuntimeException(
                "Материал «{$material->name} {$material->grade}» нельзя пересчитать из кг: не привязана марка стали в справочнике, либо не заполнен диаметр/толщина стенки/толщина плиты в карточке материала."
            );
        }

        $nativeUnit = match ($material->name) {
            'Плита' => 'м²',
            default => 'м', // Пруток, Труба
        };

        return [$weightKg / $weightPerUnit, $nativeUnit];
    }

    /**
     * Завести партию под уже существующий остаток материала (из версии 1,
     * где партий не было вообще). Партия создаётся без плавки и сертификата,
     * с количеством, равным текущему Material::quantity — то есть НИЧЕГО не
     * добавляет на склад, просто даёт уже имеющемуся остатку "дом" в виде
     * партии, с которой дальше можно списывать (в том числе вручную через
     * вкладку "История") так же, как с любой другой партией.
     *
     * @throws \RuntimeException если у материала уже есть хотя бы одна партия
     *   (нет смысла заводить начальный остаток повторно), нечего заводить
     *   (остаток нулевой), либо материал нельзя пересчитать в кг (для
     *   проката — не привязана марка стали или не заполнены размеры).
     */
    public function createOpeningBalanceLot(Material $material): MaterialLot
    {
        if ($material->lots()->exists()) {
            throw new \RuntimeException('У этого материала уже есть партии — начальный остаток заводить не нужно.');
        }

        if ($material->quantity <= 0) {
            throw new \RuntimeException('Текущий остаток материала нулевой — заводить партию не из чего.');
        }

        if ($material->name === 'Покупное изделие') {
            $weightKg = 0.0; // Покупные изделия по весу не учитываются, только поштучно.
            $nativeUnit = 'шт';
        } else {
            $weightPerUnit = $material->calculateTheoreticalWeightPerUnit();

            if (!$weightPerUnit || $weightPerUnit <= 0) {
                throw new \RuntimeException(
                    "Материал «{$material->name} {$material->grade}» нельзя пересчитать в кг: сначала привяжите марку стали и заполните диаметр/толщину стенки/толщину плиты в карточке материала."
                );
            }

            $weightKg = $material->quantity * $weightPerUnit;
            $nativeUnit = $material->name === 'Плита' ? 'м²' : 'м';
        }

        return MaterialLot::create([
            'lot_number' => MaterialLot::generateNextLotNumber(),
            'material_id' => $material->id,
            'receipt_line_id' => null,
            'melt_number' => null,
            'certificate_number' => null,
            'weight_kg' => $weightKg,
            'remaining_weight_kg' => $weightKg,
            'native_quantity' => $material->quantity,
            'remaining_native_quantity' => $material->quantity,
            'native_unit' => $nativeUnit,
            'status' => 'available',
            'received_at' => now(),
        ]);
    }
}
