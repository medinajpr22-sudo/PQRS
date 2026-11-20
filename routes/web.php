<?php

use App\Http\Controllers\Central\DashboardController;
use App\Http\Controllers\Central\PqrsController;
use App\Http\Controllers\Central\SuperAdminController;
use App\Http\Controllers\Central\TenantAdminController;
use App\Http\Controllers\Central\TenantController;
use App\Http\Controllers\Central\TenantFormController;
use App\Http\Controllers\Central\TenantStatusController;
use App\Http\Controllers\Central\TenantThemeController;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rutas del panel central (super administrador)
|--------------------------------------------------------------------------
|
| Solo responden en los dominios centrales (CENTRAL_DOMAINS). La ruta "/"
| pertenece al portal de cada empresa; en el dominio central redirige al
| dashboard (ver TenancyServiceProvider).
|
*/

Route::middleware('central')->group(function () {
    Route::middleware('auth')->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');

        Route::get('empresas', [TenantController::class, 'index'])->name('tenants.index');
        Route::get('empresas/crear', [TenantController::class, 'create'])->name('tenants.create');
        Route::post('empresas', [TenantController::class, 'store'])->name('tenants.store');
        Route::get('empresas/{tenant}/editar', [TenantController::class, 'edit'])->name('tenants.edit');
        Route::put('empresas/{tenant}', [TenantController::class, 'update'])->name('tenants.update');
        Route::delete('empresas/{tenant}', [TenantController::class, 'destroy'])->name('tenants.destroy');

        Route::post('empresas/{tenant}/diseno', [TenantThemeController::class, 'update'])->name('tenants.theme.update');
        Route::put('empresas/{tenant}/formulario', [TenantFormController::class, 'update'])->name('tenants.form.update');
        Route::patch('empresas/{tenant}/estado', [TenantStatusController::class, 'update'])->name('tenants.status.update');
        Route::put('empresas/{tenant}/administradores/{admin}', [TenantAdminController::class, 'update'])
            ->whereNumber('admin')
            ->name('tenants.admins.update');

        // Datos personales de todas las empresas y acceso total a la
        // plataforma: piden confirmar la contraseña (vale por 3 horas).
        Route::middleware(RequirePassword::class)->group(function () {
            // PQRS de todas las empresas: ver, descargar en Excel y eliminar.
            Route::get('pqrs', [PqrsController::class, 'index'])->name('pqrs.index');
            Route::get('pqrs/exportar', [PqrsController::class, 'export'])->name('pqrs.export');
            Route::delete('pqrs', [PqrsController::class, 'destroy'])->name('pqrs.destroy');

            Route::get('super-administradores', [SuperAdminController::class, 'index'])->name('super-admins.index');
            Route::post('super-administradores', [SuperAdminController::class, 'store'])->name('super-admins.store');
            Route::put('super-administradores/{superAdmin}', [SuperAdminController::class, 'update'])
                ->whereNumber('superAdmin')
                ->name('super-admins.update');
            Route::delete('super-administradores/{superAdmin}', [SuperAdminController::class, 'destroy'])
                ->whereNumber('superAdmin')
                ->name('super-admins.destroy');
        });
    });

    require __DIR__.'/settings.php';
});
