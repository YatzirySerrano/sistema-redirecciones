<?php

namespace App\Providers;

use App\Models\Paginas\DominioPagina;
use App\Models\Paginas\PaginaEnlaces;
use App\Services\Paginas\Dominios\ComprobadorHttps;
use App\Services\Paginas\Dominios\ComprobadorHttpsCurl;
use App\Services\Paginas\Dominios\ResolvedorDns;
use App\Services\Paginas\Dominios\ResolvedorDnsSistema;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

/**
 * Página de enlaces: implementaciones de DNS y HTTPS (sustituibles en las
 * pruebas) y alias polimórficos de sus modelos.
 */
class PaginasServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ResolvedorDns::class, ResolvedorDnsSistema::class);
        $this->app->bind(ComprobadorHttps::class, ComprobadorHttpsCurl::class);
    }

    public function boot(): void
    {
        // Se suma al mapa polimórfico obligatorio (auditoría).
        Relation::morphMap([
            'pagina_enlaces' => PaginaEnlaces::class,
            'pagina_dominio' => DominioPagina::class,
        ]);
    }
}
