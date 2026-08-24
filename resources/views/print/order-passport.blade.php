<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Паспорт заказа №{{ $order->order_number }}</title>
    <style>
        /* Базовые стили для экрана */
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; color: #333; margin: 0; padding: 20px; background-color: #f4f4f4; }
        .page { background: #fff; padding: 30px; margin: 0 auto 20px auto; width: 210mm; min-height: 297mm; box-shadow: 0 0 10px rgba(0,0,0,0.1); box-sizing: border-box; }
        .drawing-container { display: flex; justify-content: center; align-items: center; width: 100%; height: 260mm; border: 1px dashed #ccc; overflow: hidden; }
        .drawing-container img { max-width: 100%; max-height: 100%; object-fit: contain; }
        .pdf-warning { font-weight: bold; color: #c53030; text-align: center; border: 2px solid #feb2b2; padding: 20px; background: #ffffff; }

        /* Стили паспорта и техпроцесса */
        .header-table { width: 100%; border-collapse: collapse; margin-bottom: 25px; }
        .header-table td { border: 2px solid #000; padding: 10px; font-size: 14px; vertical-align: top; }
        .title-block { font-size: 22px; font-weight: bold; text-align: center; text-transform: uppercase; margin-bottom: 20px; letter-spacing: 1px; }

        .route-table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        .route-table th { border: 2px solid #000; background-color: #f2f2f2; padding: 8px; font-size: 12px; text-transform: uppercase; }
        .route-table td { border: 1px solid #000; padding: 10px; font-size: 13px; }
        .center { text-align: center; }

        /* Стили строго для принтера (А4) */
        @media print {
            body { background: #fff; margin: 0; padding: 0; }
            .page { width: 210mm; height: 297mm; margin: 0; padding: 15mm; page-break-after: always; box-shadow: none; }
            .page:last-child { page-break-after: avoid; }
            .drawing-container { border: none; }
        }
    </style>
</head>
<body>

@foreach($passportItems as $item)
    {{-- КРУПНЫЕ ЛИСТЫ 1 И 2: Вывод чистых чертежей во весь экран --}}
    @if(!empty($item['product']->drawing_files) && is_array($item['product']->drawing_files))
        @foreach($item['product']->drawing_files as $file)
            <div class="page">
                <div class="title-block" style="font-size: 14px; color: #777; text-align: left; margin-bottom: 10px;">
                    Item: {{ $item['item_number'] }} | Чертеж изделия
                </div>
                <div class="drawing-container">
                    @if(Str::endsWith(Str::lower($file), '.pdf'))
                        <div class="pdf-warning">
                            <p style="font-size: 24px;">📄 ЧЕРТЕЖ В ФОРМАТЕ PDF</p>
                            <p>Файл: {{ basename($file) }}</p>
                            <p style="font-size: 14px; font-weight: normal; color: #555;">Принтер распечатает этот лист. Если браузер блокирует встроенный PDF, нажмите кнопку «Открыть оригинал» в карточке детали.</p>
                            <iframe src="{{ asset('storage/' . $file) }}" width="100%" height="700px" style="border: none;"></iframe>
                        </div>
                    @else
                        <img src="{{ asset('storage/' . $file) }}" alt="Чертеж детали">
                    @endif
                </div>
            </div>
        @endforeach
    @else
        {{-- Если чертеж вообще не был загружен технологом --}}
        <div class="page">
            <div class="drawing-container" style="background-color: #fafafa;">
                <div style="text-align: center; color: #999;">
                    <p style="font-size: 48px; margin: 0;">🖼️</p>
                    <p style="font-size: 16px;">Конструкторский чертеж не прикреплен к детали</p>
                    <p style="font-size: 12px; color: #bbb;">(Артикул: {{ $item['product']->sku }})</p>
                </div>
            </div>
        </div>
    @endif

    {{-- СЛЕДУЮЩИЙ ЛИСТ: Сопроводительный паспорт и Технологический маршрут --}}
    <div class="page">
        <div class="title-block">Производственный паспорт детали</div>

        {{-- Сетка параметров шапки --}}
        <table class="header-table">
            <tr>
                <td style="width: 35%;"><strong>Заказ на производство:</strong><br><span style="font-size: 18px; font-weight: bold;">№ {{ $order->order_number }}</span></td>
                <td style="width: 30%;"><strong>Уникальный ID (Item):</strong><br><span style="font-size: 18px; font-weight: bold; color: #0284c7;">#{{ $item['item_number'] }}</span></td>
                <td style="width: 35%;"><strong>Срок сдачи (Дедлайн):</strong><br><span style="font-size: 16px; font-weight: bold;">{{ $order->deadline ? $order->deadline->format('d.m.Y') : '-' }}</span></td>
            </tr>
            <tr>
                <td><strong>Наименование детали:</strong><br>{{ $item['product']->name }}</td>
                <td><strong>Чертеж / Артикул:</strong><br><span style="font-family: mono; font-weight: bold;">{{ $item['product']->sku }}</span></td>
                <td><strong>Объем партии в цех:</strong><br><span style="font-size: 18px; font-weight: bold;">{{ $item['quantity'] }} шт.</span></td>
            </tr>
            <tr>
                <td colspan="3">
                    <strong>Заготовка со склада (Нормы расхода сырья по BOM):</strong><br>
                    @if($item['materials']->count() > 0)
                        @foreach($item['materials'] as $matRecord)
                            @php $mat = \App\Models\Material::find($matRecord->material_id); @endphp
                            • {{ $matRecord->material_type }} {{ $matRecord->material_grade }}
                            @if($mat && $mat->diameter) (Ø {{ $mat->diameter }} мм) @endif
                            @if($mat && $mat->thickness) (Толщина: {{ $mat->thickness }} мм) @endif
                            — длина заготовки: <strong>{{ $matRecord->detail_length ?? '-' }} мм</strong>
                            (Расход на партию: <strong>{{ round($matRecord->consumption_rate * $item['quantity'], 4) }}</strong>)
                            <br>
                        @endforeach
                    @else
                        <span style="color: #c53030; font-weight: bold;">⚠️ Внимание: нормы расхода материала (BOM) не настроены технологом!</span>
                    @endif
                </td>
            </tr>
        </table>

        <div style="font-size: 14px; font-weight: bold; margin-top: 20px; text-transform: uppercase; letter-spacing: 0.5px;">
            Утвержденный технологический маршрут обработки:
        </div>

        {{-- Таблица техпроцесса --}}
        <table class="route-table">
            <thead>
                <tr>
                    <th style="width: 8%;">Опер.</th>
                    <th style="width: 25%;">Название операции</th>
                    <th style="width: 45%;">Технологическое описание и переходы</th>
                    <th style="width: 11%;">Тшт (мин)</th>
                    <th style="width: 11%;">Тпз (мин)</th>
                </tr>
            </thead>
            <tbody>
                @if($item['operations']->count() > 0)
                    @foreach($item['operations'] as $oper)
                        <tr>
                            <td class="center" style="font-weight: bold;">{{ $oper->operation_number }}</td>
                            <td><strong>{{ $oper->operation_name }}</strong></td>
                            <td style="color: #444; font-size: 12px; line-height: 1.4;">{{ $oper->description ?? 'Выполнить согласно КД' }}</td>
                            <td class="center">{{ $oper->piece_time > 0 ? number_format($oper->piece_time, 2) : '-' }}</td>
                            <td class="center">{{ $oper->prep_time > 0 ? number_format($oper->prep_time, 2) : '-' }}</td>
                        </tr>
                    @endforeach
                @else
                    <tr>
                        <td colspan="5" class="center" style="padding: 20px; color: #777; font-style: italic;">
                            Маршрутная карта не задана. Выполнить обработку по типовому технологическому регламенту.
                        </td>
                    </tr>
                @endif
            </tbody>
        </table>

        <div style="margin-top: 40px; font-size: 12px; display: flex; justify-content: space-between; color: #555;">
            <div>Паспорт сгенерирован автоматически ERP Argis</div>
            <div>Подпись мастера цеха: __________________</div>
        </div>
    </div>
@endforeach

<script>
    // Автоматически открываем системное окно печати принтера сразу после полной загрузки файлов
    window.onload = function() {
        window.print();
    };
</script>
</body>
</html>
