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
     * Módulos del catálogo. `descripcion` explica el módulo en la matriz;
     * `seccion` (opcional) agrupa permisos relacionados dentro de un módulo
     * grande (p. ej. Fidelidad: Mostrador, Consumidores, Campañas, Diseño).
     *
     * @var array<string, array{etiqueta: string, descripcion: string, permisos: array<string, array{etiqueta: string, descripcion: string, ambitos: list<string>, seccion?: string}>}>
     */
    public const MODULOS = [
        'panel' => [
            'etiqueta' => 'Panel de inicio',
            'descripcion' => 'Pantalla principal con indicadores.',
            'permisos' => [
                'panel.ver' => ['etiqueta' => 'Ver', 'descripcion' => 'Pantalla de inicio con indicadores (los clientes, solo de su negocio).', 'ambitos' => self::PN],
            ],
        ],
        'negocios' => [
            'etiqueta' => 'Negocios',
            'descripcion' => 'Clientes de DASTI y sus datos de contacto.',
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
            'descripcion' => 'Cuentas de acceso del personal DASTI y de los clientes.',
            'permisos' => [
                'usuarios.ver' => ['etiqueta' => 'Ver', 'descripcion' => 'Listado de usuarios y accesos.', 'ambitos' => self::P],
                'usuarios.crear' => ['etiqueta' => 'Crear', 'descripcion' => 'Dar de alta accesos.', 'ambitos' => self::P],
                'usuarios.editar' => ['etiqueta' => 'Editar', 'descripcion' => 'Nombre y correo de los usuarios.', 'ambitos' => self::P],
                'usuarios.gestionar_accesos' => ['etiqueta' => 'Gestionar accesos', 'descripcion' => 'Suspender, reactivar, enviar enlaces y contraseñas temporales.', 'ambitos' => self::P],
            ],
        ],
        'roles' => [
            'etiqueta' => 'Roles y permisos',
            'descripcion' => 'Qué puede hacer cada tipo de usuario. Nadie otorga permisos que no tiene.',
            'permisos' => [
                'roles.ver' => ['etiqueta' => 'Ver', 'descripcion' => 'Roles, permisos y usuarios de cada rol.', 'ambitos' => self::P],
                'roles.crear' => ['etiqueta' => 'Crear', 'descripcion' => 'Crear roles nuevos.', 'ambitos' => self::P],
                'roles.editar' => ['etiqueta' => 'Editar', 'descripcion' => 'Nombre y descripción; eliminar roles sin usuarios.', 'ambitos' => self::P],
                'roles.administrar_permisos' => ['etiqueta' => 'Administrar permisos', 'descripcion' => 'Asignar y retirar permisos de los roles (solo los que uno mismo tiene).', 'ambitos' => self::P],
                'roles.asignar' => ['etiqueta' => 'Asignar a usuarios', 'descripcion' => 'Cambiar el rol de otros usuarios (sin superar los permisos propios).', 'ambitos' => self::P],
            ],
        ],
        'productos' => [
            'etiqueta' => 'Productos QR/NFC',
            'descripcion' => 'Tarjetas, etiquetas y códigos con enlace permanente.',
            'permisos' => [
                'productos.ver' => ['etiqueta' => 'Ver', 'descripcion' => 'Productos QR y NFC (los clientes, solo los de su negocio).', 'ambitos' => self::PN],
                'productos.crear' => ['etiqueta' => 'Crear', 'descripcion' => 'Registrar productos con su enlace permanente.', 'ambitos' => self::P],
                'productos.editar' => ['etiqueta' => 'Editar', 'descripcion' => 'Nombre, preparación y datos internos.', 'ambitos' => self::P],
                'productos.suspender' => ['etiqueta' => 'Suspender', 'descripcion' => 'El enlace deja de redirigir.', 'ambitos' => self::P],
                'productos.reactivar' => ['etiqueta' => 'Reactivar', 'descripcion' => 'Reanuda un producto suspendido.', 'ambitos' => self::P],
                'productos.transferir' => ['etiqueta' => 'Transferir', 'descripcion' => 'Cambiar el negocio propietario.', 'ambitos' => self::P],
                'productos.eliminar' => ['etiqueta' => 'Eliminar', 'descripcion' => 'Solo productos sin historial ni accesos.', 'ambitos' => self::P],
                'productos.editar_nombre' => ['etiqueta' => 'Editar el nombre', 'descripcion' => 'Cambiar solo el nombre descriptivo de sus productos (p. ej. «Mostrador principal»). No modifica el enlace, el código QR, los destinos ni ningún otro dato.', 'ambitos' => self::N],
                'productos.cambiar_cantidad_qr' => ['etiqueta' => 'Ampliar o reducir los QR del producto', 'descripcion' => 'Cambiar un producto QR o QR + NFC entre 1 y 2 QR impresos. Al reducir, el segundo QR se archiva (deja de redirigir) sin perder su historial.', 'ambitos' => self::PN],
            ],
        ],
        'redirecciones' => [
            'etiqueta' => 'Redirecciones',
            'descripcion' => 'Página que se abre al escanear el QR o acercar la tarjeta NFC.',
            'permisos' => [
                'redirecciones.ver' => ['etiqueta' => 'Ver', 'descripcion' => 'Listado global de enlaces y destinos.', 'ambitos' => self::P],
                'redirecciones.modificar_destino' => ['etiqueta' => 'Modificar destino', 'descripcion' => 'Cambiar la página a la que lleva un producto.', 'ambitos' => self::PN],
            ],
        ],
        'qr' => [
            'etiqueta' => 'Códigos QR',
            'descripcion' => 'Diseño y descarga de los códigos QR de los productos.',
            'permisos' => [
                'qr.descargar' => ['etiqueta' => 'Ver y descargar', 'descripcion' => 'Vista previa y descarga en PNG o SVG.', 'ambitos' => self::PN],
                'qr.disenar' => ['etiqueta' => 'Diseñar', 'descripcion' => 'Guardar colores, margen y logotipo del QR.', 'ambitos' => self::PN],
            ],
        ],
        'estadisticas' => [
            'etiqueta' => 'Estadísticas',
            'descripcion' => 'Accesos a los enlaces QR/NFC.',
            'permisos' => [
                'estadisticas.ver' => ['etiqueta' => 'Consultar estadísticas', 'descripcion' => 'Accesos y dispositivos (los clientes, solo de su negocio).', 'ambitos' => self::PN],
            ],
        ],
        'auditoria' => [
            'etiqueta' => 'Auditoría',
            'descripcion' => 'Bitácora de operaciones de la plataforma.',
            'permisos' => [
                'auditoria.ver' => ['etiqueta' => 'Ver', 'descripcion' => 'Bitácora de operaciones sensibles.', 'ambitos' => self::P],
            ],
        ],
        'configuracion' => [
            'etiqueta' => 'Configuración',
            'descripcion' => 'Ajustes generales de la plataforma.',
            'permisos' => [
                'configuracion.ver' => ['etiqueta' => 'Ver', 'descripcion' => 'Ajustes de la plataforma.', 'ambitos' => self::P],
                'configuracion.editar' => ['etiqueta' => 'Editar', 'descripcion' => 'Modificar los ajustes de la plataforma.', 'ambitos' => self::P],
            ],
        ],
        'personalizacion' => [
            'etiqueta' => 'Apariencia',
            'descripcion' => 'Colores de la interfaz del panel.',
            'permisos' => [
                'personalizacion.ver' => ['etiqueta' => 'Ver', 'descripcion' => 'Colores de la interfaz.', 'ambitos' => self::P],
                'personalizacion.editar' => ['etiqueta' => 'Editar', 'descripcion' => 'Cambiar y restablecer los colores.', 'ambitos' => self::P],
            ],
        ],
        'servicios' => [
            'etiqueta' => 'Servicios',
            'descripcion' => 'Servicios adicionales (Fidelidad, Página de enlaces) y su contratación. Un permiso nunca sustituye la contratación.',
            'permisos' => [
                'servicios.ver' => ['etiqueta' => 'Ver', 'descripcion' => 'Personal DASTI: servicios, contrataciones y su historial. Clientes: el apartado «Mis servicios» (catálogo, estado y contratación); sin él no lo ven ni pueden abrirlo, pero sus productos QR/NFC siguen funcionando.', 'ambitos' => self::PN],
                'servicios.gestionar' => ['etiqueta' => 'Administrar', 'descripcion' => 'Activar, suspender, reactivar y configurar precio y vigencia.', 'ambitos' => self::P],
                'servicios.contratar' => ['etiqueta' => 'Contratar', 'descripcion' => 'Contratar servicios adicionales con pago en línea. Requiere también «Ver».', 'ambitos' => self::N],
            ],
        ],
        'pagos' => [
            'etiqueta' => 'Pagos',
            'descripcion' => 'Órdenes de pago en línea (Openpay), reembolsos y disputas.',
            'permisos' => [
                'pagos.ver' => ['etiqueta' => 'Ver', 'descripcion' => 'Pagos, reembolsos y disputas.', 'ambitos' => self::P],
                'pagos.gestionar' => ['etiqueta' => 'Revisar incidencias', 'descripcion' => 'Consultar el estado en Openpay y marcar incidencias como revisadas.', 'ambitos' => self::P],
                'pagos.reembolsar' => ['etiqueta' => 'Reembolsar', 'descripcion' => 'Enviar reembolsos totales o parciales a Openpay.', 'ambitos' => self::P],
            ],
        ],
        'fidelidad' => [
            'etiqueta' => 'Fidelidad',
            'descripcion' => 'Programa de visitas y recompensas del negocio. Requiere el servicio contratado.',
            'permisos' => [
                'fidelidad.ver' => ['etiqueta' => 'Ver', 'descripcion' => 'Resumen, buscar consumidores y consultar su progreso.', 'ambitos' => self::N, 'seccion' => 'General'],
                'fidelidad.visitas' => ['etiqueta' => 'Registrar visitas', 'descripcion' => 'Confirmar visitas o compras válidas en el mostrador.', 'ambitos' => self::N, 'seccion' => 'Mostrador'],
                'fidelidad.canjear' => ['etiqueta' => 'Canjear recompensas', 'descripcion' => 'Entregar recompensas disponibles.', 'ambitos' => self::N, 'seccion' => 'Mostrador'],
                'fidelidad.consumidores' => ['etiqueta' => 'Administrar consumidores', 'descripcion' => 'Registrar y editar consumidores.', 'ambitos' => self::N, 'seccion' => 'Consumidores'],
                'fidelidad.credenciales' => ['etiqueta' => 'Credenciales', 'descripcion' => 'Emitir, reemplazar y revocar tarjetas y enlaces; fusionar duplicados.', 'ambitos' => self::N, 'seccion' => 'Consumidores'],
                'fidelidad.ajustes' => ['etiqueta' => 'Ajustes de visitas', 'descripcion' => 'Corregir o anular visitas y recompensas (siempre con motivo).', 'ambitos' => self::N, 'seccion' => 'Consumidores'],
                'fidelidad.campanas' => ['etiqueta' => 'Campañas', 'descripcion' => 'Crear y modificar campañas, metas y recompensas.', 'ambitos' => self::N, 'seccion' => 'Campañas'],
                'fidelidad.diseno' => ['etiqueta' => 'Diseñar el programa', 'descripcion' => 'Personalizar y publicar la página que ven los consumidores.', 'ambitos' => self::N, 'seccion' => 'Diseño'],
                'fidelidad.disenar_negocios' => ['etiqueta' => 'Diseñar para negocios', 'descripcion' => 'Personal DASTI: abrir el diseñador de Fidelidad de un negocio y guardar o publicar su diseño.', 'ambitos' => self::P, 'seccion' => 'Diseño'],
                'fidelidad.personal' => ['etiqueta' => 'Personal autorizado', 'descripcion' => 'Administrar empleados del negocio.', 'ambitos' => self::N, 'seccion' => 'Administración'],
                'fidelidad.auditoria' => ['etiqueta' => 'Historial y actividad', 'descripcion' => 'Actividad del programa y visitas por empleado.', 'ambitos' => self::N, 'seccion' => 'Administración'],
            ],
        ],
        'paginas' => [
            'etiqueta' => 'Página de enlaces',
            'descripcion' => 'Página pública del negocio con sus enlaces. Requiere el servicio contratado.',
            'permisos' => [
                'paginas.ver' => ['etiqueta' => 'Ver', 'descripcion' => 'Consultar la página y su dirección pública.', 'ambitos' => self::N, 'seccion' => 'General'],
                'paginas.editar' => ['etiqueta' => 'Editar', 'descripcion' => 'Editor visual: diseño, plantillas, contenido, imágenes y borradores.', 'ambitos' => self::N, 'seccion' => 'Editor'],
                'paginas.publicar' => ['etiqueta' => 'Publicar', 'descripcion' => 'Publicar, despublicar y cambiar la dirección pública.', 'ambitos' => self::N, 'seccion' => 'Editor'],
                'paginas.disenar_negocios' => ['etiqueta' => 'Diseñar para negocios', 'descripcion' => 'Personal DASTI: editar, guardar y publicar la página de un negocio.', 'ambitos' => self::P, 'seccion' => 'Editor'],
                'paginas.estadisticas' => ['etiqueta' => 'Consultar estadísticas', 'descripcion' => 'Visitas y clics de la página; exportarlos.', 'ambitos' => self::N, 'seccion' => 'Estadísticas'],
                'paginas.mensajes' => ['etiqueta' => 'Mensajes', 'descripcion' => 'Leer los mensajes enviados desde el formulario de contacto.', 'ambitos' => self::N, 'seccion' => 'Mensajes'],
                'paginas.dominios' => ['etiqueta' => 'Dominio propio', 'descripcion' => 'Conectar, verificar y desvincular dominios del negocio.', 'ambitos' => self::N, 'seccion' => 'Dominios personalizados'],
                'paginas.supervisar' => ['etiqueta' => 'Supervisar', 'descripcion' => 'Negocios con el servicio, publicación, dominios e incidencias (sin mensajes de visitantes).', 'ambitos' => self::P, 'seccion' => 'Supervisión'],
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
     * Permisos que el rol Cliente NO recibe al crearse. «servicios.ver»
     * controla si el negocio ve «Mis servicios» y la contratación en línea:
     * queda oculto hasta que DASTI lo habilite desde «Roles y permisos»
     * (política inicial mientras la contratación en línea no se ofrece).
     * «productos.cambiar_cantidad_qr»: el número de QR lo fija DASTI en el
     * alta; solo los roles a los que DASTI lo otorgue pueden cambiarlo.
     * «productos.editar_nombre»: DASTI decide qué roles de cliente pueden
     * renombrar sus productos.
     */
    private const EXCLUIDOS_CLIENTE = ['servicios.ver', 'productos.cambiar_cantidad_qr', 'productos.editar_nombre'];

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
     * @return array{etiqueta: string, descripcion: string, ambitos: list<string>, seccion?: string}|null
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
            self::ROL_CLIENTE => array_values(array_diff(self::delAmbito(self::AMBITO_NEGOCIO), self::EXCLUIDOS_CLIENTE)),
            self::ROL_EMPLEADO => self::EMPLEADO,
            default => [],
        };
    }

    public static function etiquetaAmbito(string $ambito): string
    {
        return $ambito === self::AMBITO_NEGOCIO ? 'Cliente (su negocio)' : 'Personal DASTI (plataforma)';
    }

    /**
     * Matriz para la interfaz: módulos con sus permisos. `grupo` separa los
     * módulos de administración de la plataforma (solo personal DASTI) de los
     * operativos del negocio y de los compartidos.
     *
     * @return list<array{clave: string, etiqueta: string, descripcion: string, grupo: string, permisos: list<array{nombre: string, etiqueta: string, descripcion: string, ambitos: list<string>, seccion?: string}>}>
     */
    public static function matriz(): array
    {
        $matriz = [];

        foreach (self::MODULOS as $clave => $modulo) {
            $permisos = [];
            $ambitos = [];

            foreach ($modulo['permisos'] as $nombre => $definicion) {
                $permisos[] = ['nombre' => $nombre, ...$definicion];
                $ambitos = [...$ambitos, ...$definicion['ambitos']];
            }

            $ambitos = array_unique($ambitos);

            $matriz[] = [
                'clave' => $clave,
                'etiqueta' => $modulo['etiqueta'],
                'descripcion' => $modulo['descripcion'],
                'grupo' => count($ambitos) > 1 ? 'mixto' : (string) reset($ambitos),
                'permisos' => $permisos,
            ];
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
