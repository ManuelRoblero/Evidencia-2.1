<?php

namespace App\Providers\Filament;

use App\Models\Tenant;
use App\Support\FilamentBrandColors;
use App\Support\ReleaseNotes;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\HtmlString;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class DashboardPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('dashboard')
            ->path('dashboard')
            ->viteTheme('resources/css/filament/dashboard/theme.css')
            ->login()
            ->brandName('TuEquilibrio · Personal')
            ->brandLogo(asset('assets/tuequilibrio_logo.svg'))
            ->darkModeBrandLogo(asset('assets/tuequilibrio_logo_dark.svg'))
            ->favicon(asset('favicon.svg'))
            ->brandLogoHeight('3.25rem')
            ->globalSearch(false)
            ->navigationGroups(['Operación', 'Caja', 'Clases', 'Membresías', 'Reportes', 'Administración del Sistema', 'E-commerce'])
            ->sidebarCollapsibleOnDesktop() // Agrega el botón para colapsar el menú y dejar solo los íconos
            ->sidebarWidth('16rem') // Hace el menú un poco más esbelto y elegante (por defecto es muy ancho)
            ->collapsedSidebarWidth('5rem')
            ->colors(FilamentBrandColors::panelColors())
            ->tenant(Tenant::class, slugAttribute: 'slug') // Habilita Multitenancy
            // ->tenantRegistration(RegisterTenant::class) // Descomentar cuando creemos la página de registro de tenants para el Admin
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                function () {
                    if (! request()->routeIs('filament.dashboard.auth.login')) {
                        return '';
                    }

                    return new HtmlString('
                        <style>
                            body {
                                background-image:
                                    radial-gradient(circle at 20% 20%, #cfe9e2 0%, transparent 45%), linear-gradient(135deg, #2f7f73 0%, #1f3d38 100%);
                                background-size: cover;
                                background-position: center;
                                background-repeat: no-repeat;
                                background-attachment: fixed;
                            }

                            .fi-simple-main-ctn,
                            .fi-simple-main {
                                animation: loginCardEnter 520ms cubic-bezier(0.2, 0.8, 0.2, 1) both;
                            }

                            .fi-simple-main {
                                box-shadow: 0 24px 56px -26px rgba(0, 0, 0, 0.55) !important;
                            }

                            .fi-simple-main [type="submit"] {
                                transition: transform 180ms ease, box-shadow 220ms ease, filter 220ms ease;
                                box-shadow: 0 12px 30px -14px rgba(0, 0, 0, 0.48);
                                animation: loginButtonPop 540ms ease 180ms both;
                            }

                            .fi-simple-main [type="submit"]:hover {
                                transform: translateY(-1px) scale(1.01);
                                filter: brightness(1.03);
                                box-shadow: 0 18px 34px -16px rgba(0, 0, 0, 0.58);
                            }

                            .fi-simple-main [type="submit"]:active {
                                transform: translateY(0) scale(0.99);
                            }

                            @keyframes loginCardEnter {
                                from {
                                    opacity: 0;
                                    transform: translateY(14px) scale(0.98);
                                }
                                to {
                                    opacity: 1;
                                    transform: translateY(0) scale(1);
                                }
                            }

                            @keyframes loginButtonPop {
                                from {
                                    opacity: 0;
                                    transform: translateY(6px) scale(0.97);
                                }
                                to {
                                    opacity: 1;
                                    transform: translateY(0) scale(1);
                                }
                            }
                        </style>
                    ');
                }
            )
            ->renderHook(
                PanelsRenderHook::FOOTER,
                function () {
                    $versionFile = base_path('version.txt');
                    $version = file_exists($versionFile) ? file_get_contents($versionFile) : 'Dev (Local)';

                    return new HtmlString('
                        <div class="text-center text-xs text-gray-500 py-4 w-full">
                            TuEquilibrio · Sistema de gestión v' . e(trim($version)) . '
                        </div>
                    ');
                }
            )
            ->renderHook(
                PanelsRenderHook::TOPBAR_LOGO_AFTER,
                function () {
                    // Si NO estamos en producción, mostramos la alerta
                    if (!app()->environment('production')) {
                        return new HtmlString('
                            <div class="flex flex-1 items-center justify-center pointer-events-none  ml-3">
                                <span class="bg-amber-500 text-black text-xs font-extrabold px-4 py-1.5 rounded-full shadow-md flex items-center gap-2 uppercase tracking-wider animate-pulse">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                                    Entorno de Pruebas
                                </span>
                            </div>
                        ');
                    }
                    
                    return ''; // En producción no renderiza nada
                }
            )
            ->renderHook(
                PanelsRenderHook::TOPBAR_LOGO_AFTER,
                function () {
                    $versionFile = base_path('version.txt');
                    $version = file_exists($versionFile) ? file_get_contents($versionFile) : 'Dev (Local)';
                    // Si NO estamos en producción, mostramos la alerta
                    if (!app()->environment('production')) {
                        return new HtmlString('
                            <div class="ml-3 text-xs text-gray-500 pointer-events-none">
                                v' . trim($version) . '
                            </div>
                        ');
                    }
                    
                    return ''; // En producción no renderiza nada
                }
            )
            ->renderHook(
                PanelsRenderHook::SIDEBAR_NAV_END,
                function () {
                    $user = auth()->user();

                    if (! $user || ! $user->hasRole(['admin', 'empleado'])) {
                        return '';
                    }

                    return new HtmlString(view('filament.components.release-notes-sidebar', [
                        'release' => ReleaseNotes::current(),
                    ])->render());
                }
            );
    }
}
