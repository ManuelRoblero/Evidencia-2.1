<?php

use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\StoreCheckoutController;
use App\Http\Middleware\EnsureClientPortalEnabled;
use App\Http\Middleware\EnsureStoreEnabled;
use App\Livewire\Checkout;
use App\Livewire\CreditPackages;
use App\Livewire\GuestCreditPackages;
use App\Livewire\LandingPage;
use App\Livewire\ProductShow;
use App\Livewire\ShoppingCart;
use App\Livewire\StoreFront;
use App\Support\Features;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Fase 1: aplicación interna (panel /dashboard)
|--------------------------------------------------------------------------
| Mientras la página web para clientes esté desactivada, la raíz y /login
| llevan directo al panel del personal.
*/
if (Features::clientPortal()) {
    Route::get('/', LandingPage::class)->name('home');
} else {
    Route::redirect('/', '/dashboard')->name('home');
}

Route::get('/login', function () {
    return redirect(Features::clientPortal() ? '/clientes/login' : '/dashboard/login');
})->name('login');

/*
|--------------------------------------------------------------------------
| Fase futura: página web y compras en línea para clientes
|--------------------------------------------------------------------------
*/
Route::middleware(EnsureClientPortalEnabled::class)->group(function () {
    Route::get('/paquetes-disponibles', GuestCreditPackages::class)->name('credits.guest');

    Route::middleware(['auth'])->group(function () {
        // Vista de paquetes
        Route::get('/comprar-creditos', CreditPackages::class)->name('checkout.credits');

        // Procesamiento y Webhooks de Stripe Cashier
        Route::get('/checkout/procesar/{package}', [CheckoutController::class, 'process'])->name('checkout.process');
        Route::get('/checkout/success', [CheckoutController::class, 'success'])->name('checkout.success');
        Route::get('/checkout/cancel', [CheckoutController::class, 'cancel'])->name('checkout.cancel');
    });
});

/*
|--------------------------------------------------------------------------
| Fase futura: tienda en línea
|--------------------------------------------------------------------------
*/
Route::middleware(EnsureStoreEnabled::class)->group(function () {
    Route::get('/tienda', StoreFront::class)->name('store.index');
    Route::get('/tienda/producto/{slug}', ProductShow::class)->name('store.product.show');
    Route::get('/carrito', ShoppingCart::class)->name('store.cart');
    Route::get('/checkout', Checkout::class)->name('store.checkout');
    Route::middleware('auth')->group(function () {
        Route::get('/checkout/tienda/success', [StoreCheckoutController::class, 'success'])->name('store.checkout.success');
        Route::get('/checkout/tienda/cancel', [StoreCheckoutController::class, 'cancel'])->name('store.checkout.cancel');
    });
});
