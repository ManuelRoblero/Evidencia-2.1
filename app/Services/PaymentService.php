<?php

namespace App\Services;

use App\Models\CreditPackage;
use App\Models\CreditPackagePurchase;
use App\Models\Payment;
use App\Models\User;
use App\Models\UserCredit;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Reglas de negocio de los cobros en mostrador.
 */
class PaymentService
{
    /**
     * Registra un pago. Si es un paquete, acredita sus clases al cliente.
     *
     * @param  array{user_id:int, concept_type:string, credit_package_id?:?int, appointment_id?:?int, description?:?string, amount:numeric, method:string, reference?:?string}  $data
     */
    public function register(int $tenantId, array $data, ?int $receivedBy = null): Payment
    {
        if (! array_key_exists($data['method'] ?? '', Payment::METHODS)) {
            throw new InvalidArgumentException('Método de pago no válido.');
        }

        if ((float) ($data['amount'] ?? 0) <= 0) {
            throw new InvalidArgumentException('El monto debe ser mayor a cero.');
        }

        return DB::transaction(function () use ($tenantId, $data, $receivedBy): Payment {
            $credit = null;
            $creditsGranted = 0;

            if (($data['concept_type'] ?? null) === 'package') {
                $package = CreditPackage::findOrFail($data['credit_package_id'] ?? null);
                $user = User::findOrFail($data['user_id']);
                $creditsGranted = (int) $package->credits_amount;

                if ($package->is_one_time_purchase) {
                    CreditPackagePurchase::firstOrCreate([
                        'user_id' => $user->id,
                        'credit_package_id' => $package->id,
                    ]);
                }

                $credit = UserCredit::create([
                    'user_id' => $user->id,
                    'tenant_id' => $tenantId,
                    'balance' => $creditsGranted,
                    'expires_at' => now()->addDays((int) config('tuequilibrio.credit_validity_days', 30)),
                    'is_special' => false,
                ]);
            }

            return Payment::create([
                'tenant_id' => $tenantId,
                'user_id' => $data['user_id'],
                'concept_type' => $data['concept_type'],
                'credit_package_id' => $data['concept_type'] === 'package' ? ($data['credit_package_id'] ?? null) : null,
                'appointment_id' => $data['appointment_id'] ?? null,
                'user_credit_id' => $credit?->id,
                'credits_granted' => $creditsGranted,
                'description' => $data['description'] ?? null,
                'amount' => $data['amount'],
                'method' => $data['method'],
                'reference' => $data['reference'] ?? null,
                'status' => Payment::STATUS_PAID,
                'received_by' => $receivedBy,
                'paid_at' => now(),
            ]);
        });
    }

    /**
     * Cancela un pago (solo administrador). Retira las clases del paquete que aún no se hayan usado.
     *
     * @return int clases retiradas
     */
    public function cancel(Payment $payment, string $reason, ?int $cancelledBy = null): int
    {
        if ($payment->status === Payment::STATUS_CANCELLED) {
            throw new InvalidArgumentException('Este pago ya estaba cancelado.');
        }

        if (trim($reason) === '') {
            throw new InvalidArgumentException('El motivo de cancelación es obligatorio.');
        }

        return DB::transaction(function () use ($payment, $reason, $cancelledBy): int {
            $removed = 0;

            if ($payment->user_credit_id && $credit = UserCredit::lockForUpdate()->find($payment->user_credit_id)) {
                $removed = min((int) $credit->balance, (int) $payment->credits_granted);
                if ($removed > 0) {
                    $credit->decrement('balance', $removed);
                }
            }

            $payment->update([
                'status' => Payment::STATUS_CANCELLED,
                'cancelled_by' => $cancelledBy,
                'cancelled_at' => now(),
                'cancel_reason' => $reason,
            ]);

            return $removed;
        });
    }
}
