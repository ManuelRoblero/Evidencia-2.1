<?php

namespace App\Filament\Pages;

use App\Services\ReportService;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use UnitEnum;

class ReportsPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static string|UnitEnum|null $navigationGroup = 'Reportes';

    protected static ?string $navigationLabel = 'Ingresos y ocupación';

    protected static ?string $title = 'Reportes';

    protected static ?string $slug = 'reportes';

    protected string $view = 'filament.pages.reports-page';

    public string $from = '';

    public string $to = '';

    // Solo el administrador ve ingresos.
    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }

    public function mount(): void
    {
        $this->from = today()->startOfMonth()->toDateString();
        $this->to = today()->toDateString();
    }

    public function setRange(string $range): void
    {
        [$from, $to] = match ($range) {
            'week' => [today()->startOfWeek(), today()],
            'last_month' => [today()->subMonthNoOverflow()->startOfMonth(), today()->subMonthNoOverflow()->endOfMonth()],
            'year' => [today()->startOfYear(), today()],
            default => [today()->startOfMonth(), today()],
        };

        $this->from = $from->toDateString();
        $this->to = $to->toDateString();
    }

    protected function getViewData(): array
    {
        $tenant = Filament::getTenant();
        $from = rescue(fn () => Carbon::parse($this->from), today()->startOfMonth(), false);
        $to = rescue(fn () => Carbon::parse($this->to), today(), false);

        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }

        $reports = app(ReportService::class);

        return [
            'income' => $reports->income($tenant->id, $from, $to),
            'occupancy' => $reports->occupancy($tenant->id, $from, $to, (int) ($tenant->capacity_per_slot ?? 5)),
            'clients' => $reports->clients($tenant->id, $from, $to),
            'capacity' => (int) ($tenant->capacity_per_slot ?? 5),
        ];
    }
}
