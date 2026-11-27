<?php

namespace App\Filament\Resources\CreditPurchaseRequests;

use App\Filament\Resources\CreditPurchaseRequests\Pages\ListCreditPurchaseRequests;
use App\Filament\Resources\CreditPurchaseRequests\Tables\CreditPurchaseRequestsTable;
use App\Models\CreditPurchaseRequest;
use App\Models\User;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class CreditPurchaseRequestResource extends Resource
{
    // Fase futura: se activa en el .env (ver config/tuequilibrio.php).
    public static function canAccess(): bool
    {
        return \App\Support\Features::clientPortal() && parent::canAccess();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return \App\Support\Features::clientPortal() && parent::shouldRegisterNavigation();
    }

    protected static ?string $model = CreditPurchaseRequest::class;

    protected static bool $isScopedToTenant = false;

    public static function canViewAny(): bool
    {
        /** @var User|null $user */
        $user = Auth::user();

        return $user?->hasRole(['admin', 'empleado']) ?? false;
    }

    public static function getModelLabel(): string
    {
        return 'Solicitud de crédito';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Solicitudes de crédito';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Membresías';
    }

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-banknotes';
    }

    public static function table(Table $table): Table
    {
        return CreditPurchaseRequestsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCreditPurchaseRequests::route('/'),
        ];
    }
}
