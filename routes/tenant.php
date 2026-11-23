<?php

declare(strict_types=1);

use App\Http\Controllers\Tenant\Admin\AttachmentController;
use App\Http\Controllers\Tenant\Admin\AuthController;
use App\Http\Controllers\Tenant\Admin\BrandingController;
use App\Http\Controllers\Tenant\Admin\DashboardController;
use App\Http\Controllers\Tenant\Admin\PqrsActivityController;
use App\Http\Controllers\Tenant\Admin\PqrsController;
use App\Http\Controllers\Tenant\Admin\SedeController;
use App\Http\Controllers\Tenant\Admin\UserController;
use App\Http\Controllers\Tenant\PortalController;
use App\Http\Controllers\Tenant\PqrsSubmissionController;
use App\Http\Controllers\Tenant\PqrsTrackingController;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

/*
|--------------------------------------------------------------------------
| Rutas de cada empresa (tenant)
|--------------------------------------------------------------------------
|
| Se identifican por dominio (p. ej. andina.localhost). Al entrar, la
| conexión de base de datos, el almacenamiento y las colas apuntan a los
| de la empresa.
|
*/

Route::middleware([
    'web',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
    'tenant.active',
])->name('tenant.')->group(function () {
    // Portal público
    Route::get('/', PortalController::class)->name('home');

    Route::get('radicar', [PqrsSubmissionController::class, 'create'])->name('pqrs.create');
    Route::get('radicar/verificacion', [PqrsSubmissionController::class, 'captcha'])
        ->middleware('throttle:pqrs-captcha')
        ->name('pqrs.captcha');
    Route::post('radicar', [PqrsSubmissionController::class, 'store'])
        ->middleware('throttle:pqrs-submit')
        ->name('pqrs.store');
    Route::get('radicado', [PqrsSubmissionController::class, 'receipt'])->name('pqrs.receipt');

    Route::get('consultar', [PqrsTrackingController::class, 'create'])->name('tracking.create');
    Route::post('consultar', [PqrsTrackingController::class, 'store'])
        ->middleware('throttle:pqrs-tracking')
        ->name('tracking.store');
    // Un radicado que no existe responde igual que uno ajeno (vuelve a la
    // consulta): así no se puede averiguar qué radicados existen.
    Route::get('consultar/{pqrs}', [PqrsTrackingController::class, 'show'])
        ->missing(fn () => to_route('tenant.tracking.create'))
        ->name('tracking.show');
    Route::post('consultar/{radicado}/cerrar', [PqrsTrackingController::class, 'forget'])
        ->where('radicado', '[A-Za-z0-9-]{1,40}')
        ->name('tracking.forget');
    Route::get('consultar/{pqrs}/adjuntos/{attachment}', [PqrsTrackingController::class, 'attachment'])
        ->scopeBindings()
        ->missing(fn () => to_route('tenant.tracking.create'))
        ->name('tracking.attachment');

    // Panel de la empresa (administrador de empresa y agentes)
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::middleware('guest')->group(function () {
            Route::get('login', [AuthController::class, 'create'])->name('login');
            Route::post('login', [AuthController::class, 'store'])
                ->middleware('throttle:tenant-login')
                ->name('login.store');
        });

        Route::middleware(['auth', 'staff.active'])->group(function () {
            Route::post('logout', [AuthController::class, 'destroy'])->name('logout');

            Route::get('/', DashboardController::class)->name('dashboard');
            Route::get('pqrs', [PqrsController::class, 'index'])->name('pqrs.index');
            Route::get('pqrs/exportar', [PqrsController::class, 'export'])
                ->middleware('can:export-pqrs')
                ->name('pqrs.export');
            // Un agente solo entra a las PQRS de sus sedes (PqrsPolicy).
            Route::middleware('can:view,pqrs')->group(function () {
                Route::get('pqrs/{pqrs}', [PqrsController::class, 'show'])->name('pqrs.show');
                Route::patch('pqrs/{pqrs}/estado', [PqrsController::class, 'updateStatus'])->name('pqrs.status');
                Route::patch('pqrs/{pqrs}/asignacion', [PqrsController::class, 'assign'])->name('pqrs.assign');
                Route::patch('pqrs/{pqrs}/sede', [PqrsController::class, 'changeSede'])->name('pqrs.sede');
                Route::post('pqrs/{pqrs}/actividades', [PqrsActivityController::class, 'store'])->name('pqrs.activities.store');
                Route::get('pqrs/{pqrs}/adjuntos/{attachment}', AttachmentController::class)
                    ->scopeBindings()
                    ->name('pqrs.attachments.show');
            });

            // Usuarios de la empresa (solo el administrador de empresa)
            Route::middleware('can:manage-users')->group(function () {
                Route::get('usuarios', [UserController::class, 'index'])->name('users.index');
                Route::post('usuarios', [UserController::class, 'store'])->name('users.store');
                Route::put('usuarios/{user}', [UserController::class, 'update'])->name('users.update');
            });

            // Sedes de la empresa (solo el administrador de empresa)
            // Colores del portal y del panel (solo el administrador de empresa)
            Route::middleware('can:manage-branding')->group(function () {
                Route::get('colores', [BrandingController::class, 'edit'])->name('branding.edit');
                Route::put('colores', [BrandingController::class, 'update'])->name('branding.update');
            });

            Route::middleware('can:manage-sedes')->group(function () {
                Route::get('sedes', [SedeController::class, 'index'])->name('sedes.index');
                Route::post('sedes', [SedeController::class, 'store'])->name('sedes.store');
                Route::put('sedes/{sede}', [SedeController::class, 'update'])->name('sedes.update');
            });
        });
    });
});
