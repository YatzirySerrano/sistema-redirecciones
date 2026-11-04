<?php

namespace Tests\Feature\Autorizacion;

use App\Enums\EstadoPago;
use App\Models\Negocio;
use App\Models\Pago;
use App\Models\Rol;
use App\Models\Servicio;
use App\Models\User;
use App\Services\Autorizacion\CatalogoPermisos;
use App\Services\Autorizacion\GuardianRoles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as Rutas;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Catálogo completo de permisos: toda habilidad usada en rutas existe en el
 * catálogo, la matriz muestra todos los módulos (también los de negocio para
 * roles de plataforma, gracias a «Diseñar para negocios») y se conservan
 * las protecciones contra escalamiento.
 */
class CatalogoPermisosAmpliadoTest extends TestCase
{
    use RefreshDatabase;

    public function test_cada_permiso_usado_en_las_rutas_existe_en_el_catalogo(): void
    {
        $gates = ['administrar-plataforma', 'usar-panel-cliente', 'ver-estadisticas-cliente', 'disenar-para-negocios'];

        $usados = collect(Rutas::getRoutes()->getRoutes())
            ->flatMap(fn (Route $r) => $r->gatherMiddleware())
            ->filter(fn ($m) => is_string($m) && str_starts_with($m, 'can:'))
            ->map(fn (string $m) => explode(',', substr($m, 4))[0])
            ->unique()
            ->reject(fn (string $h) => in_array($h, $gates, true));

        foreach ($usados as $habilidad) {
            $this->assertTrue(CatalogoPermisos::existe($habilidad), "La habilidad «{$habilidad}» no está en el catálogo.");
        }
    }

    public function test_la_matriz_incluye_todos_los_modulos_con_descripcion_y_grupo(): void
    {
        $matriz = collect(CatalogoPermisos::matriz())->keyBy('clave');

        foreach (['panel', 'negocios', 'usuarios', 'roles', 'productos', 'redirecciones', 'qr', 'estadisticas', 'auditoria', 'configuracion', 'personalizacion', 'servicios', 'pagos', 'fidelidad', 'paginas'] as $modulo) {
            $this->assertTrue($matriz->has($modulo), "Falta el módulo {$modulo}");
            $this->assertNotSame('', $matriz[$modulo]['descripcion']);
        }

        $this->assertSame('plataforma', $matriz['negocios']['grupo']);
        $this->assertSame('mixto', $matriz['fidelidad']['grupo']);
        $this->assertSame('mixto', $matriz['paginas']['grupo']);

        foreach (['fidelidad.disenar_negocios', 'paginas.disenar_negocios', 'paginas.estadisticas', 'pagos.gestionar', 'pagos.reembolsar'] as $nuevo) {
            $this->assertTrue(CatalogoPermisos::existe($nuevo));
        }

        // Los de diseño para negocios son solo del personal DASTI.
        $this->assertTrue(CatalogoPermisos::admiteAmbito('fidelidad.disenar_negocios', CatalogoPermisos::AMBITO_PLATAFORMA));
        $this->assertFalse(CatalogoPermisos::admiteAmbito('fidelidad.disenar_negocios', CatalogoPermisos::AMBITO_NEGOCIO));
        $this->assertFalse(CatalogoPermisos::admiteAmbito('paginas.disenar_negocios', CatalogoPermisos::AMBITO_NEGOCIO));

        // Secciones dentro de Fidelidad.
        $secciones = collect($matriz['fidelidad']['permisos'])->pluck('seccion')->unique()->values()->all();
        $this->assertEqualsCanonicalizing(['General', 'Mostrador', 'Consumidores', 'Campañas', 'Diseño', 'Administración'], $secciones);
    }

    public function test_el_formulario_de_roles_recibe_la_matriz_completa(): void
    {
        $super = User::factory()->superadministrador()->create();

        $this->actingAs($super)->get(route('admin.roles.create'))->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('admin/roles/Form')
                ->has('matriz', count(CatalogoPermisos::MODULOS))
                ->where('matriz.12.clave', 'pagos'));
    }

    public function test_un_administrador_no_puede_otorgar_permisos_de_diseno_que_no_tiene(): void
    {
        $limitado = new Rol;
        $limitado->forceFill(['name' => 'Soporte', 'guard_name' => 'web', 'ambito' => CatalogoPermisos::AMBITO_PLATAFORMA])->save();
        $limitado->syncPermissions(['panel.ver', 'roles.ver', 'roles.crear', 'roles.administrar_permisos']);
        $actor = User::factory()->administrador()->conRol($limitado)->create();

        $guardian = app(GuardianRoles::class);
        $this->assertFalse($guardian->puedeOtorgar($actor, ['paginas.disenar_negocios'], CatalogoPermisos::AMBITO_PLATAFORMA));
        $this->assertTrue($guardian->puedeOtorgar($actor, ['panel.ver'], CatalogoPermisos::AMBITO_PLATAFORMA));

        $this->actingAs($actor)->post(route('admin.roles.store'), [
            'name' => 'Diseñadores', 'ambito' => 'plataforma', 'permisos' => ['panel.ver', 'paginas.disenar_negocios'],
        ])->assertForbidden();
        $this->assertNull(Rol::query()->where('name', 'Diseñadores')->first());

        // El superadministrador sí puede crear un rol de diseño para clientes.
        $super = User::factory()->superadministrador()->create();
        $this->actingAs($super)->post(route('admin.roles.store'), [
            'name' => 'Diseñadores', 'ambito' => 'plataforma', 'permisos' => ['panel.ver', 'fidelidad.disenar_negocios', 'paginas.disenar_negocios'],
        ])->assertSessionHasNoErrors();
        $this->assertTrue(Rol::query()->where('name', 'Diseñadores')->firstOrFail()->hasPermissionTo('paginas.disenar_negocios'));
    }

    public function test_un_rol_de_negocio_no_admite_permisos_de_diseno_para_negocios(): void
    {
        $super = User::factory()->superadministrador()->create();

        $this->actingAs($super)->post(route('admin.roles.store'), [
            'name' => 'Encargado', 'ambito' => 'negocio', 'permisos' => ['fidelidad.ver', 'fidelidad.disenar_negocios'],
        ])->assertSessionHasErrors();
    }

    public function test_reembolsar_y_revisar_pagos_son_permisos_separados(): void
    {
        $rol = new Rol;
        $rol->forceFill(['name' => 'Cobranza', 'guard_name' => 'web', 'ambito' => CatalogoPermisos::AMBITO_PLATAFORMA])->save();
        $rol->syncPermissions(['pagos.ver', 'pagos.gestionar', 'servicios.gestionar']);
        $usuario = User::factory()->administrador()->conRol($rol)->create();
        $pago = Pago::query()->create([
            'negocio_id' => Negocio::factory()->create()->id, 'servicio_id' => Servicio::porClave('fidelidad')->id,
            'proveedor' => Pago::PROVEEDOR_OPENPAY, 'estado' => EstadoPago::Pagado, 'importe_centavos' => 1000, 'moneda' => 'MXN',
        ]);

        $this->actingAs($usuario)->post(route('admin.pagos.reembolso', $pago), ['tipo' => 'total', 'motivo' => 'Prueba de permiso'])->assertForbidden();
        $this->actingAs($usuario)->patch(route('admin.pagos.revision', $pago))->assertSessionHasNoErrors();
    }

    public function test_el_rol_administrador_predeterminado_incluye_el_diseno_para_negocios(): void
    {
        $admin = User::factory()->create();

        $this->assertTrue($admin->tienePermiso('fidelidad.disenar_negocios'));
        $this->assertTrue($admin->tienePermiso('paginas.disenar_negocios'));
        $this->assertFalse($admin->tienePermiso('roles.administrar_permisos'));
    }
}
