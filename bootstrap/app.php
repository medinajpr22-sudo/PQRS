<?php

use App\Http\Middleware\EnsureCentralDomain;
use App\Http\Middleware\EnsureStaffIsActive;
use App\Http\Middleware\EnsureTenantIsActive;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->alias([
            'central' => EnsureCentralDomain::class,
            'tenant.active' => EnsureTenantIsActive::class,
            'staff.active' => EnsureStaffIsActive::class,
        ]);

        // La verificación de dominio central debe correr antes que la sesión y la autenticación.
        $middleware->prependToPriorityList(StartSession::class, EnsureCentralDomain::class);

        // Invitados / autenticados se redirigen al login o panel que corresponda
        // según estemos en el dominio central o en el de una empresa.
        $middleware->redirectGuestsTo(fn () => tenancy()->initialized
            ? route('tenant.admin.login')
            : route('login'));

        $middleware->redirectUsersTo(fn () => tenancy()->initialized
            ? route('tenant.admin.dashboard')
            : route('dashboard'));

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
