<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\ClassSchedule;
use App\Models\Payment;
use App\Models\User;
use App\Models\UserCredit;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Cálculos del corte de caja y de los reportes. Todo se filtra por sucursal.
 */
class ReportService
{
    /**
     * Corte de caja de un día.
     *
     * @return array{total: float, count: int, cancelled_count: int, cancelled_total: float, by_method: array<string, array{label:string,total:float,count:int}>, by_user: Collection, payments: Collection}
     */
    public function cashCut(int $tenantId, Carbon $date): array
    {
        $all = Payment::query()
            ->with(['user', 'receiver', 'package'])
            ->where('tenant_id', $tenantId)
            ->whereDate('paid_at', $date->toDateString())
            ->orderBy('paid_at')
            ->get();

        $paid = $all->where('status', Payment::STATUS_PAID);
        $cancelled = $all->where('status', Payment::STATUS_CANCELLED);

        return [
            'total' => (float) $paid->sum('amount'),
            'count' => $paid->count(),
            'cancelled_count' => $cancelled->count(),
            'cancelled_total' => (float) $cancelled->sum('amount'),
            'by_method' => $this->byMethod($paid),
            'by_user' => $paid->groupBy('received_by')->map(fn (Collection $items) => [
                'name' => $items->first()->receiver?->name ?? 'Sin usuario',
                'total' => (float) $items->sum('amount'),
                'count' => $items->count(),
            ])->sortByDesc('total')->values(),
            'payments' => $all,
        ];
    }

    /**
     * Ingresos de un periodo.
     *
     * @return array{total: float, count: int, average: float, by_method: array, by_concept: Collection, by_day: Collection}
     */
    public function income(int $tenantId, Carbon $from, Carbon $to): array
    {
        $paid = Payment::query()
            ->with('package')
            ->where('tenant_id', $tenantId)
            ->paid()
            ->whereBetween('paid_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->get();

        $count = $paid->count();

        return [
            'total' => (float) $paid->sum('amount'),
            'count' => $count,
            'average' => $count > 0 ? round((float) $paid->sum('amount') / $count, 2) : 0.0,
            'by_method' => $this->byMethod($paid),
            'by_concept' => $paid->groupBy(fn (Payment $p) => $p->concept_type === 'package' && $p->package ? 'Paquete: '.$p->package->name : (Payment::CONCEPTS[$p->concept_type] ?? $p->concept_type))
                ->map(fn (Collection $items, string $label) => ['label' => $label, 'total' => (float) $items->sum('amount'), 'count' => $items->count()])
                ->sortByDesc('total')->values(),
            'by_day' => $paid->groupBy(fn (Payment $p) => $p->paid_at->toDateString())
                ->map(fn (Collection $items, string $day) => ['day' => $day, 'total' => (float) $items->sum('amount'), 'count' => $items->count()])
                ->sortBy('day')->values(),
        ];
    }

    /**
     * Ocupación de clases de un periodo (reservas que no se cancelaron).
     *
     * @return array{booked: int, attended: int, no_show: int, attendance_rate: float, by_slot: Collection, by_discipline: Collection}
     */
    public function occupancy(int $tenantId, Carbon $from, Carbon $to, int $capacity): array
    {
        $appointments = Appointment::query()
            ->where('tenant_id', $tenantId)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->where('status', '!=', 'cancelled')
            ->get();

        $schedules = ClassSchedule::with(['discipline', 'instructor'])->where('tenant_id', $tenantId)->get()
            ->keyBy(fn (ClassSchedule $s) => $s->day_of_week.'|'.$s->start_time);

        $attended = $appointments->where('check_in_status', 'cliente_llego')->count();
        $noShow = $appointments->where('check_in_status', 'cliente_no_llego')->count();

        $bySlot = $appointments
            ->groupBy(fn (Appointment $a) => $a->date->dayOfWeekIso.'|'.Carbon::parse($a->time_slot)->format('H:i'))
            ->map(function (Collection $items, string $key) use ($schedules, $capacity) {
                [$day, $time] = explode('|', $key);
                $sessions = $items->groupBy(fn (Appointment $a) => $a->date->toDateString())->count();
                $schedule = $schedules->get($key);

                return [
                    'day' => (int) $day,
                    'day_name' => ClassSchedule::DAYS[(int) $day] ?? '',
                    'time' => $time,
                    'class' => $schedule?->label() ?? '—',
                    'booked' => $items->count(),
                    'sessions' => $sessions,
                    'avg_per_session' => $sessions > 0 ? round($items->count() / $sessions, 1) : 0,
                    'fill_rate' => $sessions > 0 && $capacity > 0 ? min(100, round($items->count() / ($sessions * $capacity) * 100)) : 0,
                ];
            })
            ->sortByDesc('fill_rate')->values();

        $byDiscipline = $appointments
            ->groupBy(fn (Appointment $a) => $schedules->get($a->date->dayOfWeekIso.'|'.Carbon::parse($a->time_slot)->format('H:i'))?->discipline?->name ?? 'Sin disciplina asignada')
            ->map(fn (Collection $items, string $name) => ['name' => $name, 'booked' => $items->count(), 'attended' => $items->where('check_in_status', 'cliente_llego')->count()])
            ->sortByDesc('booked')->values();

        return [
            'booked' => $appointments->count(),
            'attended' => $attended,
            'no_show' => $noShow,
            'attendance_rate' => ($attended + $noShow) > 0 ? round($attended / ($attended + $noShow) * 100) : 0,
            'by_slot' => $bySlot,
            'by_discipline' => $byDiscipline,
        ];
    }

    /** @return array{active: int, new: int} */
    public function clients(int $tenantId, Carbon $from, Carbon $to): array
    {
        return [
            'active' => UserCredit::query()
                ->where('tenant_id', $tenantId)
                ->where('balance', '>', 0)
                ->where('expires_at', '>', now())
                ->distinct('user_id')->count('user_id'),
            'new' => User::role('cliente')
                ->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
                ->count(),
        ];
    }

    private function byMethod(Collection $paid): array
    {
        $result = [];
        foreach (Payment::METHODS as $key => $label) {
            $items = $paid->where('method', $key);
            $result[$key] = ['label' => $label, 'total' => (float) $items->sum('amount'), 'count' => $items->count()];
        }

        return $result;
    }
}
