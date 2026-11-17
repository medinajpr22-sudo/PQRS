<?php

declare(strict_types=1);

use App\Http\Controllers\Tenant\Admin\AttachmentController;
use App\Http\Controllers\Tenant\Admin\AuthController;
use App\Http\Controllers\Tenant\Admin\DashboardController;
use App\Http\Controllers\Tenant\Admin\PqrsActivityController;
use App\Http\Controllers\Tenant\Admin\PqrsController;
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
    Route::post('radicar', [PqrsSubmissionController::class, 'store'])
        ->middleware('throttle:pqrs-submit')
        ->name('pqrs.store');
    Route::get('radicado', [PqrsSubmissionController::class, 'receipt'])->name('pqrs.receipt');

    Route::get('consultar', [PqrsTrackingController::class, 'create'])->name('tracking.create');
    Route::post('consultar', [PqrsTrackingController::class, 'store'])
        ->middleware('throttle:pqrs-tracking')
        ->name('tracking.store');
    Route::get('consultar/{pqrs}', [PqrsTrackingController::class, 'show'])->name('tracking.show');
    Route::get('consultar/{pqrs}/adjuntos/{attachment}', [PqrsTrackingController::class, 'attachment'])
        ->scopeBindings()
        ->name('tracking.attachment');

    // Panel de la empresa (funcionarios)
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::middleware('guest')->group(function () {
            Route::get('login', [AuthController::class, 'create'])->name('login');
            Route::post('login', [AuthController::class, 'store'])
                ->middleware('throttle:tenant-login')
                ->name('login.store');
        });

        Route::middleware('auth')->group(function () {
            Route::post('logout', [AuthController::class, 'destroy'])->name('logout');

            Route::get('/', DashboardController::class)->name('dashboard');
            Route::get('pqrs', [PqrsController::class, 'index'])->name('pqrs.index');
            Route::get('pqrs/{pqrs}', [PqrsController::class, 'show'])->name('pqrs.show');
            Route::patch('pqrs/{pqrs}/estado', [PqrsController::class, 'updateStatus'])->name('pqrs.status');
            Route::post('pqrs/{pqrs}/actividades', [PqrsActivityController::class, 'store'])->name('pqrs.activities.store');
            Route::get('pqrs/{pqrs}/adjuntos/{attachment}', AttachmentController::class)
                ->scopeBindings()
                ->name('pqrs.attachments.show');
        });
    });
});
