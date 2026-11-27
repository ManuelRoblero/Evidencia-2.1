<?php

namespace App\Filament\Resources\Payments;

use App\Filament\Resources\Payments\Pages\CreatePayment;
use App\Filament\Resources\Payments\Pages\ListPayments;
use App\Models\CreditPackage;
use App\Models\Payment;
use App\Models\User;
use App\Services\PaymentService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static string|UnitEnum|null $navigationGroup = 'Caja';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'Pago';

    protected static ?string $pluralModelLabel = 'Pagos';

    public static function canViewAny(): bool
    {
        return auth()->user()->hasRole(['admin', 'empleado']);
    }

    public static function canCreate(): bool
    {
        return auth()->user()->hasRole(['admin', 'empleado']);
    }

    // Los pagos no se editan ni se borran: se cancelan con motivo.
    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Registrar cobro')->schema([
                Select::make('user_id')
                    ->label('Cliente')
                    ->options(fn () => User::role('cliente')->orderBy('name')->get()
                        ->mapWithKeys(fn (User $u) => [$u->id => trim($u->name.' '.$u->last_name).($u->phone ? ' · '.$u->phone : '')]))
                    ->searchable()
                    ->required(),

                ToggleButtons::make('concept_type')
                    ->label('Concepto')
                    ->options(Payment::CONCEPTS)
                    ->default('package')
                    ->inline()
                    ->live()
                    ->required(),

                Select::make('credit_package_id')
                    ->label('Paquete')
                    ->options(fn () => CreditPackage::orderBy('price')->get()
                        ->mapWithKeys(fn (CreditPackage $p) => [$p->id => $p->name.' ('.$p->credits_amount.' clases) – $'.number_format((float) $p->price, 2)]))
                    ->visible(fn (Get $get) => $get('concept_type') === 'package')
                    ->required(fn (Get $get) => $get('concept_type') === 'package')
                    ->live()
                    ->afterStateUpdated(function ($state, Set $set): void {
                        if ($package = CreditPackage::find($state)) {
                            $set('amount', $package->price);
                        }
                    })
                    ->helperText('Al guardar, las clases del paquete se acreditan al cliente.'),

                TextInput::make('description')
                    ->label('Detalle')
                    ->placeholder('Ej. clase de prueba, cobro pendiente...')
                    ->visible(fn (Get $get) => $get('concept_type') !== 'package')
                    ->maxLength(150),

                TextInput::make('amount')
                    ->label('Monto')
                    ->prefix('$')
                    ->numeric()
                    ->minValue(0.01)
                    ->required(),

                ToggleButtons::make('method')
                    ->label('Método de pago')
                    ->options(Payment::METHODS)
                    ->icons(['cash' => 'heroicon-o-banknotes', 'transfer' => 'heroicon-o-arrows-right-left', 'card' => 'heroicon-o-credit-card'])
                    ->default('cash')
                    ->inline()
                    ->live()
                    ->required(),

                TextInput::make('reference')
                    ->label('Referencia / folio')
                    ->placeholder('Folio de la terminal o de la transferencia')
                    ->visible(fn (Get $get) => in_array($get('method'), ['transfer', 'card']))
                    ->maxLength(80),
            ])->columns(2)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('paid_at')->label('Fecha')->dateTime('d M Y, H:i')->sortable(),
                TextColumn::make('user.name')->label('Cliente')
                    ->formatStateUsing(fn ($state, Payment $record) => trim($record->user?->name.' '.$record->user?->last_name))
                    ->searchable(['name', 'last_name']),
                TextColumn::make('concept_type')->label('Concepto')
                    ->formatStateUsing(fn ($state, Payment $record) => $record->conceptLabel())
                    ->wrap(),
                TextColumn::make('method')->label('Método')->badge()
                    ->formatStateUsing(fn (string $state) => Payment::METHODS[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        'cash' => 'success', 'transfer' => 'info', 'card' => 'warning', default => 'gray',
                    }),
                TextColumn::make('amount')->label('Monto')->money('MXN')->sortable()
                    ->extraAttributes(fn (Payment $record) => $record->status === Payment::STATUS_CANCELLED ? ['class' => 'line-through opacity-60'] : []),
                TextColumn::make('receiver.name')->label('Cobró'),
                TextColumn::make('status')->label('Estado')->badge()
                    ->formatStateUsing(fn (string $state) => $state === Payment::STATUS_PAID ? 'Pagado' : 'Cancelado')
                    ->color(fn (string $state) => $state === Payment::STATUS_PAID ? 'success' : 'danger')
                    ->tooltip(fn (Payment $record) => $record->cancel_reason ? 'Motivo: '.$record->cancel_reason.' ('.$record->canceller?->name.')' : null),
            ])
            ->defaultSort('paid_at', 'desc')
            ->filters([
                Filter::make('fecha')
                    ->schema([
                        DatePicker::make('desde')->label('Desde'),
                        DatePicker::make('hasta')->label('Hasta'),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['desde'] ?? null, fn ($q, $d) => $q->whereDate('paid_at', '>=', $d))
                        ->when($data['hasta'] ?? null, fn ($q, $d) => $q->whereDate('paid_at', '<=', $d))),
                SelectFilter::make('method')->label('Método')->options(Payment::METHODS),
                SelectFilter::make('status')->label('Estado')->options([Payment::STATUS_PAID => 'Pagado', Payment::STATUS_CANCELLED => 'Cancelado']),
            ])
            ->recordActions([
                Action::make('cancelar')
                    ->label('Cancelar')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Payment $record) => $record->status === Payment::STATUS_PAID && auth()->user()->hasRole('admin'))
                    ->requiresConfirmation()
                    ->modalHeading('Cancelar pago')
                    ->modalDescription('El pago queda registrado como cancelado. Si fue un paquete, se retiran las clases que el cliente aún no ha usado.')
                    ->schema([
                        Textarea::make('reason')->label('Motivo de la cancelación')->required()->maxLength(255),
                    ])
                    ->action(function (Payment $record, array $data): void {
                        $removed = app(PaymentService::class)->cancel($record, $data['reason'], auth()->id());

                        Notification::make()
                            ->title('Pago cancelado')
                            ->body($removed > 0 ? "Se retiraron {$removed} clases del saldo del cliente." : null)
                            ->success()
                            ->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPayments::route('/'),
            'create' => CreatePayment::route('/create'),
        ];
    }
}
