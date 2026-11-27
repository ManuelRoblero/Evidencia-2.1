# TuEquilibrio · Sistema de gestión del estudio

Aplicación web para administrar el estudio TuEquilibrio: clientes, clases, reservaciones, membresías (paquetes de clases), pagos en mostrador, corte de caja y reportes.

Está hecha con **Laravel 12**, **Filament 4**, **Livewire** y **Tailwind**, y se abre desde el navegador.

## Fase 1 (lo que está activo)

Es una aplicación interna para el personal, en `/dashboard`.

| Módulo | Qué hace | Administrador | Recepción |
|---|---|---|---|
| Seguridad | Inicio de sesión, roles, contraseñas cifradas | ✔ | ✔ |
| Clientes | Alta, edición, clases disponibles e historial | ✔ | ✔ |
| Citas | Reservar validando cupo, cancelar, pase de lista (check-in) | ✔ | ✔ |
| Clases | Disciplinas, instructores y horario semanal | Edita | Solo consulta |
| Membresías | Paquetes de clases con precio y vigencia | ✔ | — |
| Pagos | Cobro en mostrador: efectivo, transferencia o tarjeta | Cobra y cancela | Solo cobra |
| Corte de caja | Total del día por método de pago y por usuario | ✔ | ✔ |
| Reportes | Ingresos y ocupación por periodo | ✔ | — |
| Usuarios y sucursales | Personal, roles y configuración | ✔ | — |

Reglas importantes:

- Al cobrar un **paquete**, sus clases se acreditan solas al cliente, con vigencia de 30 días (se cambia con `CREDIT_VALIDITY_DAYS`).
- Los pagos **no se borran**. Solo el administrador puede cancelarlos, y siempre con un motivo. Al cancelar un paquete se retiran las clases que el cliente aún no usó, y queda registrado quién y cuándo lo canceló.
- En una cita marcada como **"Cobrar al llegar"**, el check-in pide el monto y el método de pago, y el cobro aparece en el corte de caja.
- Al guardar una clase en el **horario semanal**, su hora se agrega sola a los horarios disponibles para reservar.

## Fase futura (apagada, no borrada)

La página web y el portal para clientes (reservas y compras en línea con Stripe) y la tienda en línea siguen en el código, pero están apagados. Para activarlos, edita el `.env`:

```env
FEATURE_CLIENT_PORTAL=true   # página web + panel /clientes + compra de paquetes en línea
FEATURE_STORE=true           # tienda en línea
```

Antes de activarlos, hay que cambiar a TuEquilibrio los textos, fotos y video de la página de inicio (`resources/views/livewire/landing-page.blade.php` y `public/assets/`). Todavía son los del proyecto original.

## Instalación

Requisitos: PHP 8.4, Composer, Node.js 20 o superior y la extensión `bcmath` de PHP.

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite        # En Windows: crea el archivo vacío a mano
php artisan migrate
php artisan db:seed --class=TuEquilibrioDemoSeeder   # datos de ejemplo (opcional)
npm install
npm run build
php artisan serve
```

Abre http://127.0.0.1:8000. Te lleva directo al inicio de sesión del personal.

Usuarios de ejemplo (contraseña `password`):

- Administrador: `admin@tuequilibrio.test`
- Recepción: `recepcion@tuequilibrio.test`

Cambia estas contraseñas antes de usar el sistema con datos reales.

## Pruebas

```bash
php artisan test
```

En `tests/Feature/TuEquilibrioTest.php` hay pruebas de cobros, cancelaciones, corte de caja, horario semanal, check-in con cobro, permisos por rol y los interruptores de fase.

## Dónde está cada cosa

| Qué | Archivo |
|---|---|
| Interruptores de fase | `config/tuequilibrio.php`, `app/Support/Features.php` |
| Reglas de cobro y cancelación | `app/Services/PaymentService.php` |
| Cálculos de corte y reportes | `app/Services/ReportService.php` |
| Pantallas nuevas | `app/Filament/Resources/{Clients,Disciplines,Instructors,ClassSchedules,Payments}` y `app/Filament/Pages/{CashCutPage,ReportsPage}.php` |
| Tablas nuevas | `database/migrations/2026_11_19_*` |
| Colores y logo | `app/Support/FilamentBrandColors.php`, `public/assets/tuequilibrio_logo*.svg` |
