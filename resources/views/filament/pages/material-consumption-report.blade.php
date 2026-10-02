<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::section>
            {{ $this->form }}
        </x-filament::section>

        <x-filament::section>
            @php($report = $this->getReport())

            @if ($report->isEmpty())
                <p class="text-sm text-gray-500">
                    За выбранный период нет ни одного списания материала в производство
                    (заготовительные операции ещё не выполнялись, либо период пуст).
                </p>
            @else
                <div class="mb-4">
                    <p class="text-sm text-gray-500 mb-2">Итого по материалам за период:</p>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($this->getTotalQuantityByMaterial() as $materialName => $total)
                            <span class="px-3 py-1 rounded-full bg-gray-100 dark:bg-gray-800 text-sm">
                                {{ $materialName }}: <strong>{{ number_format($total, 2, ',', ' ') }}</strong>
                            </span>
                        @endforeach
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left border-b border-gray-200 dark:border-gray-700">
                                <th class="py-2 pr-4 font-medium">Дата</th>
                                <th class="py-2 pr-4 font-medium">№ заказа</th>
                                <th class="py-2 pr-4 font-medium">Деталь</th>
                                <th class="py-2 pr-4 font-medium">Материал</th>
                                <th class="py-2 pr-4 font-medium">Партия / плавка</th>
                                <th class="py-2 pr-4 font-medium text-right">Количество</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($report as $row)
                                <tr class="border-b border-gray-100 dark:border-gray-800">
                                    <td class="py-2 pr-4 text-gray-500">{{ $row->created_at->format('d.m.Y H:i') }}</td>
                                    <td class="py-2 pr-4">№{{ $row->order->order_number ?? '—' }}</td>
                                    <td class="py-2 pr-4">{{ $row->product->name ?? '—' }}</td>
                                    <td class="py-2 pr-4">{{ $row->material->name ?? '—' }} {{ $row->material->grade ?? '' }}</td>
                                    <td class="py-2 pr-4 text-gray-500">
                                        {{ $row->lot->lot_number ?? 'без партии' }}
                                        @if($row->lot?->melt_number) (плавка {{ $row->lot->melt_number }}) @endif
                                    </td>
                                    <td class="py-2 pr-4 text-right font-semibold">{{ number_format($row->quantity, 3, ',', ' ') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
