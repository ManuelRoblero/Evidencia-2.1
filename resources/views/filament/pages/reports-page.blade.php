<x-filament-panels::page>
    <div class="flex flex-wrap items-end gap-3">
        <div class="w-48">
            <label class="mb-1 block text-sm text-gray-500 dark:text-gray-400">Desde</label>
            <x-filament::input.wrapper>
                <x-filament::input type="date" wire:model.live="from" />
            </x-filament::input.wrapper>
        </div>
        <div class="w-48">
            <label class="mb-1 block text-sm text-gray-500 dark:text-gray-400">Hasta</label>
            <x-filament::input.wrapper>
                <x-filament::input type="date" wire:model.live="to" />
            </x-filament::input.wrapper>
        </div>
        <div class="flex flex-wrap gap-2">
            <x-filament::button color="gray" size="sm" wire:click="setRange('week')">Esta semana</x-filament::button>
            <x-filament::button color="gray" size="sm" wire:click="setRange('month')">Este mes</x-filament::button>
            <x-filament::button color="gray" size="sm" wire:click="setRange('last_month')">Mes anterior</x-filament::button>
            <x-filament::button color="gray" size="sm" wire:click="setRange('year')">Este año</x-filament::button>
        </div>
    </div>

    {{-- Indicadores principales --}}
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-xl bg-primary-600 p-5 text-white shadow-sm">
            <p class="text-sm opacity-90">Ingresos del periodo</p>
            <p class="mt-1 text-3xl font-bold">${{ number_format($income['total'], 2) }}</p>
            <p class="mt-1 text-xs opacity-80">{{ $income['count'] }} pagos · promedio ${{ number_format($income['average'], 2) }}</p>
        </div>
        <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <p class="text-sm text-gray-500 dark:text-gray-400">Reservas</p>
            <p class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">{{ $occupancy['booked'] }}</p>
            <p class="mt-1 text-xs text-gray-500">{{ $occupancy['attended'] }} asistieron · {{ $occupancy['no_show'] }} no llegaron</p>
        </div>
        <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <p class="text-sm text-gray-500 dark:text-gray-400">Asistencia</p>
            <p class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">{{ $occupancy['attendance_rate'] }}%</p>
            <p class="mt-1 text-xs text-gray-500">de las reservas con pase de lista</p>
        </div>
        <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <p class="text-sm text-gray-500 dark:text-gray-400">Clientes</p>
            <p class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">{{ $clients['active'] }} activos</p>
            <p class="mt-1 text-xs text-gray-500">{{ $clients['new'] }} nuevos en el periodo</p>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <x-filament::section heading="Ingresos por método de pago">
            @php $max = max(1, collect($income['by_method'])->max('total')); @endphp
            @foreach ($income['by_method'] as $method)
                <div class="py-2">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-700 dark:text-gray-200">{{ $method['label'] }} <span class="text-gray-400">({{ $method['count'] }})</span></span>
                        <span class="font-semibold text-gray-950 dark:text-white">${{ number_format($method['total'], 2) }}</span>
                    </div>
                    <div class="mt-1 h-2 rounded-full bg-gray-100 dark:bg-white/10">
                        <div class="h-2 rounded-full bg-primary-500" style="width: {{ round($method['total'] / $max * 100) }}%"></div>
                    </div>
                </div>
            @endforeach
        </x-filament::section>

        <x-filament::section heading="Ingresos por concepto">
            @forelse ($income['by_concept'] as $row)
                <div class="flex items-center justify-between border-b border-gray-100 py-2 last:border-0 dark:border-white/5">
                    <span class="text-sm text-gray-700 dark:text-gray-200">{{ $row['label'] }} <span class="text-gray-400">({{ $row['count'] }})</span></span>
                    <span class="font-semibold text-gray-950 dark:text-white">${{ number_format($row['total'], 2) }}</span>
                </div>
            @empty
                <p class="text-sm text-gray-500">Sin ingresos en el periodo.</p>
            @endforelse
        </x-filament::section>
    </div>

    <x-filament::section heading="Ingresos por día">
        @if ($income['by_day']->isEmpty())
            <p class="text-sm text-gray-500">Sin ingresos en el periodo.</p>
        @else
            @php $maxDay = max(1, $income['by_day']->max('total')); @endphp
            <div class="flex h-48 items-end gap-1 overflow-x-auto">
                @foreach ($income['by_day'] as $day)
                    <div class="flex min-w-6 flex-1 flex-col items-center justify-end gap-1" title="{{ \Illuminate\Support\Carbon::parse($day['day'])->format('d/m') }}: ${{ number_format($day['total'], 2) }}">
                        <div class="w-full rounded-t bg-primary-500" style="height: {{ max(4, round($day['total'] / $maxDay * 160)) }}px"></div>
                        <span class="text-[10px] text-gray-500">{{ \Illuminate\Support\Carbon::parse($day['day'])->format('d/m') }}</span>
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>

    <div class="grid gap-6 lg:grid-cols-3">
        <x-filament::section heading="Ocupación por horario" description="Cupo por clase: {{ $capacity }} lugares. Ordenado de más lleno a menos lleno." class="lg:col-span-2">
            @if ($occupancy['by_slot']->isEmpty())
                <p class="text-sm text-gray-500">Sin reservas en el periodo.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="text-xs uppercase text-gray-500">
                            <tr>
                                <th class="py-2 pr-3">Día y hora</th>
                                <th class="py-2 pr-3">Clase</th>
                                <th class="py-2 pr-3 text-right">Reservas</th>
                                <th class="py-2 pr-3 text-right">Promedio</th>
                                <th class="py-2 w-40">Ocupación</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                            @foreach ($occupancy['by_slot'] as $slot)
                                <tr>
                                    <td class="py-2 pr-3 whitespace-nowrap">{{ $slot['day_name'] }} {{ date('h:i A', strtotime($slot['time'])) }}</td>
                                    <td class="py-2 pr-3">{{ $slot['class'] }}</td>
                                    <td class="py-2 pr-3 text-right">{{ $slot['booked'] }}</td>
                                    <td class="py-2 pr-3 text-right">{{ $slot['avg_per_session'] }}</td>
                                    <td class="py-2">
                                        <div class="flex items-center gap-2">
                                            <div class="h-2 flex-1 rounded-full bg-gray-100 dark:bg-white/10">
                                                <div @class([
                                                    'h-2 rounded-full',
                                                    'bg-success-500' => $slot['fill_rate'] >= 75,
                                                    'bg-warning-500' => $slot['fill_rate'] >= 40 && $slot['fill_rate'] < 75,
                                                    'bg-danger-500' => $slot['fill_rate'] < 40,
                                                ]) style="width: {{ $slot['fill_rate'] }}%"></div>
                                            </div>
                                            <span class="w-10 text-right text-xs font-semibold">{{ $slot['fill_rate'] }}%</span>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>

        <x-filament::section heading="Reservas por disciplina">
            @forelse ($occupancy['by_discipline'] as $row)
                <div class="flex items-center justify-between border-b border-gray-100 py-2 last:border-0 dark:border-white/5">
                    <span class="text-sm text-gray-700 dark:text-gray-200">{{ $row['name'] }}</span>
                    <span class="text-sm"><span class="font-semibold text-gray-950 dark:text-white">{{ $row['booked'] }}</span> <span class="text-gray-400">· {{ $row['attended'] }} asistieron</span></span>
                </div>
            @empty
                <p class="text-sm text-gray-500">Sin reservas en el periodo.</p>
            @endforelse
        </x-filament::section>
    </div>
</x-filament-panels::page>
