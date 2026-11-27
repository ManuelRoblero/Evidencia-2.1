<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Clase fija del horario semanal: qué disciplina e instructor hay un día a una hora.
 * Al guardarla, la hora se agrega a los horarios de la sucursal (business_hours)
 * para que aparezca disponible al reservar.
 */
class ClassSchedule extends Model
{
    public const DAYS = [
        1 => 'Lunes',
        2 => 'Martes',
        3 => 'Miércoles',
        4 => 'Jueves',
        5 => 'Viernes',
        6 => 'Sábado',
        7 => 'Domingo',
    ];

    protected $fillable = ['tenant_id', 'discipline_id', 'instructor_id', 'day_of_week', 'start_time'];

    protected function casts(): array
    {
        return ['day_of_week' => 'integer'];
    }

    protected static function booted(): void
    {
        static::saved(function (ClassSchedule $schedule): void {
            $schedule->syncTenantBusinessHours();
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function discipline(): BelongsTo
    {
        return $this->belongsTo(Discipline::class);
    }

    public function instructor(): BelongsTo
    {
        return $this->belongsTo(Instructor::class);
    }

    public function getDayNameAttribute(): string
    {
        return self::DAYS[$this->day_of_week] ?? '';
    }

    public function label(): string
    {
        $text = $this->discipline?->name ?? 'Clase';

        if ($this->instructor) {
            $text .= ' · '.$this->instructor->name;
        }

        return $text;
    }

    /** Clase programada para una sucursal en una fecha y hora concretas. */
    public static function findFor(int $tenantId, Carbon|string $date, string $time): ?self
    {
        $date = $date instanceof Carbon ? $date : Carbon::parse($date);

        return static::query()
            ->with(['discipline', 'instructor'])
            ->where('tenant_id', $tenantId)
            ->where('day_of_week', $date->dayOfWeekIso)
            ->where('start_time', Carbon::parse($time)->format('H:i'))
            ->first();
    }

    /** Agrega la hora de esta clase a los horarios de la sucursal si aún no existe. */
    public function syncTenantBusinessHours(): void
    {
        $tenant = $this->tenant;
        if (! $tenant) {
            return;
        }

        $hours = collect($tenant->business_hours ?? []);
        $time = Carbon::parse($this->start_time)->format('H:i');
        $index = $hours->search(fn ($d) => (int) ($d['day'] ?? 0) === $this->day_of_week);

        if ($index === false) {
            $hours->push(['day' => $this->day_of_week, 'slots' => [$time]]);
        } else {
            $day = $hours[$index];
            $slots = collect($day['slots'] ?? []);
            if ($slots->contains($time)) {
                return;
            }
            $day['slots'] = $slots->push($time)->sort()->values()->all();
            $hours[$index] = $day;
        }

        $tenant->update(['business_hours' => $hours->sortBy('day')->values()->all()]);
    }
}
