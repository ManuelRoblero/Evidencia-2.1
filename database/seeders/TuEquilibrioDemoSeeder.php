<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\ClassSchedule;
use App\Models\CreditPackage;
use App\Models\Discipline;
use App\Models\Instructor;
use App\Models\Tenant;
use App\Models\User;
use App\Services\PaymentService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

/**
 * Datos de demostración de TuEquilibrio.
 *
 *   php artisan db:seed --class=TuEquilibrioDemoSeeder
 *
 * Usuarios:
 *   admin@tuequilibrio.test      / password  (Administrador)
 *   recepcion@tuequilibrio.test  / password  (Recepción)
 */
class TuEquilibrioDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolesSeeder::class);

        $tenant = Tenant::updateOrCreate(['slug' => 'tuequilibrio'], [
            'name' => 'TuEquilibrio',
            'address' => 'Sucursal principal',
            'max_appointments_per_day' => 60,
            'capacity_per_slot' => 8,
            'shipping_fee' => 0,
        ]);

        $admin = User::firstOrCreate(['email' => 'admin@tuequilibrio.test'], [
            'name' => 'Dueña', 'last_name' => 'TuEquilibrio', 'phone' => '0000000001', 'password' => Hash::make('password'),
        ]);
        $admin->syncRoles(['admin']);
        $admin->tenants()->syncWithoutDetaching([$tenant->id]);

        $recepcion = User::firstOrCreate(['email' => 'recepcion@tuequilibrio.test'], [
            'name' => 'Recepción', 'last_name' => 'Turno mañana', 'phone' => '0000000002', 'password' => Hash::make('password'),
        ]);
        $recepcion->syncRoles(['empleado']);
        $recepcion->tenants()->syncWithoutDetaching([$tenant->id]);

        // Disciplinas e instructores
        $pilates = Discipline::firstOrCreate(['tenant_id' => $tenant->id, 'name' => 'Pilates'], ['duration_minutes' => 50, 'description' => 'Pilates en reformer']);
        $yoga = Discipline::firstOrCreate(['tenant_id' => $tenant->id, 'name' => 'Yoga'], ['duration_minutes' => 60, 'description' => 'Hatha y vinyasa']);
        $funcional = Discipline::firstOrCreate(['tenant_id' => $tenant->id, 'name' => 'Funcional'], ['duration_minutes' => 45]);

        $ana = Instructor::firstOrCreate(['tenant_id' => $tenant->id, 'name' => 'Ana López'], ['phone' => '0000000010']);
        $luis = Instructor::firstOrCreate(['tenant_id' => $tenant->id, 'name' => 'Luis Pérez'], ['phone' => '0000000011']);
        $sofia = Instructor::firstOrCreate(['tenant_id' => $tenant->id, 'name' => 'Sofía Ramírez'], ['phone' => '0000000012']);

        // Horario semanal: lunes a viernes
        $plan = [
            '07:00' => [$pilates, $ana],
            '09:00' => [$yoga, $sofia],
            '18:00' => [$funcional, $luis],
            '19:00' => [$pilates, $ana],
        ];
        foreach (range(1, 5) as $day) {
            foreach ($plan as $time => [$discipline, $instructor]) {
                ClassSchedule::updateOrCreate(
                    ['tenant_id' => $tenant->id, 'day_of_week' => $day, 'start_time' => $time],
                    ['discipline_id' => $discipline->id, 'instructor_id' => $instructor->id],
                );
            }
        }
        ClassSchedule::updateOrCreate(
            ['tenant_id' => $tenant->id, 'day_of_week' => 6, 'start_time' => '09:00'],
            ['discipline_id' => $yoga->id, 'instructor_id' => $sofia->id],
        );

        // Paquetes (membresías)
        $packages = [
            CreditPackage::firstOrCreate(['name' => 'Clase suelta'], ['credits_amount' => 1, 'price' => 150]),
            CreditPackage::firstOrCreate(['name' => 'Paquete 8 clases'], ['credits_amount' => 8, 'price' => 900]),
            CreditPackage::firstOrCreate(['name' => 'Paquete 12 clases'], ['credits_amount' => 12, 'price' => 1200]),
        ];

        if (User::role('cliente')->count() >= 10) {
            return; // Ya hay datos de ejemplo
        }

        // Clientes de ejemplo
        $names = [
            ['María', 'Hernández'], ['Lucía', 'Gómez'], ['Fernanda', 'Díaz'], ['Carlos', 'Martínez'],
            ['Valeria', 'Ruiz'], ['Daniela', 'Torres'], ['Andrea', 'Flores'], ['Jorge', 'Morales'],
            ['Paola', 'Castillo'], ['Ximena', 'Vargas'], ['Renata', 'Mendoza'], ['Diego', 'Ortiz'],
        ];
        $clients = collect($names)->map(function (array $n, int $i) use ($tenant) {
            $client = User::firstOrCreate(['email' => 'cliente'.($i + 1).'@tuequilibrio.test'], [
                'name' => $n[0], 'last_name' => $n[1], 'phone' => '96100000'.str_pad((string) $i, 2, '0', STR_PAD_LEFT), 'password' => Hash::make('password'),
            ]);
            $client->syncRoles(['cliente']);
            $client->tenants()->syncWithoutDetaching([$tenant->id]);

            return $client;
        });

        // Pagos del mes (se registran con el mismo servicio que usa la pantalla de cobro)
        $service = app(PaymentService::class);
        $methods = ['cash', 'card', 'transfer'];
        $start = today()->startOfMonth();
        $daySpan = max(1, today()->day - 1);
        foreach ($clients as $i => $client) {
            $package = $packages[1 + ($i % 2)];
            Carbon::setTestNow($start->copy()->addDays($i % $daySpan)->setTime(8 + $i % 10, 15));
            $service->register($tenant->id, [
                'user_id' => $client->id,
                'concept_type' => 'package',
                'credit_package_id' => $package->id,
                'amount' => $package->price,
                'method' => $methods[$i % 3],
                'reference' => $methods[$i % 3] === 'cash' ? null : 'FOLIO-'.(1000 + $i),
            ], $i % 2 === 0 ? $recepcion->id : $admin->id);
        }
        Carbon::setTestNow();

        // Dos cobros de hoy para ver el corte de caja
        $service->register($tenant->id, ['user_id' => $clients[0]->id, 'concept_type' => 'single_class', 'description' => 'Clase de prueba', 'amount' => 150, 'method' => 'cash'], $recepcion->id);
        $service->register($tenant->id, ['user_id' => $clients[3]->id, 'concept_type' => 'package', 'credit_package_id' => $packages[1]->id, 'amount' => 900, 'method' => 'card', 'reference' => 'TPV-5521'], $recepcion->id);

        // Reservas de las últimas dos semanas con pase de lista, y de los próximos días
        $times = array_keys($plan);
        for ($d = -14; $d <= 3; $d++) {
            $date = today()->addDays($d);
            if ($date->dayOfWeekIso > 5) {
                continue;
            }
            foreach ($times as $t => $time) {
                $howMany = [6, 3, 7, 5][$t] - (abs($d) % 3);
                foreach ($clients->shuffle()->take(max(1, $howMany)) as $client) {
                    $past = $d < 0;
                    Appointment::create([
                        'tenant_id' => $tenant->id,
                        'user_id' => $client->id,
                        'date' => $date->toDateString(),
                        'time_slot' => $time,
                        'status' => $past ? 'completed' : 'scheduled',
                        'check_in_status' => $past ? (random_int(1, 10) > 1 ? 'cliente_llego' : 'cliente_no_llego') : 'pendiente',
                        'payment_method' => 'credit_balance',
                        'booking_origin' => 'admin_panel',
                    ]);
                }
            }
        }

        $this->command?->info('TuEquilibrio listo. Entra con admin@tuequilibrio.test / password');
    }
}
