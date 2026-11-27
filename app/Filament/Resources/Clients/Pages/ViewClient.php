<?php

namespace App\Filament\Resources\Clients\Pages;

use App\Filament\Resources\Clients\ClientResource;
use App\Models\Appointment;
use App\Models\ClassSchedule;
use App\Models\Payment;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Resources\Pages\ViewRecord;

class ViewClient extends ViewRecord
{
    protected static string $resource = ClientResource::class;

    protected string $view = 'filament.resources.clients.view-client';

    public function getTitle(): string
    {
        return trim($this->record->name.' '.$this->record->last_name);
    }

    protected function getHeaderActions(): array
    {
        return [EditAction::make()];
    }

    protected function getViewData(): array
    {
        $tenantId = Filament::getTenant()->id;
        $client = $this->record;

        $appointments = Appointment::where('user_id', $client->id)->where('tenant_id', $tenantId)
            ->orderByDesc('date')->orderByDesc('time_slot')->limit(15)->get()
            ->each(fn (Appointment $a) => $a->setAttribute('class_label', ClassSchedule::findFor($tenantId, $a->date, (string) $a->time_slot)?->label()));

        return [
            'credits' => $client->activeCredits($tenantId),
            'expiresAt' => $client->credits()->where('tenant_id', $tenantId)->where('balance', '>', 0)->where('expires_at', '>', now())->max('expires_at'),
            'appointments' => $appointments,
            'attended' => Appointment::where('user_id', $client->id)->where('tenant_id', $tenantId)->where('check_in_status', 'cliente_llego')->count(),
            'payments' => Payment::with('package')->where('user_id', $client->id)->where('tenant_id', $tenantId)->latest('paid_at')->limit(15)->get(),
        ];
    }
}
