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
     * Зафиксировать результаты входного контроля по строке поступления и
     * итоговое решение. Можно вызывать повторно, пока документ в статусе
     * "черновик" — старые результаты по пунктам перезаписываются.
     */
    public function recordInspectionResults(ReceiptLine $line, array $results, string $decision, ?string $decisionComment = null): void
    {
        if ($line->receipt->status === 'posted') {
            throw new \RuntimeException('Документ уже проведён — результаты контроля изменить нельзя.');
        }

        DB::transaction(function () use ($line, $results, $decision, $decisionComment) {
            foreach ($results as $checkpoint => $result) {
                \App\Models\InspectionResult::updateOrCreate(
                    ['receipt_line_id' => $line->id, 'checkpoint' => $checkpoint],
                    [
                        'result' => $result['result'],
                        'measured_value' => $result['measured_value'] ?? null,
                        'comment' => $result['comment'] ?? null,
                    ]
                );
            }

            $line->update([
                'decision' => $decision,
                'decision_comment' => $decisionComment,
                'inspected_by' => auth()->id(),
                'inspected_at' => now(),
            ]);
        });
    }

    /**
     * Провести документ поступления: по каждой строке создаём партию на
     * складе и увеличиваем остаток материала (Material::quantity) в его
     * родной единице (метры/м²/шт) — пересчитанной из веса в кг через
     * плотность привязанной марки стали.
     *
     * ИСПРАВЛЕНО (Этап 3): раньше документ проводился сразу, без входного
     * контроля. Теперь провести можно только если КАЖДАЯ строка получила
     * решение входного контроля (ReceiptLine::decision не пусто) — иначе
     * непроверенный металл мог бы попасть в доступный остаток склада.
     *
     * После проведения документ не редактируется (как и положено проведённым
     * документам в 1С) — исправление возможно только через сторно, это
     * отдельное действие, которое можно добавить позже при необходимости.
     *
     * @throws \RuntimeException если хотя бы одна строка не прошла входной
     *   контроль, либо не может быть пересчитана в родную единицу (материал
     *   не привязан к марке стали, не задан диаметр/толщина, либо не указано
     *   количество штук для покупного изделия) — в этом случае НИЧЕГО не
     *   проводится (всё в одной транзакции), чтобы не оставить документ
     *   проведённым наполовину.
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

        $uninspected = $lines->whereNull('decision');
        if ($uninspected->isNotEmpty()) {
            throw new \RuntimeException(
                'Не все строки прошли входной контроль (строк без решения: ' . $uninspected->count() . ' из ' . $lines->count() . '). '
                . 'Пройдите входной контроль по каждой строке, прежде чем проводить документ.'
            );
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
     *
     * Поведение зависит от решения входного контроля:
     *  - accept / accept_with_limitation → партия сразу доступна, остаток
     *    материала увеличивается;
     *  - quarantine → партия создаётся, но НЕ увеличивает доступный остаток
     *    материала (чтобы цех не мог зарезервировать непроверенный металл),
     *    пока кто-то явно не разрешит её через resolveQuarantineLot();
     *  - return → партия вообще не создаётся, материал на склад не попадает
     *    (физически возвращается поставщику), строка остаётся в истории с
     *    решением "return" для отчётности.
     */
    protected function createLotFromReceiptLine(ReceiptLine $line): ?MaterialLot
    {
        if ($line->decision === 'return') {
            return null;
        }

        $material = $line->material;
        $weightKg = $line->effective_weight_kg;

        [$nativeQuantity, $nativeUnit] = $this->convertWeightToNativeQuantity($material, $weightKg, $line->pieces_count);

        $lotStatus = $line->decision === 'quarantine' ? 'quarantine' : 'available';

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
            'status' => $lotStatus,
            'limitation_note' => $line->decision === 'accept_with_limitation' ? $line->decision_comment : null,
            'received_at' => $line->receipt->receipt_date,
        ]);

        // Доступный остаток увеличиваем ТОЛЬКО для партий, реально готовых к
        // использованию — карантинные партии физически лежат на складе, но
        // резервировать/расходовать их нельзя, пока решение не принято.
        if ($lotStatus === 'available') {
            $material->increment('quantity', $nativeQuantity);
        }

        return $lot;
    }

    /**
     * Разрешить или окончательно забраковать партию, находящуюся в карантине
     * (решение входного контроля "Карантин до решения").
     *
     * @param bool $approve true — разрешить к использованию (остаток материала
     *   увеличивается впервые именно сейчас, при проведении документа он не
     *   учитывался); false — забраковать окончательно, остаток не меняется.
     */
    public function resolveQuarantineLot(MaterialLot $lot, bool $approve, ?string $comment = null): void
    {
        if ($lot->status !== 'quarantine') {
            throw new \RuntimeException('Эта партия не находится в карантине — решать по ней нечего.');
        }

        DB::transaction(function () use ($lot, $approve, $comment) {
            if ($approve) {
                $lot->update([
                    'status' => 'available',
                    'limitation_note' => $comment,
                ]);
                $lot->material->increment('quantity', $lot->remaining_native_quantity);
            } else {
                $lot->update([
                    'status' => 'rejected',
                    'limitation_note' => $comment,
                ]);
                // Остаток материала не трогаем — забракованная партия никогда
                // не учитывалась в доступном количестве.
            }
        });
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
