<?php

namespace App\Providers;

use App\Listeners\AuditarEventosDeSeguridad;
use App\Models\ArchivoPublico;
use App\Models\Contratacion;
use App\Models\Fidelidad\Campana;
use App\Models\Fidelidad\Consumidor;
use App\Models\Fidelidad\Diseno;
use App\Models\Fidelidad\Recompensa;
use App\Models\Negocio;
use App\Models\Pago;
use App\Models\Producto;
use App\Models\Rol;
use App\Models\Servicio;
use App\Models\User;
use App\Services\Apariencia\TemaVisual;
use App\Services\Autorizacion\CatalogoPermisos;
use App\Services\Configuracion\AjustesPlataforma;
use App\Services\Pagos\PasarelaOpenpay;
use App\Services\Pagos\PasarelaPago;
use App\Services\Servicios\AccesoServicios;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
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
        // Una instancia por petición: los ajustes se leen una sola vez de la
        // caché aunque se consulten en cada producto de un listado.
        $this->app->scoped(AjustesPlataforma::class);
        $this->app->scoped(TemaVisual::class);
        $this->app->scoped(AccesoServicios::class);
        $this->app->bind(PasarelaPago::class, PasarelaOpenpay::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureAuthorization();
        $this->configureRateLimiting();

        Event::subscribe(AuditarEventosDeSeguridad::class);
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
            'rol' => Rol::class,
            'contratacion' => Contratacion::class,
            'pago' => Pago::class,
            'servicio' => Servicio::class,
            'fidelidad_campana' => Campana::class,
            'fidelidad_consumidor' => Consumidor::class,
            'fidelidad_recompensa' => Recompensa::class,
            'fidelidad_diseno' => Diseno::class,
            'archivo_publico' => ArchivoPublico::class,
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
     * Autorización.
     *
     * - Permisos dinámicos (CatalogoPermisos, roles en MariaDB): cualquier
     *   habilidad con nombre de permiso («negocios.ver», …) se resuelve aquí
     *   con User::tienePermiso, que respeta el ámbito de la cuenta. Se
     *   devuelve true/false explícito para que nada más lo amplíe.
     * - Gates de ámbito: administrar-plataforma (personal de DASTI) y
     *   usar-panel-cliente (usuario de un negocio).
     * - La autorización sobre productos concretos (propiedad) vive en
     *   App\Policies\ProductoPolicy.
     */
    protected function configureAuthorization(): void
    {
        Gate::before(function (User $usuario, string $habilidad): ?bool {
            if (! CatalogoPermisos::existe($habilidad)) {
                return null;
            }

            return $usuario->tienePermiso($habilidad);
        });

        Gate::define('administrar-plataforma', fn (User $usuario) => $usuario->esAdministrador());
        Gate::define('usar-panel-cliente', fn (User $usuario) => $usuario->esCliente() && $usuario->negocio_id !== null);
        Gate::define('ver-estadisticas-cliente', fn (User $usuario) => $usuario->esCliente()
            && $usuario->tienePermiso('estadisticas.ver')
            && app(AjustesPlataforma::class)->booleano('clientes_ven_estadisticas'));
    }

    protected function configureRateLimiting(): void
    {
        RateLimiter::for('redirecciones', fn (Request $request) => Limit::perMinute(max(1, (int) config('dasti.limite_redirecciones_por_minuto')))
            ->by($request->ip()));

        RateLimiter::for('cambios-destino', fn (Request $request) => Limit::perMinute(20)
            ->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip())));

        RateLimiter::for('qr', fn (Request $request) => Limit::perMinute(90)
            ->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip())));
    }
}
