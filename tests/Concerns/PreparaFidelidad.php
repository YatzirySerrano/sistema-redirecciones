<?php

namespace Tests\Concerns;

use App\Enums\OrigenContratacion;
use App\Enums\TipoCredencial;
use App\Models\Fidelidad\Campana;
use App\Models\Fidelidad\Consumidor;
use App\Models\Fidelidad\Credencial;
use App\Models\Negocio;
use App\Models\Rol;
use App\Models\Servicio;
use App\Models\User;
use App\Services\Fidelidad\GestorCampanas;
use App\Services\Fidelidad\GestorConsumidores;
use App\Services\Servicios\GestorContrataciones;
use Illuminate\Support\Str;

trait PreparaFidelidad
{
    protected function negocioConFidelidad(?Negocio $negocio = null): Negocio
    {
        $negocio ??= Negocio::factory()->create();
        app(GestorContrataciones::class)->activar($negocio, Servicio::fidelidad(), OrigenContratacion::Manual, motivo: 'Prueba');

        return $negocio;
    }

    protected function propietario(Negocio $negocio): User
    {
        return User::factory()->cliente($negocio)->create();
    }

    protected function empleado(Negocio $negocio): User
    {
        return User::factory()->cliente($negocio)->conRol('empleado')->create();
    }

    /**
     * @param  array<string, mixed>  $reglas
     */
    protected function campana(Negocio $negocio, array $reglas = []): Campana
    {
        return app(GestorCampanas::class)->crear($negocio, [
            'nombre' => 'Café gratis',
            'meta_visitas' => 3,
            'recompensa' => 'Un café americano',
            'inicia_en' => now()->subDay()->toDateString(),
            'max_visitas_por_dia' => 1,
            'intervalo_minimo_minutos' => 0,
            'repetir_ciclo' => true,
            ...$reglas,
        ], $this->propietario($negocio));
    }

    protected function consumidor(Negocio $negocio, array $datos = []): Consumidor
    {
        return app(GestorConsumidores::class)->registrar($negocio, [
            'nombre' => 'Ana López',
            'telefono' => (string) random_int(5500000000, 5599999999),
            ...$datos,
        ], $this->propietario($negocio));
    }

    protected function tarjeta(Consumidor $consumidor): Credencial
    {
        return app(GestorConsumidores::class)->emitirCredencial($consumidor, TipoCredencial::Nfc, $this->propietario($consumidor->negocio));
    }

    protected function uuid(): string
    {
        return (string) Str::uuid();
    }

    /**
     * @param  list<string>  $permisos
     */
    protected function rolNegocio(string $nombre, array $permisos): Rol
    {
        $rol = new Rol;
        $rol->forceFill(['name' => $nombre, 'guard_name' => 'web', 'ambito' => 'negocio'])->save();
        $rol->syncPermissions($permisos);

        return $rol;
    }
}
