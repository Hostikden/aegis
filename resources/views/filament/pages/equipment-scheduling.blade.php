<x-filament-panels::page>
    <div class="space-y-4">
        <p class="text-sm text-gray-500">
            Перетаскивайте строки за иконку слева, чтобы изменить очередь изготовления
            для выбранного типа оборудования. Порядок сохраняется автоматически.
        </p>

        {{ $this->table }}
    </div>
</x-filament-panels::page>
