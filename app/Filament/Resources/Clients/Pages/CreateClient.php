<?php

namespace App\Filament\Resources\Clients\Pages;

use App\Filament\Resources\Clients\ClientResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CreateClient extends CreateRecord
{
    protected static string $resource = ClientResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // En la fase 1 el cliente no entra al sistema: contraseña aleatoria y sin correo de bienvenida.
        $data['password'] = Hash::make(Str::random(40));

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->record->assignRole('cliente');

        if ($tenant = Filament::getTenant()) {
            $this->record->tenants()->syncWithoutDetaching([$tenant->id]);
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
