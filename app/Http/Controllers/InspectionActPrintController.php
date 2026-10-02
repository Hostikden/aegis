<?php

namespace App\Http\Controllers;

use App\Models\Nonconformity;
use App\Models\ReceiptLine;

class InspectionActPrintController extends Controller
{
    /**
     * Печатная форма акта входного контроля по одной строке поступления
     * (одной партии). Содержит реквизиты по ГОСТ 24297 (форма акта/журнала
     * результатов входного контроля) и ГОСТ 7566 (обязательные реквизиты
     * документа о качестве на металлопродукцию).
     */
    public function print(ReceiptLine $receiptLine)
    {
        if (!auth()->user()->hasAnyRole(['admin', 'director', 'manager', 'storekeeper'])) {
            abort(403, 'Доступ к печати документов ограничен.');
        }

        $receiptLine->load([
            'receipt.counterparty',
            'material.steelGrade',
            'inspectionResults',
            'inspectedBy',
            'lot',
        ]);

        // Если по этой строке уже оформлен акт о несоответствии — покажем
        // ссылку на него внизу печатной формы.
        $nonconformity = Nonconformity::where('receipt_line_id', $receiptLine->id)->first();

        return view('print.inspection-act', [
            'line' => $receiptLine,
            'receipt' => $receiptLine->receipt,
            'material' => $receiptLine->material,
            'results' => $receiptLine->inspectionResults->keyBy('checkpoint'),
            'nonconformity' => $nonconformity,
        ]);
    }
}
