<?php

namespace App\Providers;

use App\Models\Negocio;
use App\Models\Producto;
use App\Models\User;
use App\Services\Configuracion\AjustesPlataforma;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
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
        $this->configureAuthorization();
        $this->configureRateLimiting();
        $this->configureNotifications();

        Event::listen(Login::class, function (Login $event): void {
            if ($event->user instanceof User) {
                $event->user->forceFill(['ultimo_acceso_en' => now()])->saveQuietly();
            }
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        // Alias estables para relaciones polimórficas (auditoría) en lugar de
        // nombres de clase PHP.
        Relation::enforceMorphMap([
            'negocio' => Negocio::class,
            'producto' => Producto::class,
            'usuario' => User::class,
        ]);

        if (app()->isProduction()) {
            URL::forceScheme('https');
        }

        Password::defaults(fn (): Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : Password::min(8),
        );
    }

    /**
     * Gates por rol. La autorización fina sobre productos vive en
     * App\Policies\ProductoPolicy (descubierta por convención).
     */
    protected function configureAuthorization(): void
    {
        Gate::define('administrar-plataforma', fn (User $usuario) => $usuario->esAdministrador());
        Gate::define('usar-panel-cliente', fn (User $usuario) => $usuario->esCliente() && $usuario->negocio_id !== null);
        Gate::define('ver-estadisticas-cliente', fn (User $usuario) => $usuario->esCliente()
            && app(AjustesPlataforma::class)->booleano('clientes_ven_estadisticas'));
    }

    protected function configureRateLimiting(): void
    {
        RateLimiter::for('redirecciones', fn (Request $request) => Limit::perMinute((int) config('dasti.limite_redirecciones_por_minuto'))
            ->by($request->ip()));

        RateLimiter::for('cambios-destino', fn (Request $request) => Limit::perMinute(20)
            ->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip())));

        RateLimiter::for('qr', fn (Request $request) => Limit::perMinute(90)
            ->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip())));
    }

    protected function configureNotifications(): void
    {
        ResetPassword::toMailUsing(function (CanResetPassword $notifiable, string $token): MailMessage {
            $url = route('password.reset', ['token' => $token, 'email' => $notifiable->getEmailForPasswordReset()]);
            $minutos = (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

            return (new MailMessage)
                ->subject('Restablecer contraseña')
                ->line('Recibimos una solicitud para restablecer la contraseña de tu cuenta.')
                ->action('Restablecer contraseña', $url)
                ->line("Este enlace caduca en {$minutos} minutos.")
                ->line('Si no solicitaste el cambio, no es necesario hacer nada.');
        });
    }
}
