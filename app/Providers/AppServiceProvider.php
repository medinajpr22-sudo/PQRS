<?php

namespace App\Providers;

use App\Mail\MicrosoftGraphTransport;
use App\Models\Pqrs;
use App\Models\User;
use App\Policies\PqrsPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Mail\Markdown;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureRateLimiting();
        $this->configureGates();
        $this->configureRoutePatterns();
        $this->configureMail();
    }

    /**
     * MAIL_MAILER=microsoft-graph: envío por Microsoft 365 con OAuth.
     */
    protected function configureMail(): void
    {
        Mail::extend('microsoft-graph', fn (array $config) => new MicrosoftGraphTransport(
            tenantId: trim((string) ($config['tenant_id'] ?? '')),
            clientId: trim((string) ($config['client_id'] ?? '')),
            clientSecret: trim((string) ($config['client_secret'] ?? '')),
            // Graph envía desde el buzón del remitente.
            mailbox: trim((string) config('mail.from.address')),
        ));
    }

    /**
     * Ids numéricos en las rutas: «/usuarios/abc» es un 404 y no un error de
     * PostgreSQL (que no acepta texto donde va un número).
     */
    protected function configureRoutePatterns(): void
    {
        foreach (['attachment', 'user', 'sede', 'admin'] as $parameter) {
            Route::pattern($parameter, '[0-9]+');
        }
    }

    /**
     * Permisos dentro del panel de cada empresa.
     */
    protected function configureGates(): void
    {
        // Solo el administrador de empresa gestiona usuarios y sedes.
        Gate::define('manage-users', fn (User $user) => $user->isAdmin());
        Gate::define('manage-sedes', fn (User $user) => $user->isAdmin());
        Gate::define('manage-branding', fn (User $user) => $user->isAdmin());

        // Un agente solo ve las PQRS de sus sedes.
        Gate::policy(Pqrs::class, PqrsPolicy::class);
    }

    /**
     * Límites para los formularios públicos de cada empresa.
     */
    protected function configureRateLimiting(): void
    {
        // Además del «No soy un robot», cada conexión tiene un tope de PQRS
        // por minuto y por hora. Cada límite necesita su propia clave.
        RateLimiter::for('pqrs-submit', fn (Request $request) => [
            Limit::perMinute(5)->by('pqrs-submit-minute|'.tenant('id').'|'.$request->ip()),
            Limit::perHour(30)->by('pqrs-submit-hour|'.tenant('id').'|'.$request->ip()),
        ]);

        RateLimiter::for('pqrs-captcha', fn (Request $request) => Limit::perMinute(20)
            ->by('pqrs-captcha|'.tenant('id').'|'.$request->ip()));

        // Consulta de estado: tope por conexión (además, el controlador
        // limita los intentos fallidos por correo y por radicado).
        RateLimiter::for('pqrs-tracking', fn (Request $request) => [
            Limit::perMinute(10)->by('pqrs-tracking-minute|'.tenant('id').'|'.$request->ip()),
            Limit::perHour(60)->by('pqrs-tracking-hour|'.tenant('id').'|'.$request->ip()),
        ]);

        RateLimiter::for('tenant-login', fn (Request $request) => Limit::perMinute(5)
            ->by('tenant-login|'.tenant('id').'|'.mb_strtolower((string) $request->input('email')).'|'.$request->ip()));

        // Ritmo del buzón que envía los correos de todas las empresas (ver
        // config/pqrs.php y QueuedMailNotification). Las claves son globales
        // a propósito: el buzón es uno solo.
        RateLimiter::for('outgoing-mail', function (): array {
            $limits = [Limit::perMinute(max(1, (int) config('pqrs.mail.per_minute')))->by('outgoing-mail-minute')];

            if (($perDay = (int) config('pqrs.mail.per_day')) > 0) {
                $limits[] = Limit::perDay($perDay)->by('outgoing-mail-day');
            }

            return $limits;
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        // En los correos, lo que escribe el ciudadano (nombre, asunto…) se
        // muestra como texto: «[Clic aquí](http://…)» no se vuelve un enlace.
        Markdown::withSecuredEncoding();

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
