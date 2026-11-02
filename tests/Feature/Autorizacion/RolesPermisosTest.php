<?php

namespace Tests\Feature\Autorizacion;

use App\Actions\Usuarios\AsignarRol;
use App\Actions\Usuarios\CambiarEstadoUsuario;
use App\Enums\EstadoCuenta;
use App\Models\Negocio;
use App\Models\Producto;
use App\Models\RegistroAuditoria;
use App\Models\Rol;
use App\Models\User;
use App\Services\Autorizacion\CatalogoPermisos;
use App\Services\Autorizacion\SincronizadorPermisos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RolesPermisosTest extends TestCase
{
    use RefreshDatabase;

    private function rol(string $clave): Rol
    {
        return Rol::porClave($clave) ?? throw new \RuntimeException("Falta el rol {$clave}");
    }

    /**
     * @param  list<string>  $permisos
     */
    private function rolPersonalizado(string $nombre, array $permisos, string $ambito = CatalogoPermisos::AMBITO_PLATAFORMA): Rol
    {
        $rol = new Rol;
        $rol->forceFill(['name' => $nombre, 'guard_name' => 'web', 'ambito' => $ambito])->save();
        $rol->syncPermissions($permisos);

        return $rol;
    }

    // ---- Roles iniciales y migración ----

    public function test_existen_los_roles_iniciales_con_sus_permisos(): void
    {
        $super = $this->rol(CatalogoPermisos::ROL_SUPERADMINISTRADOR);
        $admin = $this->rol(CatalogoPermisos::ROL_ADMINISTRADOR);
        $cliente = $this->rol(CatalogoPermisos::ROL_CLIENTE);

        $this->assertEqualsCanonicalizing(CatalogoPermisos::delAmbito('plataforma'), $super->nombresPermisos());
        $this->assertNotContains('roles.administrar_permisos', $admin->nombresPermisos());
        $this->assertContains('negocios.crear', $admin->nombresPermisos());
        $this->assertSame('negocio', $cliente->ambito);
        $this->assertEqualsCanonicalizing(CatalogoPermisos::delAmbito('negocio'), $cliente->nombresPermisos());
    }

    public function test_la_migracion_conserva_el_acceso_de_los_usuarios_existentes(): void
    {
        // Usuarios "previos" sin rol (como antes de la migración).
        $negocio = Negocio::factory()->create();
        DB::table('users')->insert([
            ['name' => 'Admin previo', 'email' => 'previo@dasti.test', 'password' => 'x', 'rol' => 'admin', 'estado' => 'activo', 'negocio_id' => null, 'debe_cambiar_password' => false, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Cliente previo', 'email' => 'cliente-previo@x.test', 'password' => 'y', 'rol' => 'cliente', 'estado' => 'activo', 'negocio_id' => $negocio->id, 'debe_cambiar_password' => false, 'created_at' => now(), 'updated_at' => now()],
        ]);
        $hashAntes = DB::table('users')->where('email', 'previo@dasti.test')->value('password');

        $migracion = require database_path('migrations/2026_11_02_100100_asignar_roles_a_usuarios_existentes.php');
        $migracion->up();
        $migracion->up(); // repetible sin duplicar

        $admin = User::query()->where('email', 'previo@dasti.test')->firstOrFail();
        $cliente = User::query()->where('email', 'cliente-previo@x.test')->firstOrFail();

        $this->assertTrue($admin->esSuperadministrador());
        $this->assertSame(1, $admin->roles()->count());
        $this->assertSame('cliente', $cliente->rolAsignado()?->clave);
        $this->assertSame($hashAntes, DB::table('users')->where('email', 'previo@dasti.test')->value('password'));
        $this->assertTrue($admin->can('roles.administrar_permisos'));
        $this->assertFalse($cliente->can('negocios.ver'));
    }

    public function test_la_sincronizacion_es_idempotente_y_respeta_lo_configurado(): void
    {
        $admin = $this->rol(CatalogoPermisos::ROL_ADMINISTRADOR);
        $admin->revokePermissionTo('negocios.crear');

        app(SincronizadorPermisos::class)->sincronizar();

        $this->assertNotContains('negocios.crear', $admin->fresh()?->load('permissions')->nombresPermisos());
        $this->assertSame(3, Rol::query()->whereNotNull('clave')->count());
    }

    // ---- Módulo de roles ----

    public function test_el_superadministrador_crea_un_rol_con_permisos_y_queda_auditado(): void
    {
        $super = User::factory()->superadministrador()->create();

        $this->actingAs($super)->post(route('admin.roles.store'), [
            'name' => 'Soporte',
            'descripcion' => 'Atiende a clientes',
            'ambito' => 'plataforma',
            'permisos' => ['negocios.ver', 'productos.ver'],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $rol = Rol::query()->where('name', 'Soporte')->firstOrFail();
        $this->assertEqualsCanonicalizing(['negocios.ver', 'productos.ver'], $rol->load('permissions')->nombresPermisos());
        $this->assertDatabaseHas('registros_auditoria', ['accion' => 'rol.creado', 'auditable_type' => 'rol', 'auditable_id' => $rol->id]);
    }

    public function test_editar_rol_asigna_y_retira_permisos_y_audita_la_diferencia(): void
    {
        $super = User::factory()->superadministrador()->create();
        $rol = $this->rolPersonalizado('Operador', ['negocios.ver', 'productos.ver']);

        $this->actingAs($super)->put(route('admin.roles.update', $rol), [
            'name' => 'Operador DASTI',
            'descripcion' => null,
            'permisos' => ['productos.ver', 'productos.crear'],
        ])->assertSessionHasNoErrors();

        $rol->refresh()->load('permissions');
        $this->assertSame('Operador DASTI', $rol->name);
        $this->assertEqualsCanonicalizing(['productos.ver', 'productos.crear'], $rol->nombresPermisos());

        $registro = RegistroAuditoria::query()->where('accion', 'rol.actualizado')->latest('id')->firstOrFail();
        $this->assertSame(['productos.crear'], $registro->valores_nuevos['permisos_agregados'] ?? null);
        $this->assertSame(['negocios.ver'], $registro->valores_anteriores['permisos_retirados'] ?? null);
    }

    public function test_los_cambios_de_permisos_se_aplican_de_inmediato_a_los_usuarios(): void
    {
        $super = User::factory()->superadministrador()->create();
        $rol = $this->rolPersonalizado('Consulta', ['negocios.ver']);
        $usuario = User::factory()->conRol($rol)->create();

        $this->actingAs($usuario)->get(route('admin.negocios.index'))->assertOk();
        $this->actingAs($usuario)->get(route('admin.productos.index'))->assertForbidden();

        $this->actingAs($super)->put(route('admin.roles.update', $rol), ['name' => 'Consulta', 'permisos' => ['productos.ver']]);

        $usuario = $usuario->fresh();
        $this->actingAs($usuario)->get(route('admin.negocios.index'))->assertForbidden();
        $this->actingAs($usuario)->get(route('admin.productos.index'))->assertOk();
    }

    public function test_el_administrador_comun_no_gestiona_roles_por_defecto(): void
    {
        $admin = User::factory()->create(); // rol Administrador

        $this->actingAs($admin)->get(route('admin.roles.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.roles.create'))->assertForbidden();
        $this->actingAs($admin)->post(route('admin.roles.store'), ['name' => 'X', 'ambito' => 'plataforma', 'permisos' => []])->assertForbidden();
        $this->assertDatabaseMissing('roles', ['name' => 'X']);
    }

    public function test_nadie_otorga_permisos_que_no_tiene(): void
    {
        $gestor = $this->rolPersonalizado('Gestor de roles', ['roles.ver', 'roles.crear', 'roles.editar', 'roles.administrar_permisos', 'negocios.ver']);
        $usuario = User::factory()->conRol($gestor)->create();

        // Solicitud manipulada: incluye un permiso que el gestor no tiene.
        $this->actingAs($usuario)->post(route('admin.roles.store'), [
            'name' => 'Escalado',
            'ambito' => 'plataforma',
            'permisos' => ['negocios.ver', 'configuracion.editar'],
        ])->assertForbidden();
        $this->assertDatabaseMissing('roles', ['name' => 'Escalado']);

        // Con permisos propios sí puede.
        $this->actingAs($usuario)->post(route('admin.roles.store'), [
            'name' => 'Lectura',
            'ambito' => 'plataforma',
            'permisos' => ['negocios.ver'],
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('roles', ['name' => 'Lectura']);
    }

    public function test_nadie_modifica_los_permisos_de_su_propio_rol(): void
    {
        $gestor = $this->rolPersonalizado('Gestor', ['roles.ver', 'roles.editar', 'roles.administrar_permisos', 'negocios.ver', 'productos.ver']);
        $usuario = User::factory()->conRol($gestor)->create();

        $this->actingAs($usuario)->put(route('admin.roles.update', $gestor), [
            'name' => 'Gestor',
            'permisos' => ['roles.ver', 'roles.editar', 'roles.administrar_permisos', 'negocios.ver'],
        ])->assertForbidden();

        $this->assertContains('productos.ver', $gestor->fresh()?->load('permissions')->nombresPermisos());
    }

    public function test_un_rol_de_cliente_no_admite_permisos_de_plataforma(): void
    {
        $super = User::factory()->superadministrador()->create();

        $this->actingAs($super)->post(route('admin.roles.store'), [
            'name' => 'Cliente avanzado',
            'ambito' => 'negocio',
            'permisos' => ['productos.ver', 'negocios.ver'],
        ])->assertSessionHasErrors('permisos');

        $this->assertDatabaseMissing('roles', ['name' => 'Cliente avanzado']);
    }

    public function test_el_rol_superadministrador_esta_protegido(): void
    {
        $super = $this->rol(CatalogoPermisos::ROL_SUPERADMINISTRADOR);
        $gestor = $this->rolPersonalizado('Gestor', CatalogoPermisos::delAmbito('plataforma'));
        $usuario = User::factory()->conRol($gestor)->create();

        // Ni con todos los permisos se modifica sin ser superadministrador.
        $this->actingAs($usuario)->put(route('admin.roles.update', $super), ['name' => 'Superadministrador', 'descripcion' => 'x', 'permisos' => []])->assertForbidden();

        // El propio superadministrador no puede retirarle permisos.
        $root = User::factory()->superadministrador()->create();
        $this->actingAs($root)->put(route('admin.roles.update', $super), ['name' => 'Otro', 'permisos' => ['panel.ver']])->assertSessionHasNoErrors();
        $super->refresh()->load('permissions');
        $this->assertSame('Superadministrador', $super->name);
        $this->assertEqualsCanonicalizing(CatalogoPermisos::delAmbito('plataforma'), $super->nombresPermisos());

        // Los roles del sistema no se eliminan.
        $this->actingAs($root)->delete(route('admin.roles.destroy', $super))->assertSessionHasErrors('rol');
        $this->assertNotNull(Rol::porClave(CatalogoPermisos::ROL_SUPERADMINISTRADOR));
    }

    public function test_eliminar_un_rol_personalizado_solo_sin_usuarios(): void
    {
        $root = User::factory()->superadministrador()->create();
        $conUsuarios = $this->rolPersonalizado('Con usuarios', ['negocios.ver']);
        User::factory()->conRol($conUsuarios)->create();
        $vacio = $this->rolPersonalizado('Vacío', ['negocios.ver']);

        $this->actingAs($root)->delete(route('admin.roles.destroy', $conUsuarios))->assertSessionHasErrors('rol');
        $this->actingAs($root)->delete(route('admin.roles.destroy', $vacio))->assertRedirect(route('admin.roles.index'));

        $this->assertDatabaseHas('roles', ['id' => $conUsuarios->id]);
        $this->assertDatabaseMissing('roles', ['id' => $vacio->id]);
        $this->assertDatabaseHas('registros_auditoria', ['accion' => 'rol.eliminado']);
    }

    public function test_detalle_del_rol_muestra_permisos_usuarios_e_historial(): void
    {
        $root = User::factory()->superadministrador()->create();
        $rol = $this->rolPersonalizado('Supervisor', ['negocios.ver']);
        $miembro = User::factory()->conRol($rol)->create(['name' => 'Ana Supervisora']);

        $this->actingAs($root)->put(route('admin.roles.update', $rol), ['name' => 'Supervisor', 'permisos' => ['negocios.ver', 'productos.ver']]);

        $this->actingAs($root)->get(route('admin.roles.show', $rol))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/roles/Show')
                ->where('rol.data.permisos_count', 2)
                ->where('usuarios.data.0.id', $miembro->id)
                ->where('historial.data.0.accion', 'rol.actualizado'));
    }

    // ---- Asignación de roles a usuarios ----

    public function test_asignar_rol_a_un_usuario_y_auditarlo(): void
    {
        $root = User::factory()->superadministrador()->create();
        $rol = $this->rolPersonalizado('Soporte', ['negocios.ver']);
        $usuario = User::factory()->create();

        $this->actingAs($root)->put(route('admin.usuarios.rol', $usuario), ['rol_id' => $rol->id])->assertSessionHasNoErrors();

        $this->assertTrue($usuario->fresh()?->rolAsignado()?->is($rol));
        $this->assertDatabaseHas('registros_auditoria', ['accion' => 'usuario.rol_cambiado', 'auditable_id' => $usuario->id]);
    }

    public function test_un_cliente_nunca_recibe_un_rol_de_plataforma(): void
    {
        $root = User::factory()->superadministrador()->create();
        $cliente = User::factory()->cliente()->create();

        $this->actingAs($root)->put(route('admin.usuarios.rol', $cliente), ['rol_id' => $this->rol('administrador')->id])
            ->assertSessionHasErrors('rol_id');

        $this->assertSame('cliente', $cliente->fresh()?->rolAsignado()?->clave);
    }

    public function test_un_administrador_no_puede_convertirse_ni_convertir_a_otro_en_superadministrador(): void
    {
        $asignador = $this->rolPersonalizado('Asignador', ['usuarios.ver', 'roles.ver', 'roles.asignar']);
        $actor = User::factory()->conRol($asignador)->create();
        $otro = User::factory()->conRol($this->rolPersonalizado('Básico', ['usuarios.ver']))->create();
        $super = $this->rol(CatalogoPermisos::ROL_SUPERADMINISTRADOR);

        $this->actingAs($actor)->put(route('admin.usuarios.rol', $actor), ['rol_id' => $super->id])->assertForbidden();
        $this->actingAs($actor)->put(route('admin.usuarios.rol', $otro), ['rol_id' => $super->id])->assertForbidden();
        // Tampoco un rol con más permisos que los suyos (Administrador).
        $this->actingAs($actor)->put(route('admin.usuarios.rol', $otro), ['rol_id' => $this->rol('administrador')->id])->assertForbidden();

        $this->assertFalse($actor->fresh()?->esSuperadministrador());
        $this->assertFalse($otro->fresh()?->esSuperadministrador());
    }

    public function test_crear_usuario_con_un_rol_superior_es_rechazado(): void
    {
        $alta = $this->rolPersonalizado('Altas', ['usuarios.ver', 'usuarios.crear']);
        $actor = User::factory()->conRol($alta)->create();

        $this->actingAs($actor)->post(route('admin.usuarios.store'), [
            'name' => 'Intruso', 'email' => 'intruso@dasti.test', 'rol' => 'admin',
            'rol_id' => $this->rol(CatalogoPermisos::ROL_SUPERADMINISTRADOR)->id,
            'metodo' => 'temporal', 'password' => 'Temporal-123',
        ])->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'intruso@dasti.test']);
    }

    public function test_personal_con_menos_permisos_no_restablece_la_contrasena_de_un_superadministrador(): void
    {
        $gestor = $this->rolPersonalizado('Accesos', ['usuarios.ver', 'usuarios.gestionar_accesos']);
        $actor = User::factory()->conRol($gestor)->create();
        $root = User::factory()->superadministrador()->create();
        $hash = $root->password;

        $this->actingAs($actor)->put(route('admin.usuarios.contrasena', $root), ['password' => 'Temporal-12345'])->assertForbidden();
        $this->actingAs($actor)->patch(route('admin.usuarios.estado', $root), ['estado' => 'suspendido'])->assertForbidden();

        $this->assertSame($hash, $root->fresh()?->password);
        $this->assertSame(EstadoCuenta::Activo, $root->fresh()?->estado);
    }

    public function test_siempre_queda_al_menos_un_superadministrador_activo(): void
    {
        $a = User::factory()->superadministrador()->create();
        $b = User::factory()->superadministrador()->create();

        // Con dos activos, uno puede cambiar el rol del otro.
        $this->actingAs($a)->put(route('admin.usuarios.rol', $b), ['rol_id' => $this->rol('administrador')->id])->assertSessionHasNoErrors();
        $this->assertFalse($b->fresh()?->esSuperadministrador());

        // $a es ahora el último superadministrador activo (uno suspendido no cuenta).
        $suspendido = User::factory()->superadministrador()->suspendido()->create();

        try {
            app(CambiarEstadoUsuario::class)->ejecutar($a->fresh(), EstadoCuenta::Suspendido, $suspendido);
            $this->fail('Se suspendió al último superadministrador.');
        } catch (ValidationException $e) {
            $this->assertSame('Debe existir al menos un superadministrador activo.', $e->errors()['usuario'][0]);
        }

        try {
            app(AsignarRol::class)->ejecutar($suspendido, $a->fresh(), $this->rol('administrador'));
            $this->fail('Se retiró el rol al último superadministrador.');
        } catch (ValidationException $e) {
            $this->assertSame('Debe existir al menos un superadministrador activo.', $e->errors()['rol_id'][0]);
        }

        // Y nadie puede quitarse a sí mismo el rol.
        $this->actingAs($a)->put(route('admin.usuarios.rol', $a), ['rol_id' => $this->rol('administrador')->id])->assertForbidden();

        $this->assertTrue($a->fresh()?->esSuperadministrador());
        $this->assertSame(EstadoCuenta::Activo, $a->fresh()?->estado);
    }

    // ---- Permisos en rutas y alcance multiempresa ----

    public function test_las_rutas_exigen_su_permiso_en_el_servidor(): void
    {
        $rol = $this->rolPersonalizado('Solo productos', ['productos.ver']);
        $usuario = User::factory()->conRol($rol)->create();
        $producto = Producto::factory()->create();

        $this->actingAs($usuario)->get(route('admin.productos.show', $producto))->assertOk();
        $this->actingAs($usuario)->get(route('admin.productos.edit', $producto))->assertForbidden();
        $this->actingAs($usuario)->put(route('admin.productos.destino', $producto), ['url_destino' => 'https://ejemplo.mx'])->assertForbidden();
        $this->actingAs($usuario)->patch(route('admin.productos.estado', $producto), ['estado' => 'suspendido'])->assertForbidden();
        $this->actingAs($usuario)->post(route('admin.productos.transferir', $producto), ['negocio_id' => Negocio::factory()->create()->id, 'motivo' => 'Prueba de permisos'])->assertForbidden();
        $this->actingAs($usuario)->get(route('admin.configuracion.edit'))->assertForbidden();
        $this->actingAs($usuario)->get(route('admin.panel'))->assertForbidden();

        $this->assertSame($producto->estado, $producto->fresh()?->estado);
    }

    public function test_suspender_y_reactivar_son_permisos_distintos(): void
    {
        $rol = $this->rolPersonalizado('Suspende', ['negocios.ver', 'negocios.suspender']);
        $usuario = User::factory()->conRol($rol)->create();
        $negocio = Negocio::factory()->create();

        $this->actingAs($usuario)->patch(route('admin.negocios.estado', $negocio), ['estado' => 'suspendido'])->assertSessionHasNoErrors();
        $this->actingAs($usuario)->patch(route('admin.negocios.estado', $negocio), ['estado' => 'activo'])->assertForbidden();

        $this->assertSame(EstadoCuenta::Suspendido, $negocio->fresh()?->estado);
    }

    public function test_el_inicio_lleva_a_la_primera_seccion_permitida(): void
    {
        $rol = $this->rolPersonalizado('Solo auditoría', ['auditoria.ver']);
        $usuario = User::factory()->conRol($rol)->create();

        $this->actingAs($usuario)->get(route('inicio'))->assertRedirect(route('admin.auditoria.index'));
    }

    public function test_los_permisos_compartidos_con_la_interfaz_coinciden_con_el_rol(): void
    {
        $rol = $this->rolPersonalizado('Solo negocios', ['negocios.ver']);
        $usuario = User::factory()->conRol($rol)->create();

        $this->actingAs($usuario)->get(route('admin.negocios.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.permisos.lista', ['negocios.ver'])
                ->where('auth.permisos.superadministrador', false)
                ->where('auth.user.rol_nombre', 'Solo negocios'));
    }

    public function test_un_permiso_nunca_amplia_el_alcance_de_un_cliente(): void
    {
        $rolCliente = $this->rolPersonalizado('Cliente total', CatalogoPermisos::delAmbito('negocio'), 'negocio');
        $cliente = User::factory()->cliente()->conRol($rolCliente)->create();
        $ajeno = Producto::factory()->create();

        $this->actingAs($cliente)->get(route('cliente.productos.show', $ajeno))->assertNotFound();
        $this->actingAs($cliente)->put(route('cliente.productos.destino', $ajeno), ['url_destino' => 'https://ejemplo.mx'])->assertNotFound();
        $this->actingAs($cliente)->get(route('productos.qr.show', $ajeno))->assertNotFound();
        $this->actingAs($cliente)->get(route('admin.productos.index'))->assertForbidden();
        $this->actingAs($cliente)->get(route('admin.opciones.negocios'))->assertForbidden();
    }

    public function test_un_rol_de_plataforma_asignado_por_error_a_un_cliente_no_le_da_permisos(): void
    {
        $cliente = User::factory()->cliente()->create();
        // Manipulación directa de la base, fuera de las reglas de la aplicación.
        $cliente->syncRoles([$this->rol(CatalogoPermisos::ROL_SUPERADMINISTRADOR)]);
        $cliente->olvidarPermisos();

        $this->assertSame([], $cliente->permisosEfectivos());
        $this->assertFalse($cliente->esSuperadministrador());
        $this->actingAs($cliente)->get(route('admin.negocios.index'))->assertForbidden();
    }

    public function test_cliente_sin_permiso_de_modificar_destino_no_puede_cambiarlo(): void
    {
        $rolLectura = $this->rolPersonalizado('Cliente lectura', ['panel.ver', 'productos.ver'], 'negocio');
        $cliente = User::factory()->cliente()->conRol($rolLectura)->create();
        $producto = Producto::factory()->for($cliente->negocio)->create(['url_destino' => 'https://antes.mx']);

        $this->actingAs($cliente)->get(route('cliente.productos.show', $producto))->assertOk();
        $this->actingAs($cliente)->put(route('cliente.productos.destino', $producto), ['url_destino' => 'https://despues.mx'])->assertForbidden();
        $this->assertSame('https://antes.mx', $producto->fresh()?->url_destino);
    }
}
