<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::section>
            {{ $this->form }}
        </x-filament::section>

        <x-filament::section>
            @php($report = $this->getReport())

            @if (empty($report))
                <p class="text-sm text-gray-500">
                    За выбранный период ни один сотрудник ещё не завершил ни одной технологической
                    операции с указанным исполнителем.
                </p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left border-b border-gray-200 dark:border-gray-700">
                                <th class="py-2 pr-4 font-medium">Сотрудник</th>
                                <th class="py-2 pr-4 font-medium text-right">Выполнено операций</th>
                                <th class="py-2 pr-4 font-medium text-right">Плановое время</th>
                                <th class="py-2 pr-4 font-medium text-right">Фактическое время</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($report as $row)
                                <tr class="border-b border-gray-100 dark:border-gray-800">
                                    <td class="py-2 pr-4 font-medium">{{ $row['name'] }}</td>
                                    <td class="py-2 pr-4 text-right">{{ $row['tasks_count'] }}</td>
                                    <td class="py-2 pr-4 text-right text-gray-500">
                                        {{ $this->toHours($row['planned_minutes']) }}
                                    </td>
                                    <td class="py-2 pr-4 text-right font-semibold">
                                        {{ $this->toHours($row['fact_minutes']) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <p class="text-xs text-gray-400 mt-4">
                    «Плановое время» — сумма нормативной трудоёмкости операций (Тшт/Тпз) по всем
                    завершённым этапам этого сотрудника за период. «Фактическое время» — реальное
                    время между нажатием «В работу» и «Выполнить». Список отсортирован по убыванию
                    фактического времени.
                </p>
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
