<?php

namespace App\Filament\Resources\Clients;

use App\Filament\Resources\Clients\Pages\CreateClient;
use App\Filament\Resources\Clients\Pages\EditClient;
use App\Filament\Resources\Clients\Pages\ListClients;
use App\Filament\Resources\Clients\Pages\ViewClient;
use App\Models\User;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Clientes del estudio. Recepción puede darlos de alta y consultarlos;
 * la pantalla de Usuarios (con roles) queda solo para el administrador.
 */
class ClientResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $slug = 'clientes';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-identification';

    protected static string|UnitEnum|null $navigationGroup = 'Operación';

    protected static ?int $navigationSort = 0;

    protected static ?string $modelLabel = 'Cliente';

    protected static ?string $pluralModelLabel = 'Clientes';

    protected static bool $isScopedToTenant = false;

    public static function canViewAny(): bool
    {
        return auth()->user()->hasRole(['admin', 'empleado']);
    }

    public static function canCreate(): bool
    {
        return auth()->user()->hasRole(['admin', 'empleado']);
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()->hasRole(['admin', 'empleado']);
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->role('cliente');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Datos del cliente')->schema([
                TextInput::make('name')->label('Nombre(s)')->required()->maxLength(255),
                TextInput::make('last_name')->label('Apellidos')->required()->maxLength(255),
                TextInput::make('phone')->label('Teléfono')->tel()->required()->maxLength(10),
                TextInput::make('email')->label('Correo')->email()->required()->maxLength(255)
                    ->unique(table: 'users', column: 'email', ignoreRecord: true),
            ])->columns(2)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        $tenantId = fn () => Filament::getTenant()?->id;

        return $table
            ->columns([
                TextColumn::make('name')->label('Cliente')
                    ->formatStateUsing(fn ($state, User $record) => trim($record->name.' '.$record->last_name))
                    ->searchable(['name', 'last_name'])->sortable()->weight('bold'),
                TextColumn::make('phone')->label('Teléfono')->searchable(),
                TextColumn::make('clases')->label('Clases disponibles')
                    ->state(fn (User $record) => $record->activeCredits((int) $tenantId()))
                    ->badge()
                    ->color(fn (int $state) => $state > 2 ? 'success' : ($state > 0 ? 'warning' : 'gray')),
                TextColumn::make('vigencia')->label('Vigente hasta')
                    ->state(fn (User $record) => $record->credits()->where('tenant_id', $tenantId())->where('balance', '>', 0)->where('expires_at', '>', now())->max('expires_at'))
                    ->date('d M Y')
                    ->placeholder('Sin membresía activa'),
                TextColumn::make('created_at')->label('Alta')->date('d M Y')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->filters([
                Filter::make('con_membresia')->label('Con membresía activa')
                    ->query(fn (Builder $query) => $query->whereHas('credits', fn (Builder $credits) => $credits
                        ->where('tenant_id', $tenantId())->where('balance', '>', 0)->where('expires_at', '>', now()))),
            ])
            ->recordActions([ViewAction::make()->label('Historial'), EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListClients::route('/'),
            'create' => CreateClient::route('/create'),
            'view' => ViewClient::route('/{record}'),
            'edit' => EditClient::route('/{record}/edit'),
        ];
    }
}
