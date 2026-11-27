<?php

namespace Tests\Feature;

use App\Filament\Pages\CashCutPage;
use App\Filament\Pages\ReportsPage;
use App\Filament\Resources\Payments\Pages\CreatePayment;
use App\Filament\Resources\Payments\Pages\ListPayments;
use Filament\Facades\Filament;
use Livewire\Livewire;
use App\Filament\Resources\Clients\Pages\CreateClient;
use App\Filament\Resources\Clients\Pages\ListClients;
use App\Filament\Resources\Users\UserResource;
use Illuminate\Support\Facades\Mail;
use App\Filament\Resources\Products\ProductResource;
use App\Filament\Support\CheckInPayment;
use App\Models\Appointment;
use App\Models\ClassSchedule;
use App\Models\CreditPackage;
use App\Models\Discipline;
use App\Models\Instructor;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\User;
use App\Services\PaymentService;
use App\Services\ReportService;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Tests\TestCase;

class TuEquilibrioTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $admin;

    private User $recepcion;

    private User $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);

        $this->tenant = Tenant::create(['name' => 'TuEquilibrio', 'slug' => 'tuequilibrio', 'capacity_per_slot' => 8]);
        $this->admin = $this->makeUser('admin');
        $this->recepcion = $this->makeUser('empleado');
        $this->client = $this->makeUser('cliente');
    }

    private function makeUser(string $role): User
    {
        $user = User::factory()->create(['last_name' => 'Prueba', 'phone' => '0000000000']);
        $user->assignRole($role);
        $user->tenants()->attach($this->tenant);

        return $user;
    }

    private function package(int $credits = 8, float $price = 900): CreditPackage
    {
        return CreditPackage::create(['name' => "Paquete {$credits}", 'credits_amount' => $credits, 'price' => $price]);
    }

    public function test_cobrar_un_paquete_acredita_sus_clases(): void
    {
        $package = $this->package(8, 900);

        $payment = app(PaymentService::class)->register($this->tenant->id, [
            'user_id' => $this->client->id,
            'concept_type' => 'package',
            'credit_package_id' => $package->id,
            'amount' => 900,
            'method' => 'cash',
        ], $this->recepcion->id);

        $this->assertSame(8, $payment->credits_granted);
        $this->assertSame(8, $this->client->activeCredits($this->tenant->id));
        $this->assertSame($this->recepcion->id, $payment->received_by);
        $this->assertSame(Payment::STATUS_PAID, $payment->status);
    }

    public function test_una_clase_suelta_no_acredita_clases(): void
    {
        $payment = app(PaymentService::class)->register($this->tenant->id, [
            'user_id' => $this->client->id,
            'concept_type' => 'single_class',
            'amount' => 150,
            'method' => 'card',
            'reference' => 'TPV-1',
        ]);

        $this->assertSame(0, $payment->credits_granted);
        $this->assertSame(0, $this->client->activeCredits($this->tenant->id));
    }

    public function test_no_acepta_montos_en_cero_ni_metodos_desconocidos(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(PaymentService::class)->register($this->tenant->id, [
            'user_id' => $this->client->id, 'concept_type' => 'other', 'amount' => 0, 'method' => 'cash',
        ]);
    }

    public function test_cancelar_pago_retira_solo_las_clases_no_usadas_y_guarda_bitacora(): void
    {
        $service = app(PaymentService::class);
        $payment = $service->register($this->tenant->id, [
            'user_id' => $this->client->id, 'concept_type' => 'package',
            'credit_package_id' => $this->package(8)->id, 'amount' => 900, 'method' => 'cash',
        ]);

        // El cliente ya usó 3 clases
        $payment->userCredit->decrement('balance', 3);

        $removed = $service->cancel($payment, 'Se cobró dos veces', $this->admin->id);

        $payment->refresh();
        $this->assertSame(5, $removed);
        $this->assertSame(0, $this->client->activeCredits($this->tenant->id));
        $this->assertSame(Payment::STATUS_CANCELLED, $payment->status);
        $this->assertSame($this->admin->id, $payment->cancelled_by);
        $this->assertSame('Se cobró dos veces', $payment->cancel_reason);
    }

    public function test_cancelar_requiere_motivo_y_no_se_puede_cancelar_dos_veces(): void
    {
        $service = app(PaymentService::class);
        $payment = $service->register($this->tenant->id, [
            'user_id' => $this->client->id, 'concept_type' => 'other', 'amount' => 100, 'method' => 'cash',
        ]);

        try {
            $service->cancel($payment, '   ');
            $this->fail('Debió pedir motivo');
        } catch (InvalidArgumentException) {
        }

        $service->cancel($payment, 'Error de captura');
        $this->expectException(InvalidArgumentException::class);
        $service->cancel($payment->refresh(), 'Otra vez');
    }

    public function test_corte_de_caja_suma_por_metodo_y_usuario_sin_contar_cancelados(): void
    {
        $service = app(PaymentService::class);
        $service->register($this->tenant->id, ['user_id' => $this->client->id, 'concept_type' => 'other', 'amount' => 100, 'method' => 'cash'], $this->recepcion->id);
        $service->register($this->tenant->id, ['user_id' => $this->client->id, 'concept_type' => 'other', 'amount' => 250, 'method' => 'card'], $this->recepcion->id);
        $service->register($this->tenant->id, ['user_id' => $this->client->id, 'concept_type' => 'other', 'amount' => 50, 'method' => 'transfer'], $this->admin->id);
        $cancelled = $service->register($this->tenant->id, ['user_id' => $this->client->id, 'concept_type' => 'other', 'amount' => 999, 'method' => 'cash'], $this->admin->id);
        $service->cancel($cancelled, 'Prueba', $this->admin->id);

        $cut = app(ReportService::class)->cashCut($this->tenant->id, today());

        $this->assertEquals(400.0, $cut['total']);
        $this->assertSame(3, $cut['count']);
        $this->assertEquals(100.0, $cut['by_method']['cash']['total']);
        $this->assertEquals(250.0, $cut['by_method']['card']['total']);
        $this->assertEquals(50.0, $cut['by_method']['transfer']['total']);
        $this->assertSame(1, $cut['cancelled_count']);
        $this->assertEquals(350.0, $cut['by_user']->firstWhere('name', $this->recepcion->name)['total']);
    }

    public function test_el_horario_semanal_agrega_la_hora_a_la_sucursal_y_se_ve_en_la_cita(): void
    {
        $yoga = Discipline::create(['tenant_id' => $this->tenant->id, 'name' => 'Yoga']);
        $ana = Instructor::create(['tenant_id' => $this->tenant->id, 'name' => 'Ana']);

        ClassSchedule::create(['tenant_id' => $this->tenant->id, 'discipline_id' => $yoga->id, 'instructor_id' => $ana->id, 'day_of_week' => 1, 'start_time' => '09:00']);
        ClassSchedule::create(['tenant_id' => $this->tenant->id, 'discipline_id' => $yoga->id, 'day_of_week' => 1, 'start_time' => '07:00']);

        $hours = collect($this->tenant->refresh()->business_hours);
        $this->assertSame(['07:00', '09:00'], $hours->firstWhere('day', 1)['slots']);

        $monday = Carbon::parse('next monday');
        $this->assertSame('Yoga · Ana', ClassSchedule::findFor($this->tenant->id, $monday, '09:00:00')?->label());
        $this->assertNull(ClassSchedule::findFor($this->tenant->id, $monday->copy()->addDay(), '09:00'));
    }

    public function test_check_in_de_cita_por_cobrar_registra_el_pago_en_caja(): void
    {
        $this->actingAs($this->recepcion);
        $appointment = Appointment::create([
            'tenant_id' => $this->tenant->id, 'user_id' => $this->client->id, 'date' => today(),
            'time_slot' => '09:00', 'status' => 'scheduled', 'check_in_status' => 'cobrar_al_llegar',
        ]);

        CheckInPayment::record($appointment, ['new_check_in_status' => 'cliente_llego', 'payment_amount' => 150, 'payment_method' => 'cash']);
        // Repetir no duplica el cobro
        CheckInPayment::record($appointment, ['new_check_in_status' => 'cliente_llego', 'payment_amount' => 150, 'payment_method' => 'cash']);

        $this->assertSame(1, Payment::where('appointment_id', $appointment->id)->count());
        $this->assertSame($this->recepcion->id, Payment::first()->received_by);
    }

    public function test_si_el_cliente_no_llega_no_se_registra_cobro(): void
    {
        $appointment = Appointment::create([
            'tenant_id' => $this->tenant->id, 'user_id' => $this->client->id, 'date' => today(),
            'time_slot' => '09:00', 'status' => 'scheduled', 'check_in_status' => 'cobrar_al_llegar',
        ]);

        CheckInPayment::record($appointment, ['new_check_in_status' => 'cliente_no_llego', 'payment_amount' => 150, 'payment_method' => 'cash']);

        $this->assertSame(0, Payment::count());
    }

    public function test_recepcion_ve_el_corte_pero_no_los_reportes_de_ingresos(): void
    {
        $this->actingAs($this->recepcion);
        $this->assertTrue(CashCutPage::canAccess());
        $this->assertFalse(ReportsPage::canAccess());

        $this->actingAs($this->admin);
        $this->assertTrue(ReportsPage::canAccess());
    }

    public function test_tienda_y_portal_de_clientes_apagados_por_defecto(): void
    {
        $this->get('/')->assertRedirect('/dashboard');
        $this->get('/login')->assertRedirect('/dashboard/login');
        $this->get('/tienda')->assertNotFound();
        $this->get('/paquetes-disponibles')->assertNotFound();
        $this->get('/clientes/login')->assertNotFound();

        $this->actingAs($this->admin);
        $this->assertFalse(ProductResource::canAccess());
    }

    public function test_la_tienda_se_reactiva_desde_la_configuracion(): void
    {
        config(['tuequilibrio.features.store' => true]);
        $this->actingAs($this->admin);

        $this->assertTrue(ProductResource::canAccess());
    }

    public function test_el_panel_del_personal_carga_para_recepcion(): void
    {
        $this->actingAs($this->recepcion)
            ->get('/dashboard/tuequilibrio/corte-de-caja')
            ->assertOk()
            ->assertSee('Corte de caja');

        $this->actingAs($this->recepcion)
            ->get('/dashboard/tuequilibrio/reportes')
            ->assertForbidden();
    }

    private function enterPanelAs(User $user): void
    {
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('dashboard'));
        Filament::setTenant($this->tenant);
        Filament::bootCurrentPanel();
    }

    public function test_recepcion_registra_un_cobro_desde_el_formulario(): void
    {
        $package = $this->package(12, 1200);
        $this->enterPanelAs($this->recepcion);

        Livewire::test(CreatePayment::class)
            ->fillForm([
                'user_id' => $this->client->id,
                'concept_type' => 'package',
                'credit_package_id' => $package->id,
            ])
            ->assertSchemaStateSet(['amount' => '1200.00'])
            ->fillForm(['method' => 'card', 'reference' => 'TPV-7788'])
            ->call('create')
            ->assertHasNoFormErrors();

        $payment = Payment::sole();
        $this->assertSame('card', $payment->method);
        $this->assertSame('TPV-7788', $payment->reference);
        $this->assertSame($this->recepcion->id, $payment->received_by);
        $this->assertSame(12, $this->client->activeCredits($this->tenant->id));
    }

    public function test_el_formulario_valida_campos_obligatorios(): void
    {
        $this->enterPanelAs($this->recepcion);

        Livewire::test(CreatePayment::class)
            ->fillForm(['user_id' => null, 'concept_type' => 'other', 'amount' => null])
            ->call('create')
            ->assertHasFormErrors(['user_id' => 'required', 'amount' => 'required']);

        $this->assertSame(0, Payment::count());
    }

    public function test_solo_el_administrador_puede_cancelar_pagos_en_la_lista(): void
    {
        $payment = app(PaymentService::class)->register($this->tenant->id, [
            'user_id' => $this->client->id, 'concept_type' => 'other', 'amount' => 100, 'method' => 'cash',
        ]);

        $this->enterPanelAs($this->recepcion);
        Livewire::test(ListPayments::class)->assertTableActionHidden('cancelar', $payment);

        $this->enterPanelAs($this->admin);
        Livewire::test(ListPayments::class)
            ->assertTableActionVisible('cancelar', $payment)
            ->callTableAction('cancelar', $payment, data: ['reason' => 'Error de captura'])
            ->assertHasNoTableActionErrors();

        $this->assertSame(Payment::STATUS_CANCELLED, $payment->refresh()->status);
    }

    public function test_recepcion_da_de_alta_un_cliente_sin_enviar_correo(): void
    {
        Mail::fake();
        $this->enterPanelAs($this->recepcion);

        Livewire::test(CreateClient::class)
            ->fillForm(['name' => 'Nueva', 'last_name' => 'Clienta', 'phone' => '9610000000', 'email' => 'nueva@ejemplo.com'])
            ->call('create')
            ->assertHasNoFormErrors();

        $client = User::where('email', 'nueva@ejemplo.com')->sole();
        $this->assertTrue($client->hasRole('cliente'));
        $this->assertTrue($client->tenants->contains($this->tenant));
        Mail::assertNothingSent();
    }

    public function test_la_lista_de_clientes_no_muestra_personal_y_recepcion_no_gestiona_usuarios(): void
    {
        $this->enterPanelAs($this->recepcion);

        Livewire::test(ListClients::class)
            ->assertCanSeeTableRecords([$this->client])
            ->assertCanNotSeeTableRecords([$this->admin, $this->recepcion]);

        $this->assertFalse(UserResource::canViewAny());
    }
}
