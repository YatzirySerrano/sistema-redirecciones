<?php

namespace App\Services\Autorizacion;

/**
 * Catálogo de permisos de la plataforma, agrupados por módulo.
 *
 * Los permisos corresponden a funcionalidades que existen en el código, por
 * eso su lista se define aquí; QUÉ rol tiene cada permiso es dinámico y vive
 * en MariaDB (tablas de spatie/laravel-permission), editable desde
 * «Roles y permisos». SincronizadorPermisos crea en la base los permisos de
 * este catálogo.
 *
 * Cada permiso indica los ámbitos de cuenta que pueden recibirlo:
 * - plataforma: personal de DASTI (alcance: todos los negocios).
 * - negocio: usuarios de un cliente (alcance: solo su negocio).
 *
 * Un permiso nunca amplía el alcance: un cliente con «productos.ver» solo ve
 * los productos de su propio negocio (ProductoPolicy y consultas filtradas).
 */
final class CatalogoPermisos
{
    public const AMBITO_PLATAFORMA = 'plataforma';

    public const AMBITO_NEGOCIO = 'negocio';

    public const ROL_SUPERADMINISTRADOR = 'superadministrador';

    public const ROL_ADMINISTRADOR = 'administrador';

    public const ROL_CLIENTE = 'cliente';

    public const ROL_EMPLEADO = 'empleado';

    private const N = [self::AMBITO_NEGOCIO];

    private const P = [self::AMBITO_PLATAFORMA];

    private const PN = [self::AMBITO_PLATAFORMA, self::AMBITO_NEGOCIO];

    /**
     * @var array<string, array{etiqueta: string, permisos: array<string, array{etiqueta: string, descripcion: string, ambitos: list<string>}>}>
     */
    public const MODULOS = [
        'panel' => [
            'etiqueta' => 'Panel',
            'permisos' => [
                'panel.ver' => ['etiqueta' => 'Ver', 'descripcion' => 'Pantalla de inicio con indicadores.', 'ambitos' => self::PN],
            ],
        ],
        'negocios' => [
            'etiqueta' => 'Negocios',
            'permisos' => [
                'negocios.ver' => ['etiqueta' => 'Ver', 'descripcion' => 'Listado y ficha de negocios.', 'ambitos' => self::P],
                'negocios.crear' => ['etiqueta' => 'Crear', 'descripcion' => 'Registrar negocios nuevos.', 'ambitos' => self::P],
                'negocios.editar' => ['etiqueta' => 'Editar', 'descripcion' => 'Datos de contacto y logotipo.', 'ambitos' => self::P],
                'negocios.suspender' => ['etiqueta' => 'Suspender', 'descripcion' => 'Detiene sus enlaces y accesos.', 'ambitos' => self::P],
                'negocios.reactivar' => ['etiqueta' => 'Reactivar', 'descripcion' => 'Reanuda un negocio suspendido.', 'ambitos' => self::P],
            ],
        ],
        'usuarios' => [
            'etiqueta' => 'Usuarios',
            'permisos' => [
                'usuarios.ver' => ['etiqueta' => 'Ver', 'descripcion' => 'Listado de usuarios y accesos.', 'ambitos' => self::P],
                'usuarios.crear' => ['etiqueta' => 'Crear', 'descripcion' => 'Dar de alta accesos.', 'ambitos' => self::P],
                'usuarios.editar' => ['etiqueta' => 'Editar', 'descripcion' => 'Nombre y correo de los usuarios.', 'ambitos' => self::P],
                'usuarios.gestionar_accesos' => ['etiqueta' => 'Gestionar accesos', 'descripcion' => 'Suspender, reactivar, enviar enlaces y contraseñas temporales.', 'ambitos' => self::P],
            ],
        ],
        'productos' => [
            'etiqueta' => 'Productos',
            'permisos' => [
                'productos.ver' => ['etiqueta' => 'Ver', 'descripcion' => 'Productos QR y NFC (los clientes, solo los de su negocio).', 'ambitos' => self::PN],
                'productos.crear' => ['etiqueta' => 'Crear', 'descripcion' => 'Registrar productos con su enlace permanente.', 'ambitos' => self::P],
                'productos.editar' => ['etiqueta' => 'Editar', 'descripcion' => 'Nombre, preparación y datos internos.', 'ambitos' => self::P],
                'productos.suspender' => ['etiqueta' => 'Suspender', 'descripcion' => 'El enlace deja de redirigir.', 'ambitos' => self::P],
                'productos.reactivar' => ['etiqueta' => 'Reactivar', 'descripcion' => 'Reanuda un producto suspendido.', 'ambitos' => self::P],
                'productos.transferir' => ['etiqueta' => 'Transferir', 'descripcion' => 'Cambiar el negocio propietario.', 'ambitos' => self::P],
                'productos.eliminar' => ['etiqueta' => 'Eliminar', 'descripcion' => 'Solo productos sin historial ni accesos.', 'ambitos' => self::P],
            ],
        ],
        'redirecciones' => [
            'etiqueta' => 'Redirecciones',
            'permisos' => [
                'redirecciones.ver' => ['etiqueta' => 'Ver', 'descripcion' => 'Listado global de enlaces y destinos.', 'ambitos' => self::P],
                'redirecciones.modificar_destino' => ['etiqueta' => 'Modificar destino', 'descripcion' => 'Cambiar la URL a la que redirige un producto.', 'ambitos' => self::PN],
            ],
        ],
        'qr' => [
            'etiqueta' => 'Códigos QR',
            'permisos' => [
                'qr.descargar' => ['etiqueta' => 'Ver y descargar', 'descripcion' => 'Vista previa y descarga en PNG o SVG.', 'ambitos' => self::PN],
                'qr.disenar' => ['etiqueta' => 'Diseñar', 'descripcion' => 'Guardar colores, margen y logotipo del QR.', 'ambitos' => self::PN],
            ],
        ],
        'estadisticas' => [
            'etiqueta' => 'Estadísticas',
            'permisos' => [
                'estadisticas.ver' => ['etiqueta' => 'Ver', 'descripcion' => 'Accesos y dispositivos (los clientes, solo de su negocio).', 'ambitos' => self::PN],
            ],
        ],
        'auditoria' => [
            'etiqueta' => 'Auditoría',
            'permisos' => [
                'auditoria.ver' => ['etiqueta' => 'Ver', 'descripcion' => 'Bitácora de operaciones sensibles.', 'ambitos' => self::P],
            ],
        ],
        'configuracion' => [
            'etiqueta' => 'Configuración',
            'permisos' => [
                'configuracion.ver' => ['etiqueta' => 'Ver', 'descripcion' => 'Ajustes de la plataforma.', 'ambitos' => self::P],
                'configuracion.editar' => ['etiqueta' => 'Editar', 'descripcion' => 'Modificar los ajustes de la plataforma.', 'ambitos' => self::P],
            ],
        ],
        'personalizacion' => [
            'etiqueta' => 'Personalización',
            'permisos' => [
                'personalizacion.ver' => ['etiqueta' => 'Ver', 'descripcion' => 'Colores de la interfaz.', 'ambitos' => self::P],
                'personalizacion.editar' => ['etiqueta' => 'Editar', 'descripcion' => 'Cambiar y restablecer los colores.', 'ambitos' => self::P],
            ],
        ],
        'servicios' => [
            'etiqueta' => 'Servicios contratados',
            'permisos' => [
                'servicios.ver' => ['etiqueta' => 'Ver', 'descripcion' => 'Servicios, contrataciones y su historial.', 'ambitos' => self::P],
                'servicios.gestionar' => ['etiqueta' => 'Gestionar', 'descripcion' => 'Activar, suspender, registrar pagos fuera de línea y configurar precio y vigencia.', 'ambitos' => self::P],
                'servicios.contratar' => ['etiqueta' => 'Contratar', 'descripcion' => 'Contratar servicios adicionales con pago en línea.', 'ambitos' => self::N],
            ],
        ],
        'pagos' => [
            'etiqueta' => 'Pagos',
            'permisos' => [
                'pagos.ver' => ['etiqueta' => 'Ver', 'descripcion' => 'Pagos, reembolsos y disputas.', 'ambitos' => self::P],
            ],
        ],
        'fidelidad' => [
            'etiqueta' => 'Fidelidad',
            'permisos' => [
                'fidelidad.ver' => ['etiqueta' => 'Ver', 'descripcion' => 'Resumen, buscar consumidores y consultar su progreso.', 'ambitos' => self::N],
                'fidelidad.visitas' => ['etiqueta' => 'Registrar visitas', 'descripcion' => 'Confirmar visitas o compras válidas.', 'ambitos' => self::N],
                'fidelidad.canjear' => ['etiqueta' => 'Canjear recompensas', 'descripcion' => 'Entregar recompensas disponibles.', 'ambitos' => self::N],
                'fidelidad.consumidores' => ['etiqueta' => 'Administrar consumidores', 'descripcion' => 'Registrar y editar consumidores.', 'ambitos' => self::N],
                'fidelidad.credenciales' => ['etiqueta' => 'Credenciales', 'descripcion' => 'Emitir, reemplazar y revocar tarjetas y enlaces; fusionar duplicados.', 'ambitos' => self::N],
                'fidelidad.ajustes' => ['etiqueta' => 'Ajustes', 'descripcion' => 'Corregir o anular visitas y recompensas con motivo.', 'ambitos' => self::N],
                'fidelidad.campanas' => ['etiqueta' => 'Campañas', 'descripcion' => 'Crear y modificar campañas y recompensas.', 'ambitos' => self::N],
                'fidelidad.personal' => ['etiqueta' => 'Personal', 'descripcion' => 'Administrar empleados del negocio.', 'ambitos' => self::N],
                'fidelidad.auditoria' => ['etiqueta' => 'Auditoría', 'descripcion' => 'Actividad del programa y visitas por empleado.', 'ambitos' => self::N],
            ],
        ],
        'roles' => [
            'etiqueta' => 'Roles y permisos',
            'permisos' => [
                'roles.ver' => ['etiqueta' => 'Ver', 'descripcion' => 'Roles, permisos y usuarios de cada rol.', 'ambitos' => self::P],
                'roles.crear' => ['etiqueta' => 'Crear', 'descripcion' => 'Crear roles nuevos.', 'ambitos' => self::P],
                'roles.editar' => ['etiqueta' => 'Editar', 'descripcion' => 'Nombre y descripción; eliminar roles sin usuarios.', 'ambitos' => self::P],
                'roles.administrar_permisos' => ['etiqueta' => 'Administrar permisos', 'descripcion' => 'Asignar y retirar permisos de los roles.', 'ambitos' => self::P],
                'roles.asignar' => ['etiqueta' => 'Asignar a usuarios', 'descripcion' => 'Cambiar el rol de otros usuarios.', 'ambitos' => self::P],
            ],
        ],
    ];

    /**
     * Permisos de los roles del sistema al crearlos por primera vez. Después
     * se configuran desde el panel (salvo el superadministrador, que siempre
     * tiene todos).
     *
     * @var array<string, array{nombre: string, descripcion: string, ambito: string}>
     */
    public const ROLES_SISTEMA = [
        self::ROL_SUPERADMINISTRADOR => [
            'nombre' => 'Superadministrador',
            'descripcion' => 'Control total y protegido de la plataforma. Siempre tiene todos los permisos.',
            'ambito' => self::AMBITO_PLATAFORMA,
        ],
        self::ROL_ADMINISTRADOR => [
            'nombre' => 'Administrador',
            'descripcion' => 'Personal de DASTI con permisos administrativos configurables.',
            'ambito' => self::AMBITO_PLATAFORMA,
        ],
        self::ROL_CLIENTE => [
            'nombre' => 'Cliente',
            'descripcion' => 'Usuarios de un negocio; solo acceden a los recursos de su negocio.',
            'ambito' => self::AMBITO_NEGOCIO,
        ],
        self::ROL_EMPLEADO => [
            'nombre' => 'Empleado',
            'descripcion' => 'Personal de mostrador de un negocio: busca consumidores, registra visitas y canjea recompensas.',
            'ambito' => self::AMBITO_NEGOCIO,
        ],
    ];

    /**
     * Permisos del rol Empleado al crearse.
     */
    private const EMPLEADO = ['fidelidad.ver', 'fidelidad.visitas', 'fidelidad.canjear'];

    /**
     * Permisos que el rol Administrador NO recibe al crearse: la gestión de
     * roles queda en manos del superadministrador hasta que la delegue.
     */
    private const EXCLUIDOS_ADMINISTRADOR = ['roles.crear', 'roles.editar', 'roles.administrar_permisos', 'roles.asignar'];

    /**
     * @return list<string>
     */
    public static function todos(): array
    {
        $nombres = [];

        foreach (self::MODULOS as $modulo) {
            foreach (array_keys($modulo['permisos']) as $nombre) {
                $nombres[] = $nombre;
            }
        }

        return $nombres;
    }

    public static function existe(string $permiso): bool
    {
        return self::definicion($permiso) !== null;
    }

    /**
     * @return array{etiqueta: string, descripcion: string, ambitos: list<string>}|null
     */
    public static function definicion(string $permiso): ?array
    {
        $modulo = strstr($permiso, '.', true);

        if ($modulo === false || ! isset(self::MODULOS[$modulo])) {
            return null;
        }

        return self::MODULOS[$modulo]['permisos'][$permiso] ?? null;
    }

    public static function admiteAmbito(string $permiso, string $ambito): bool
    {
        return in_array($ambito, self::definicion($permiso)['ambitos'] ?? [], true);
    }

    /**
     * @return list<string>
     */
    public static function delAmbito(string $ambito): array
    {
        return array_values(array_filter(self::todos(), fn (string $p) => self::admiteAmbito($p, $ambito)));
    }

    /**
     * @return list<string>
     */
    public static function predeterminados(string $claveRol): array
    {
        return match ($claveRol) {
            self::ROL_SUPERADMINISTRADOR => self::delAmbito(self::AMBITO_PLATAFORMA),
            self::ROL_ADMINISTRADOR => array_values(array_diff(self::delAmbito(self::AMBITO_PLATAFORMA), self::EXCLUIDOS_ADMINISTRADOR)),
            self::ROL_CLIENTE => self::delAmbito(self::AMBITO_NEGOCIO),
            self::ROL_EMPLEADO => self::EMPLEADO,
            default => [],
        };
    }

    public static function etiquetaAmbito(string $ambito): string
    {
        return $ambito === self::AMBITO_NEGOCIO ? 'Cliente (su negocio)' : 'Personal DASTI (plataforma)';
    }

    /**
     * Matriz para la interfaz: módulos con sus permisos.
     *
     * @return list<array{clave: string, etiqueta: string, permisos: list<array{nombre: string, etiqueta: string, descripcion: string, ambitos: list<string>}>}>
     */
    public static function matriz(): array
    {
        $matriz = [];

        foreach (self::MODULOS as $clave => $modulo) {
            $permisos = [];

            foreach ($modulo['permisos'] as $nombre => $definicion) {
                $permisos[] = ['nombre' => $nombre, ...$definicion];
            }

            $matriz[] = ['clave' => $clave, 'etiqueta' => $modulo['etiqueta'], 'permisos' => $permisos];
        }

        return $matriz;
    }

    public static function etiquetaCompleta(string $permiso): string
    {
        $modulo = strstr($permiso, '.', true);
        $definicion = self::definicion($permiso);

        if ($modulo === false || $definicion === null) {
            return $permiso;
        }

        return self::MODULOS[$modulo]['etiqueta'].' · '.$definicion['etiqueta'];
    }
}
