<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pago registrado en mostrador (efectivo, transferencia o tarjeta).
 * Nunca se borra: si hay un error se cancela con motivo (queda en bitácora).
 */
class Payment extends Model
{
    public const STATUS_PAID = 'paid';

    public const STATUS_CANCELLED = 'cancelled';

    public const METHODS = [
        'cash' => 'Efectivo',
        'transfer' => 'Transferencia',
        'card' => 'Tarjeta',
    ];

    public const CONCEPTS = [
        'package' => 'Paquete de clases',
        'single_class' => 'Clase suelta',
        'other' => 'Otro',
    ];

    protected $fillable = [
        'tenant_id', 'user_id', 'concept_type', 'credit_package_id', 'appointment_id',
        'user_credit_id', 'credits_granted', 'description', 'amount', 'method', 'reference',
        'status', 'received_by', 'paid_at', 'cancelled_by', 'cancelled_at', 'cancel_reason',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'credits_granted' => 'integer',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(CreditPackage::class, 'credit_package_id');
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function userCredit(): BelongsTo
    {
        return $this->belongsTo(UserCredit::class);
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function scopePaid(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PAID);
    }

    public function conceptLabel(): string
    {
        if ($this->concept_type === 'package' && $this->package) {
            return 'Paquete: '.$this->package->name;
        }

        $label = self::CONCEPTS[$this->concept_type] ?? $this->concept_type;

        return $this->description ? $label.' – '.$this->description : $label;
    }
}
