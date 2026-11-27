<?php

namespace App\Filament\Pages;

use App\Services\ReportService;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use UnitEnum;

class CashCutPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calculator';

    protected static string|UnitEnum|null $navigationGroup = 'Caja';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Corte de caja';

    protected static ?string $title = 'Corte de caja';

    protected static ?string $slug = 'corte-de-caja';

    protected string $view = 'filament.pages.cash-cut-page';

    public string $date = '';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole(['admin', 'empleado']) ?? false;
    }

    public function mount(): void
    {
        $this->date = today()->toDateString();
    }

    protected function getViewData(): array
    {
        $date = rescue(fn () => Carbon::parse($this->date), today(), false);

        return [
            'cut' => app(ReportService::class)->cashCut(Filament::getTenant()->id, $date),
            'dateLabel' => ucfirst($date->locale('es')->isoFormat('dddd D [de] MMMM [de] YYYY')),
        ];
    }
}
