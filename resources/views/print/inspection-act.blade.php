<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Акт входного контроля — {{ $receipt->receipt_number }}</title>
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; color: #333; margin: 0; padding: 20px; background-color: #f4f4f4; }
        .page { background: #fff; padding: 30px; margin: 0 auto; width: 210mm; min-height: 297mm; box-shadow: 0 0 10px rgba(0,0,0,0.1); box-sizing: border-box; }

        .title-block { font-size: 20px; font-weight: bold; text-align: center; text-transform: uppercase; margin-bottom: 4px; letter-spacing: 1px; }
        .subtitle-block { font-size: 12px; text-align: center; color: #666; margin-bottom: 20px; }

        .header-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .header-table td { border: 1px solid #000; padding: 8px 10px; font-size: 13px; vertical-align: top; }
        .header-table .label { color: #666; font-size: 11px; display: block; margin-bottom: 3px; }

        .route-table { width: 100%; border-collapse: collapse; margin-top: 10px; margin-bottom: 20px; }
        .route-table th { border: 1px solid #000; background-color: #f2f2f2; padding: 8px; font-size: 12px; text-transform: uppercase; }
        .route-table td { border: 1px solid #000; padding: 10px; font-size: 13px; vertical-align: top; }
        .center { text-align: center; }

        .decision-block { border: 2px solid #000; padding: 14px; margin-bottom: 20px; font-size: 14px; }
        .decision-block .decision-title { font-weight: bold; text-transform: uppercase; font-size: 12px; color: #666; margin-bottom: 6px; }

        .pass { color: #15803d; font-weight: bold; }
        .fail { color: #c53030; font-weight: bold; }

        .nonconformity-note { border: 1px dashed #c53030; padding: 10px; margin-bottom: 20px; font-size: 13px; color: #c53030; }

        .signatures { margin-top: 40px; font-size: 13px; }
        .signatures .row { display: flex; justify-content: space-between; margin-bottom: 30px; }
        .signatures .line { display: inline-block; min-width: 220px; border-bottom: 1px solid #000; margin: 0 8px; }

        @media print {
            body { background: #fff; margin: 0; padding: 0; }
            .page { width: 210mm; min-height: 297mm; margin: 0; padding: 15mm; box-shadow: none; }
        }
        @page { size: A4 portrait; }
    </style>
</head>
<body>
    <div class="page">
        <div class="title-block">Акт входного контроля</div>
        <div class="subtitle-block">
            № АВК-{{ str_pad($line->id, 6, '0', STR_PAD_LEFT) }}
            от {{ ($line->inspected_at ?? now())->format('d.m.Y') }}
            &nbsp;·&nbsp; составлен по ГОСТ 24297, с проверкой документа о качестве по ГОСТ 7566
        </div>

        {{-- Идентификация продукции и партии --}}
        <table class="header-table">
            <tr>
                <td style="width: 34%;">
                    <span class="label">Наименование продукции, марка</span>
                    {{ $material->name }} {{ $material->grade }}
                </td>
                <td style="width: 33%;">
                    <span class="label">Обозначение НТД (ГОСТ/ТУ на материал)</span>
                    {{ $material->steelGrade?->gost ?? '—' }}
                </td>
                <td style="width: 33%;">
                    <span class="label">Код 1С / чертёжный номер (если применимо)</span>
                    {{ $material->code_1c ?? '—' }}
                </td>
            </tr>
            <tr>
                <td>
                    <span class="label">Заказчик / поставщик</span>
                    {{ $receipt->counterparty->name }}
                </td>
                <td>
                    <span class="label">№ плавки</span>
                    {{ $line->melt_number ?? '—' }}
                </td>
                <td>
                    <span class="label">№ партии поставщика</span>
                    {{ $line->supplier_lot_number ?? '—' }}
                </td>
            </tr>
            <tr>
                <td>
                    <span class="label">Дата поступления</span>
                    {{ $receipt->receipt_date->format('d.m.Y') }}
                </td>
                <td>
                    <span class="label">№ сопроводительного документа</span>
                    {{ $receipt->document_number ?? '—' }}
                </td>
                <td>
                    <span class="label">Дата документа</span>
                    {{ $receipt->document_date?->format('d.m.Y') ?? '—' }}
                </td>
            </tr>
            <tr>
                <td>
                    <span class="label">Размер</span>
                    @if($material->diameter)
                        Ø{{ $material->diameter }} мм
                        @if($material->wall_thickness) , стенка {{ $material->wall_thickness }} мм @endif
                    @elseif($material->thickness)
                        Толщина {{ $material->thickness }} мм, ширина {{ $material->width ?? '—' }} мм
                    @else
                        —
                    @endif
                    @if($line->measured_length_m) , длина {{ $line->measured_length_m }} м @endif
                </td>
                <td>
                    <span class="label">Масса по документу</span>
                    {{ number_format($line->declared_weight_kg, 2, ',', ' ') }} кг
                </td>
                <td>
                    <span class="label">Масса фактическая (взвешено)</span>
                    @if($line->actual_weight_kg)
                        {{ number_format($line->actual_weight_kg, 2, ',', ' ') }} кг
                        @if($line->weight_discrepancy_percent !== null)
                            ({{ $line->weight_discrepancy_percent > 0 ? '+' : '' }}{{ $line->weight_discrepancy_percent }}%)
                        @endif
                    @else
                        не взвешивалось
                    @endif
                </td>
            </tr>
            <tr>
                <td colspan="3">
                    <span class="label">№ сертификата / документа о качестве</span>
                    {{ $line->certificate_number ?? '— не указан —' }}
                    @if($line->certificate_date) от {{ $line->certificate_date->format('d.m.Y') }} @endif
                    — {{ $line->certificate_attached ? 'приложен к поступлению' : 'НЕ приложен' }}
                </td>
            </tr>
        </table>

        {{-- Результаты по пунктам проверки --}}
        <table class="route-table">
            <thead>
                <tr>
                    <th style="width: 5%;">№</th>
                    <th style="width: 35%;">Пункт проверки</th>
                    <th style="width: 15%;">Результат</th>
                    <th style="width: 20%;">Замеренное значение</th>
                    <th style="width: 25%;">Примечание</th>
                </tr>
            </thead>
            <tbody>
                @foreach(\App\Models\InspectionResult::CHECKPOINTS as $key => $label)
                    @php($result = $results->get($key))
                    <tr>
                        <td class="center">{{ $loop->iteration }}</td>
                        <td>{{ $label }}</td>
                        <td class="center">
                            @if(!$result)
                                —
                            @elseif($result->result === 'pass')
                                <span class="pass">✅ Соответствует</span>
                            @else
                                <span class="fail">❌ Несоответствие</span>
                            @endif
                        </td>
                        <td class="center">{{ $result->measured_value ?? '—' }}</td>
                        <td>{{ $result->comment ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        {{-- Итоговое решение --}}
        <div class="decision-block">
            <div class="decision-title">Итоговое решение по входному контролю</div>
            @if($line->decision)
                {{ \App\Models\ReceiptLine::DECISIONS[$line->decision] }}
                @if($line->decision_comment)
                    — {{ $line->decision_comment }}
                @endif
            @else
                Входной контроль ещё не завершён
            @endif
        </div>

        @if($nonconformity)
            <div class="nonconformity-note">
                По данной партии оформлен акт о несоответствии <strong>№ {{ $nonconformity->number }}</strong>
                от {{ $nonconformity->created_at->format('d.m.Y') }},
                статус: {{ \App\Models\Nonconformity::STATUSES[$nonconformity->status] }}
            </div>
        @endif

        {{-- Подписи --}}
        <div class="signatures">
            <div class="row">
                <div>Контролёр ОТК: <span class="line">{{ $line->inspectedBy->name ?? '' }}</span></div>
                <div>Дата: <span class="line" style="min-width: 120px;">{{ $line->inspected_at?->format('d.m.Y') ?? '' }}</span></div>
            </div>
            <div class="row">
                <div>Утверждаю (гл. технолог / директор): <span class="line"></span></div>
                <div>Дата: <span class="line" style="min-width: 120px;"></span></div>
            </div>
        </div>
    </div>
</body>
</html>
