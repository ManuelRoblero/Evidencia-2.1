<x-filament-panels::page>
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-xl bg-primary-600 p-5 text-white shadow-sm">
            <p class="text-sm opacity-90">Clases disponibles</p>
            <p class="mt-1 text-3xl font-bold">{{ $credits }}</p>
            <p class="mt-1 text-xs opacity-80">
                {{ $expiresAt ? 'Vigentes hasta '.\Illuminate\Support\Carbon::parse($expiresAt)->format('d/m/Y') : 'Sin membresía activa' }}
            </p>
        </div>
        <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <p class="text-sm text-gray-500 dark:text-gray-400">Asistencias</p>
            <p class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">{{ $attended }}</p>
        </div>
        <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <p class="text-sm text-gray-500 dark:text-gray-400">Teléfono</p>
            <p class="mt-1 text-lg font-semibold text-gray-950 dark:text-white">{{ $record->phone }}</p>
        </div>
        <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <p class="text-sm text-gray-500 dark:text-gray-400">Correo</p>
            <p class="mt-1 truncate text-sm font-semibold text-gray-950 dark:text-white">{{ $record->email }}</p>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <x-filament::section heading="Últimas reservas">
            @forelse ($appointments as $a)
                <div class="flex items-center justify-between border-b border-gray-100 py-2 text-sm last:border-0 dark:border-white/5">
                    <div>
                        <p class="font-medium text-gray-950 dark:text-white">{{ $a->date->format('d/m/Y') }} · {{ date('h:i A', strtotime($a->time_slot)) }}</p>
                        <p class="text-xs text-gray-500">{{ $a->class_label ?? 'Clase' }}</p>
                    </div>
                    <span @class([
                        'rounded-full px-2 py-0.5 text-xs font-medium',
                        'bg-success-50 text-success-700 dark:bg-success-500/10 dark:text-success-400' => $a->check_in_status === 'cliente_llego',
                        'bg-danger-50 text-danger-700 dark:bg-danger-500/10 dark:text-danger-400' => in_array($a->check_in_status, ['cliente_no_llego']) || $a->status === 'cancelled',
                        'bg-gray-100 text-gray-700 dark:bg-white/10 dark:text-gray-300' => ! in_array($a->check_in_status, ['cliente_llego', 'cliente_no_llego']) && $a->status !== 'cancelled',
                    ])>
                        {{ $a->status === 'cancelled' ? 'Cancelada' : match ($a->check_in_status) {
                            'cliente_llego' => 'Asistió',
                            'cliente_no_llego' => 'No llegó',
                            'cobrar_al_llegar' => 'Cobrar al llegar',
                            default => 'Agendada',
                        } }}
                    </span>
                </div>
            @empty
                <p class="text-sm text-gray-500">Sin reservas.</p>
            @endforelse
        </x-filament::section>

        <x-filament::section heading="Pagos">
            @forelse ($payments as $p)
                <div @class(['flex items-center justify-between border-b border-gray-100 py-2 text-sm last:border-0 dark:border-white/5', 'opacity-50 line-through' => $p->status === \App\Models\Payment::STATUS_CANCELLED])>
                    <div>
                        <p class="font-medium text-gray-950 dark:text-white">{{ $p->conceptLabel() }}</p>
                        <p class="text-xs text-gray-500">{{ $p->paid_at->format('d/m/Y H:i') }} · {{ \App\Models\Payment::METHODS[$p->method] ?? $p->method }}</p>
                    </div>
                    <span class="font-semibold text-gray-950 dark:text-white">${{ number_format((float) $p->amount, 2) }}</span>
                </div>
            @empty
                <p class="text-sm text-gray-500">Sin pagos registrados.</p>
            @endforelse
        </x-filament::section>
    </div>
</x-filament-panels::page>
