<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::section>
            {{ $this->form }}
        </x-filament::section>

        <x-filament::section>
            @php($report = $this->getReport())

            @if (empty($report))
                <p class="text-sm text-gray-500">
                    Пока нет технологических задач с указанным типом операции — отчёт появится
                    после создания заказов на производство с заполненным техпроцессом.
                </p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left border-b border-gray-200 dark:border-gray-700">
                                <th class="py-2 pr-4 font-medium">Тип оборудования / операции</th>
                                <th class="py-2 pr-4 font-medium text-right">В очереди (план)</th>
                                <th class="py-2 pr-4 font-medium text-right">Из них просрочено</th>
                                <th class="py-2 pr-4 font-medium text-right">Факт за период</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($report as $equipmentType => $row)
                                <tr class="border-b border-gray-100 dark:border-gray-800">
                                    <td class="py-2 pr-4 font-medium">{{ $equipmentType }}</td>
                                    <td class="py-2 pr-4 text-right">
                                        {{ $this->toHours($row['backlog_minutes']) }}
                                    </td>
                                    <td class="py-2 pr-4 text-right {{ $row['overdue_minutes'] > 0 ? 'text-danger-600 font-semibold' : 'text-gray-400' }}">
                                        {{ $this->toHours($row['overdue_minutes']) }}
                                    </td>
                                    <td class="py-2 pr-4 text-right text-gray-600 dark:text-gray-300">
                                        {{ $this->toHours($row['fact_minutes']) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <p class="text-xs text-gray-400 mt-4">
                    «В очереди» — суммарная плановая трудоёмкость всех незавершённых задач этого типа
                    по всем заказам прямо сейчас. «Просрочено» — та же цифра, но только по заказам,
                    у которых уже прошёл дедлайн. «Факт» — реальное время между нажатием «В работу» и
                    «Выполнить» по задачам, завершённым в выбранном периоде.
                </p>
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
