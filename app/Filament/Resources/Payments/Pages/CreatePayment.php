<?php

namespace App\Filament\Resources\Payments\Pages;

use App\Filament\Resources\Payments\PaymentResource;
use App\Services\PaymentService;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreatePayment extends CreateRecord
{
    protected static string $resource = PaymentResource::class;

    protected static ?string $title = 'Registrar cobro';

    protected function handleRecordCreation(array $data): Model
    {
        return app(PaymentService::class)->register(Filament::getTenant()->id, $data, auth()->id());
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        $payment = $this->record;

        return $payment->credits_granted > 0
            ? "Pago registrado. Se acreditaron {$payment->credits_granted} clases al cliente."
            : 'Pago registrado.';
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
