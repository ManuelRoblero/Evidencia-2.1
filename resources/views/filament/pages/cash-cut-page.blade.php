<x-filament-panels::page>
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="text-sm text-gray-500 dark:text-gray-400">Día del corte</p>
            <p class="text-lg font-semibold text-gray-950 dark:text-white">{{ $dateLabel }}</p>
        </div>
        <div class="w-56">
            <x-filament::input.wrapper>
                <x-filament::input type="date" wire:model.live="date" />
            </x-filament::input.wrapper>
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-xl bg-primary-600 p-5 text-white shadow-sm">
            <p class="text-sm opacity-90">Total cobrado</p>
            <p class="mt-1 text-3xl font-bold">${{ number_format($cut['total'], 2) }}</p>
            <p class="mt-1 text-xs opacity-80">{{ $cut['count'] }} {{ $cut['count'] === 1 ? 'pago' : 'pagos' }}</p>
        </div>
        @foreach ($cut['by_method'] as $key => $method)
            <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ $method['label'] }}</p>
                <p class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">${{ number_format($method['total'], 2) }}</p>
                <p class="mt-1 text-xs text-gray-500">{{ $method['count'] }} {{ $method['count'] === 1 ? 'pago' : 'pagos' }}</p>
            </div>
        @endforeach
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <x-filament::section heading="Por usuario que cobró" class="lg:col-span-1">
            @forelse ($cut['by_user'] as $row)
                <div class="flex items-center justify-between border-b border-gray-100 py-2 last:border-0 dark:border-white/5">
                    <span class="text-sm text-gray-700 dark:text-gray-200">{{ $row['name'] }} <span class="text-gray-400">({{ $row['count'] }})</span></span>
                    <span class="font-semibold text-gray-950 dark:text-white">${{ number_format($row['total'], 2) }}</span>
                </div>
            @empty
                <p class="text-sm text-gray-500">Sin cobros este día.</p>
            @endforelse

            @if ($cut['cancelled_count'] > 0)
                <p class="mt-4 rounded-lg bg-danger-50 p-3 text-xs text-danger-700 dark:bg-danger-500/10 dark:text-danger-400">
                    {{ $cut['cancelled_count'] }} {{ $cut['cancelled_count'] === 1 ? 'pago cancelado' : 'pagos cancelados' }}
                    por ${{ number_format($cut['cancelled_total'], 2) }} (no suman al total).
                </p>
            @endif
        </x-filament::section>

        <x-filament::section heading="Detalle de cobros" class="lg:col-span-2">
            @if ($cut['payments']->isEmpty())
                <p class="text-sm text-gray-500">No hay cobros registrados en este día.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="text-xs uppercase text-gray-500">
                            <tr>
                                <th class="py-2 pr-3">Hora</th>
                                <th class="py-2 pr-3">Cliente</th>
                                <th class="py-2 pr-3">Concepto</th>
                                <th class="py-2 pr-3">Método</th>
                                <th class="py-2 pr-3">Cobró</th>
                                <th class="py-2 text-right">Monto</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                            @foreach ($cut['payments'] as $payment)
                                <tr @class(['opacity-50 line-through' => $payment->status === \App\Models\Payment::STATUS_CANCELLED])>
                                    <td class="py-2 pr-3 whitespace-nowrap">{{ $payment->paid_at->format('H:i') }}</td>
                                    <td class="py-2 pr-3">{{ trim($payment->user?->name.' '.$payment->user?->last_name) }}</td>
                                    <td class="py-2 pr-3">{{ $payment->conceptLabel() }}</td>
                                    <td class="py-2 pr-3">{{ \App\Models\Payment::METHODS[$payment->method] ?? $payment->method }}</td>
                                    <td class="py-2 pr-3">{{ $payment->receiver?->name }}</td>
                                    <td class="py-2 text-right font-medium">${{ number_format((float) $payment->amount, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
