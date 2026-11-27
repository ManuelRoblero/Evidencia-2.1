<?php

namespace App\Filament\Support;

use App\Models\Appointment;
use App\Models\Payment;
use App\Services\PaymentService;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;

/**
 * Cuando una cita es "Cobrar al llegar", el check-in registra el cobro en la caja
 * para que aparezca en el corte del día.
 */
final class CheckInPayment
{
    /** @return array<int, \Filament\Schemas\Components\Component|\Filament\Forms\Components\Field> */
    public static function fields(bool $requiresPayment): array
    {
        return [
            TextInput::make('payment_amount')
                ->label('Monto cobrado')
                ->prefix('$')
                ->numeric()
                ->minValue(0.01)
                ->visible($requiresPayment)
                ->required($requiresPayment),
            ToggleButtons::make('payment_method')
                ->label('Método de pago')
                ->options(Payment::METHODS)
                ->default('cash')
                ->inline()
                ->visible($requiresPayment)
                ->required($requiresPayment),
        ];
    }

    public static function record(Appointment $appointment, array $data): void
    {
        if ($appointment->check_in_status !== 'cobrar_al_llegar' || empty($data['payment_amount'])) {
            return;
        }

        if (($data['new_check_in_status'] ?? null) !== 'cliente_llego') {
            return;
        }

        if (Payment::where('appointment_id', $appointment->id)->paid()->exists()) {
            return;
        }

        app(PaymentService::class)->register($appointment->tenant_id, [
            'user_id' => $appointment->user_id,
            'concept_type' => 'single_class',
            'appointment_id' => $appointment->id,
            'description' => 'Cobro al llegar ('.$appointment->date->format('d/m').' '.substr((string) $appointment->time_slot, 0, 5).')',
            'amount' => $data['payment_amount'],
            'method' => $data['payment_method'] ?? 'cash',
        ], auth()->id());
    }
}
