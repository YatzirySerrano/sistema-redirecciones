/**
 * Contenido de la Guía de uso (pages/guia/Index.vue).
 *
 * Fiel a lo que existe en la plataforma (ver docs/manual-cliente.md y
 * docs/manual-administrador.md). Cada tema puede enlazar a su pantalla; el
 * enlace solo se muestra si el usuario tiene el permiso correspondiente.
 *
 * En los textos, **así** se muestra en negritas.
 */
import type { LucideIcon } from '@lucide/vue';
import {
    ArrowLeftRight,
    Brush,
    Building2,
    ChartColumnBig,
    CreditCard,
    HeartHandshake,
    History,
    KeyRound,
    LayoutList,
    PackagePlus,
    QrCode,
    Rocket,
    Settings2,
    ShieldCheck,
    Sparkles,
    UsersRound,
} from '@lucide/vue';
import { index as auditoria } from '@/routes/admin/auditoria';
import { edit as configuracion } from '@/routes/admin/configuracion';
import { index as disenosClientes } from '@/routes/admin/disenos';
import { index as estadisticasAdmin } from '@/routes/admin/estadisticas';
import { index as negocios } from '@/routes/admin/negocios';
import { index as pagos } from '@/routes/admin/pagos';
import { create as nuevoProducto } from '@/routes/admin/productos';
import { index as roles } from '@/routes/admin/roles';
import { index as serviciosAdmin } from '@/routes/admin/servicios';
import { index as usuarios } from '@/routes/admin/usuarios';
import { index as estadisticasCliente } from '@/routes/cliente/estadisticas';
import { inicio as fidelidadInicio } from '@/routes/cliente/fidelidad';
import { inicio as paginaInicio } from '@/routes/cliente/paginas';
import { index as productosCliente } from '@/routes/cliente/productos';
import { index as serviciosCliente } from '@/routes/cliente/servicios';
import { edit as perfil } from '@/routes/profile';

export type Ambito = 'admin' | 'cliente';

export type Bloque =
    | { tipo: 'texto'; texto: string }
    | { tipo: 'pasos'; titulo?: string; pasos: string[] }
    | { tipo: 'lista'; titulo?: string; elementos: string[] }
    | {
          tipo: 'nota';
          tono: 'consejo' | 'aviso' | 'importante';
          titulo: string;
          texto: string;
      }
    | {
          tipo: 'preguntas';
          preguntas: { pregunta: string; respuesta: string }[];
      };

export type Tema = {
    id: string;
    titulo: string;
    resumen: string;
    icono: LucideIcon;
    grupo: string;
    bloques: Bloque[];
    enlace?: {
        texto: string;
        href: string;
        /** Basta con tener alguno. */
        permisos?: string[];
    };
};

/* ------------------------------------------------------------------ */
/* Temas compartidos                                                   */
/* ------------------------------------------------------------------ */

const cuentaYSeguridad = (admin: boolean): Tema => ({
    id: 'cuenta',
    titulo: 'Cuenta y seguridad',
    resumen:
        'Tu nombre, tu contraseña, la verificación en dos pasos y el tema claro u oscuro.',
    icono: KeyRound,
    grupo: 'Tu cuenta',
    enlace: { texto: 'Abrir Mi cuenta', href: perfil.url() },
    bloques: [
        {
            tipo: 'lista',
            titulo: 'Desde el menú con tu nombre (abajo en la barra lateral):',
            elementos: [
                '**Mi cuenta**: cambiar tu nombre y tu contraseña.',
                '**Contraseña y seguridad**: activar la verificación en dos pasos y consultar tus códigos de recuperación.',
                '**Apariencia**: tema claro, oscuro o el del sistema.',
                '**Cerrar sesión**.',
            ],
        },
        {
            tipo: 'pasos',
            titulo: 'Activar la verificación en dos pasos',
            pasos: [
                'Abre **Mi cuenta › Contraseña y seguridad**.',
                'Pulsa **Activar** y escanea el código con tu aplicación de autenticación (por ejemplo, Google Authenticator o Microsoft Authenticator).',
                'Escribe el código de 6 dígitos que muestra la aplicación para confirmar.',
                'Guarda los **códigos de recuperación** en un lugar seguro: te permiten entrar si pierdes el teléfono.',
            ],
        },
        admin
            ? {
                  tipo: 'nota',
                  tono: 'importante',
                  titulo: 'Obligatoria para el personal DASTI',
                  texto: 'En producción, los administradores deben activar la verificación en dos pasos antes de usar el panel.',
              }
            : {
                  tipo: 'nota',
                  tono: 'consejo',
                  titulo: 'Recomendada',
                  texto: 'Con la verificación en dos pasos, aunque alguien conozca tu contraseña no podrá entrar sin tu teléfono.',
              },
        {
            tipo: 'preguntas',
            preguntas: [
                {
                    pregunta: '¿Olvidé mi contraseña, qué hago?',
                    respuesta:
                        'En la pantalla de acceso usa **¿Olvidaste tu contraseña?** y sigue el enlace que llega a tu correo.',
                },
                {
                    pregunta: '¿Puedo cambiar mi correo de acceso?',
                    respuesta: admin
                        ? 'El correo de cada cuenta se cambia desde **Usuarios y accesos**.'
                        : 'No desde tu cuenta: pídeselo a DASTI.',
                },
            ],
        },
    ],
});

/* ------------------------------------------------------------------ */
/* Cliente (negocio)                                                   */
/* ------------------------------------------------------------------ */

const temasCliente = (): Tema[] => [
    {
        id: 'primeros-pasos',
        titulo: 'Primeros pasos',
        resumen:
            'Qué hay en tu panel y cómo moverte por él en tus primeros minutos.',
        icono: Rocket,
        grupo: 'Empezar',
        bloques: [
            {
                tipo: 'texto',
                texto: 'Tu panel reúne los productos que compraste a DASTI (códigos QR y tarjetas NFC) y los servicios que tengas contratados. La barra lateral se organiza por secciones: **Redirecciones**, **Fidelidad**, **Página de enlaces** y **Servicios**. Solo ves lo que tu rol permite.',
            },
            {
                tipo: 'pasos',
                titulo: 'Para empezar',
                pasos: [
                    'Entra con el correo y la contraseña que te dio DASTI, o define tu contraseña con el enlace de invitación (funciona una sola vez y caduca).',
                    'Revisa **Inicio**: resume los últimos 30 días, avisa de productos que necesitan atención y ofrece accesos rápidos.',
                    'Abre **Mis productos** y comprueba que cada QR o tarjeta lleve al destino correcto.',
                    'Activa la verificación en dos pasos en **Mi cuenta**.',
                ],
            },
            {
                tipo: 'nota',
                tono: 'consejo',
                titulo: 'Ayuda en cada pantalla',
                texto: 'El botón **?** de la parte superior abre esta guía en el tema de la pantalla en la que estás. Los iconos **i** junto a algunos títulos explican conceptos concretos.',
            },
        ],
    },
    {
        id: 'productos',
        titulo: 'Mis productos: destino y diseño del QR',
        resumen:
            'Cambia a dónde lleva cada QR o tarjeta NFC y personaliza cómo se ve tu código.',
        icono: QrCode,
        grupo: 'Redirecciones',
        enlace: {
            texto: 'Ir a Mis productos',
            href: productosCliente.url(),
            permisos: ['productos.ver'],
        },
        bloques: [
            {
                tipo: 'texto',
                texto: 'Cada producto tiene un **enlace permanente** propio. El QR o la tarjeta guardan ese enlace, y tú decides a dónde lleva. Por eso puedes cambiar el destino cuando quieras **sin reimprimir el QR ni reprogramar la tarjeta**.',
            },
            {
                tipo: 'pasos',
                titulo: 'Cambiar el destino',
                pasos: [
                    'En **Mis productos**, abre el producto y pulsa **Editar destino**.',
                    'Escribe la dirección completa, empezando por `https://`.',
                    'Espera el aviso «Lleva a …»: solo aparece si la dirección se puede guardar. Si no, verás el motivo.',
                    'Pulsa **Guardar destino**. El cambio es inmediato.',
                ],
            },
            {
                tipo: 'pasos',
                titulo: 'Personalizar el diseño del QR',
                pasos: [
                    'En la ficha del producto, abre el diseñador del código QR.',
                    'Elige el color del código y del fondo (o fondo transparente) y el margen.',
                    'Elige la forma de las **Esquinas** (redondeadas, hoja o cuadradas) y de los **Puntos del código** (fluidos, puntos o cuadrados).',
                    'Si DASTI cargó tu logotipo, inclúyelo **al centro** del código.',
                    'Descarga en **PNG** o **SVG**. El código siempre contiene tu enlace permanente.',
                ],
            },
            {
                tipo: 'nota',
                tono: 'consejo',
                titulo: 'Las esquinas del QR',
                texto: 'Los tres cuadros de las esquinas sirven para que el teléfono localice el código: toman el color y la forma que elijas, sin logotipos. Tu logotipo va al centro. Los diseños con poco contraste se bloquean para que el código siempre se pueda leer.',
            },
            {
                tipo: 'lista',
                titulo: 'Productos con 1 o 2 QR',
                elementos: [
                    'Algunos productos llevan **dos códigos QR** (por ejemplo, uno por cada cara o uso).',
                    'Cada QR tiene su **propio destino**, su **propio diseño** y sus **propias estadísticas**: cambiar uno no afecta al otro.',
                    'En la ficha del producto eliges qué QR estás editando antes de cambiar su destino o su diseño.',
                ],
            },
            {
                tipo: 'lista',
                titulo: 'Tarjetas NFC',
                elementos: [
                    'La tarjeta se lee al acercarla a la parte trasera de un teléfono con NFC activado.',
                    'Usa el mismo enlace permanente: al cambiar el destino, la tarjeta lleva al nuevo sitio sin reprogramarla.',
                    'Compruébala acercándola a un teléfono o abriendo el enlace permanente con el botón ↗.',
                ],
            },
            {
                tipo: 'preguntas',
                preguntas: [
                    {
                        pregunta: '¿Por qué no puedo guardar una dirección?',
                        respuesta:
                            'Por seguridad no se aceptan acortadores de enlaces, direcciones internas ni esquemas distintos de `https://`. El aviso del formulario indica el motivo exacto.',
                    },
                    {
                        pregunta: 'Mi producto aparece como «Suspendido».',
                        respuesta:
                            'Un producto suspendido no redirige ni se puede modificar. Contacta a DASTI para reactivarlo.',
                    },
                    {
                        pregunta: 'No veo el diseñador ni la descarga del QR.',
                        respuesta:
                            'DASTI puede deshabilitar las descargas de QR para los clientes. En ese caso pídele los archivos a DASTI.',
                    },
                ],
            },
        ],
    },
    {
        id: 'estadisticas',
        titulo: 'Estadísticas',
        resumen:
            'Cuántas veces se abren tus QR y tarjetas, cuándo y desde qué dispositivos.',
        icono: ChartColumnBig,
        grupo: 'Redirecciones',
        enlace: {
            texto: 'Ver estadísticas',
            href: estadisticasCliente.url(),
        },
        bloques: [
            {
                tipo: 'texto',
                texto: 'Elige el periodo (hoy, 7 o 30 días, este mes, mes anterior, 90 días, 12 meses o fechas propias), un producto y un canal. Verás la evolución frente al periodo anterior de igual duración, NFC frente a QR, los productos con más actividad, los días y horarios con más uso (hora del centro de México), dispositivos y navegadores.',
            },
            {
                tipo: 'nota',
                tono: 'importante',
                titulo: 'Qué significa «interacciones»',
                texto: 'Son las veces que se abrió un enlace y llegó a su destino, sin accesos automatizados identificados. No equivalen a personas distintas, ventas ni clientes nuevos. No registramos ubicación.',
            },
            {
                tipo: 'preguntas',
                preguntas: [
                    {
                        pregunta: 'No veo la sección Estadísticas.',
                        respuesta:
                            'DASTI decide si las estadísticas están disponibles para los clientes. Si no aparece en el menú, no está habilitada para tu cuenta.',
                    },
                    {
                        pregunta: '¿Cuánto tiempo se guardan los datos?',
                        respuesta:
                            'Los horarios y dispositivos se conservan alrededor de 13 meses; los totales por día, siempre.',
                    },
                ],
            },
        ],
    },
    {
        id: 'fidelidad',
        titulo: 'Fidelidad',
        resumen:
            'Tarjetas de sellos digitales: registra visitas en el mostrador y entrega recompensas.',
        icono: HeartHandshake,
        grupo: 'Servicios',
        enlace: {
            texto: 'Abrir Fidelidad',
            href: fidelidadInicio.url(),
            permisos: ['fidelidad.ver'],
        },
        bloques: [
            {
                tipo: 'texto',
                texto: 'Disponible si tu negocio contrató Fidelidad. Se organiza en **Inicio** (resumen), **Operación** (Mostrador y Consumidores), **Programas** (Campañas), **Personalización** (Diseñar mi programa) y **Administración** (Personal autorizado, Historial y actividad).',
            },
            {
                tipo: 'pasos',
                titulo: 'Crear tu programa',
                pasos: [
                    'En **Campañas**, pulsa **Nueva campaña**.',
                    'Define la meta y la recompensa (por ejemplo, «8 visitas = 1 café») y, si quieres, los días para canjearla.',
                    'Ajusta las reglas que protegen tu programa: minutos entre visitas, visitas por día y si el ciclo se repite.',
                    'En **Diseñar mi programa**, personaliza la tarjeta que ven tus clientes y pulsa **Publicar**.',
                ],
            },
            {
                tipo: 'pasos',
                titulo: 'Atender en el mostrador',
                pasos: [
                    'Abre **Mostrador** y escribe el código de la tarjeta, el nombre o el teléfono: los resultados aparecen mientras escribes. También puedes **Escanear QR** con la cámara o leer la tarjeta NFC.',
                    'Elige al cliente (con las flechas y Enter). Si hay varios con el mismo nombre, confírmalo con él.',
                    'Pulsa **Confirmar visita** solo cuando el cliente realmente compró o recibió el servicio.',
                    'Cuando complete la meta, pulsa **Entregar recompensa**.',
                ],
            },
            {
                tipo: 'nota',
                tono: 'aviso',
                titulo: 'Escanear no suma visitas',
                texto: 'Buscar o escanear una tarjeta solo identifica al cliente. La visita se registra únicamente cuando alguien de tu equipo la confirma, y queda con su nombre.',
            },
            {
                tipo: 'lista',
                titulo: 'Credenciales de tus clientes',
                elementos: [
                    'Cada consumidor se identifica con una **credencial**: una tarjeta física (QR o NFC) o un **enlace digital** que abre su tarjeta de sellos en el teléfono.',
                    'Desde la ficha del consumidor puedes emitir una credencial QR o NFC, generar su enlace digital o **reemplazar** una credencial extraviada: la anterior deja de funcionar y sus visitas y recompensas se conservan.',
                    'Reemplazar o revocar una credencial, ajustar o anular visitas y recompensas, y fusionar cuentas duplicadas piden un **motivo obligatorio** y quedan en el historial.',
                ],
            },
            {
                tipo: 'preguntas',
                preguntas: [
                    {
                        pregunta: '¿Quién puede registrar visitas?',
                        respuesta:
                            'Las personas que agregues en **Personal autorizado**, según los permisos de su rol. Cada visita y canje queda registrado con el nombre de quien lo autorizó.',
                    },
                    {
                        pregunta:
                            '¿Guardar el diseño cambia lo que ven mis clientes?',
                        respuesta:
                            'No. **Guardar** conserva tu borrador; lo publicado cambia solo al pulsar **Publicar**. El diseño nunca modifica visitas, metas, recompensas ni canjes.',
                    },
                ],
            },
        ],
    },
    {
        id: 'pagina-enlaces',
        titulo: 'Página de enlaces',
        resumen:
            'Una página pública con tu identidad que reúne redes sociales, contacto y contenidos.',
        icono: LayoutList,
        grupo: 'Servicios',
        enlace: {
            texto: 'Abrir Mi página',
            href: paginaInicio.url(),
            permisos: ['paginas.ver'],
        },
        bloques: [
            {
                tipo: 'texto',
                texto: 'Disponible si tu negocio contrató Página de enlaces. Incluye **Mi página** (dirección pública y QR), **Editor**, **Estadísticas** y **Mensajes** que te envían los visitantes.',
            },
            {
                tipo: 'pasos',
                titulo: 'Publicar tu página',
                pasos: [
                    'Abre el **Editor** y pulsa **Plantillas** para elegir uno de los 24 diseños por giro y estilo.',
                    'Al aplicarla, elige si cambiar solo el diseño, agregar los ejemplos o reemplazar la página de inicio. Puedes deshacerlo.',
                    'Completa los textos y destinos marcados como pendientes («Escribe aquí…»).',
                    'Pulsa **Publicar**. Puedes restaurar versiones anteriores cuando quieras.',
                ],
            },
            {
                tipo: 'nota',
                tono: 'consejo',
                titulo: 'Imágenes',
                texto: 'Junto a cada botón de carga verás los formatos aceptados y el tamaño máximo. Si un archivo no es válido, se avisa antes de subirlo y no pierdes lo que llevabas.',
            },
        ],
    },
    {
        id: 'servicios',
        titulo: 'Mis servicios',
        resumen:
            'Consulta y contrata Fidelidad o Página de enlaces con un pago único.',
        icono: Sparkles,
        grupo: 'Servicios',
        enlace: {
            texto: 'Ir a Mis servicios',
            href: serviciosCliente.url(),
            permisos: ['servicios.ver'],
        },
        bloques: [
            {
                tipo: 'texto',
                texto: 'El apartado aparece cuando DASTI lo habilita para tu cuenta. Las **Redirecciones QR y NFC** están incluidas con tus productos; **Fidelidad** y **Página de enlaces** se contratan por separado con un **pago único** (sin mensualidades). Los servicios que aún no tienes aparecen en el menú con la etiqueta «Contratar».',
            },
            {
                tipo: 'pasos',
                titulo: 'Contratar en línea',
                pasos: [
                    'Abre el servicio y revisa el precio final (indica si incluye IVA).',
                    'Pulsa **Contratar** y confirma. Pagarás en el formulario seguro de **Openpay**; DASTI nunca recibe los datos de tu tarjeta.',
                    'Al terminar, vuelves a DASTI. El servicio se activa cuando Openpay confirma el pago, normalmente al instante.',
                ],
            },
            {
                tipo: 'nota',
                tono: 'aviso',
                titulo: '«Esperando la confirmación»',
                texto: 'Si ves este aviso, usa **Consultar de nuevo** más tarde. No pagues otra vez.',
            },
            {
                tipo: 'preguntas',
                preguntas: [
                    {
                        pregunta: 'Mi tarjeta fue rechazada.',
                        respuesta:
                            'Puedes intentarlo de nuevo. No se activa nada ni se hace un cargo aprobado.',
                    },
                    {
                        pregunta: 'No veo el botón Contratar.',
                        respuesta:
                            'Solo las personas con el permiso «Contratar» pueden comprar. Pide a quien administra tu negocio o a DASTI que lo habilite.',
                    },
                ],
            },
        ],
    },
    cuentaYSeguridad(false),
];

/* ------------------------------------------------------------------ */
/* Personal DASTI                                                      */
/* ------------------------------------------------------------------ */

const temasAdmin = (): Tema[] => [
    {
        id: 'primeros-pasos',
        titulo: 'Primeros pasos',
        resumen:
            'Cómo se organiza la administración de DASTI y el flujo habitual de alta de un cliente.',
        icono: Rocket,
        grupo: 'Empezar',
        bloques: [
            {
                tipo: 'texto',
                texto: 'La barra lateral se agrupa en **General** (Panel y Estadísticas), **Gestión** (Negocios, Productos y Redirecciones), **Servicios** (Contrataciones, Pagos, Páginas de enlaces y Diseño para clientes) y **Administración** (usuarios, roles, auditoría, configuración y apariencia). Cada sección aparece solo si tu rol tiene el permiso.',
            },
            {
                tipo: 'pasos',
                titulo: 'Flujo habitual con un cliente nuevo',
                pasos: [
                    'Crea el **negocio** y su acceso (invitación por correo o contraseña temporal).',
                    'Carga su **logotipo** en la ficha del negocio para usarlo al centro de sus QR.',
                    'Registra sus **productos** (QR, NFC o combinado) con su destino inicial.',
                    'Descarga los QR o graba las tarjetas NFC y márcalos como **Listo** o **Entregado**.',
                    'Si contrata servicios, actívalos desde **Contrataciones** o deja que pague en línea.',
                ],
            },
            {
                tipo: 'nota',
                tono: 'consejo',
                titulo: 'Ayuda en cada pantalla',
                texto: 'El botón **?** de la parte superior abre esta guía en el tema de la pantalla actual.',
            },
        ],
    },
    {
        id: 'negocios',
        titulo: 'Negocios',
        resumen:
            'Alta de clientes, accesos, logotipo, suspensión y reactivación.',
        icono: Building2,
        grupo: 'Negocios y productos',
        enlace: {
            texto: 'Ir a Negocios',
            href: negocios.url(),
            permisos: ['negocios.ver'],
        },
        bloques: [
            {
                tipo: 'pasos',
                titulo: 'Crear un negocio',
                pasos: [
                    'Pulsa **Negocios › Nuevo negocio**.',
                    'Captura el nombre comercial (obligatorio), el responsable, el correo y el teléfono. Las notas internas nunca las ve el cliente.',
                    'Deja activo **Crear acceso ahora** y elige **Invitación por correo** (enlace de un solo uso que caduca a las 72 horas) o **Contraseña temporal** (se cambia al entrar).',
                    'Guarda: se abre la ficha del negocio.',
                ],
            },
            {
                tipo: 'nota',
                tono: 'aviso',
                titulo: 'Si el correo de invitación falla',
                texto: 'El negocio y el usuario quedan creados y verás un aviso. Cuando el correo funcione, usa **Enviar enlace de acceso**; cada reenvío invalida los enlaces anteriores.',
            },
            {
                tipo: 'texto',
                texto: '**Suspender negocio** (en su ficha) hace que todos sus enlaces dejen de redirigir y que sus usuarios no puedan entrar. **Reactivar** lo restablece.',
            },
        ],
    },
    {
        id: 'usuarios',
        titulo: 'Usuarios y roles',
        resumen:
            'Accesos del personal y de los negocios, y qué puede hacer cada rol.',
        icono: UsersRound,
        grupo: 'Administración',
        enlace: {
            texto: 'Ir a Usuarios y accesos',
            href: usuarios.url(),
            permisos: ['usuarios.ver'],
        },
        bloques: [
            {
                tipo: 'lista',
                titulo: 'Usuarios y accesos',
                elementos: [
                    'Para dar acceso a otra persona de un negocio: en su ficha, **Usuarios › Agregar acceso**, o desde **Usuarios y accesos › Nuevo acceso**.',
                    'Puedes suspender o reactivar, usar **Enviar enlace de acceso** o asignar una contraseña temporal.',
                    'Suspender, asignar o restablecer una contraseña cierra las sesiones abiertas del usuario.',
                ],
            },
            {
                tipo: 'lista',
                titulo: 'Roles y permisos',
                elementos: [
                    'El catálogo se separa en «Administración de la plataforma» y «Servicios y operación de los negocios». Puedes buscar permisos y ver solo los marcados.',
                    'Nadie otorga ni retira permisos que no tiene. Solo un superadministrador gestiona el rol Superadministrador, y siempre queda al menos uno activo.',
                    'Los permisos de negocio nunca salen del negocio del usuario.',
                    'Los cambios aplican en la siguiente acción de cada usuario, aunque tenga la sesión abierta.',
                ],
            },
            {
                tipo: 'nota',
                tono: 'importante',
                titulo: 'Mostrar «Mis servicios» a los clientes',
                texto: 'Lo controla el permiso **Servicios · Ver** en el rol del negocio (y **Contratar** para pagar en línea). Por defecto ningún rol de cliente lo tiene.',
            },
        ],
    },
    {
        id: 'productos',
        titulo: 'Alta de productos y QR',
        resumen:
            'Registra QR, NFC o combinados, con 1 o 2 QR, y prepara cada unidad para entregarla.',
        icono: PackagePlus,
        grupo: 'Negocios y productos',
        enlace: {
            texto: 'Registrar producto',
            href: nuevoProducto.url(),
            permisos: ['productos.crear'],
        },
        bloques: [
            {
                tipo: 'pasos',
                titulo: 'Registrar productos',
                pasos: [
                    'En la ficha del negocio pulsa **Registrar productos** (o **Productos › Registrar producto**).',
                    'Escribe el nombre y elige el **tipo**: QR, NFC o combinado (QR + NFC).',
                    'Indica si el producto llevará **1 o 2 QR**. Cada QR tendrá su propio destino, diseño y estadísticas.',
                    'Indica la **cantidad**: un lote de 3 crea «Nombre 1…3», cada uno con su propio enlace permanente.',
                    'Opcional: destino inicial, estado de preparación, fecha de entrega y referencia o lote.',
                ],
            },
            {
                tipo: 'nota',
                tono: 'importante',
                titulo: 'Un código por unidad, para siempre',
                texto: 'El enlace permanente nunca cambia (ni al cambiar el destino ni al transferir) y nunca se reutiliza. Para medir QR y NFC por separado, registra productos distintos: el tipo combinado comparte un enlace.',
            },
            {
                tipo: 'lista',
                titulo: 'Diseño y descarga del QR',
                elementos: [
                    'Elige el color del código y del fondo (o transparente) y el margen.',
                    'Elige la forma de las **Esquinas** (redondeadas, hoja o cuadradas) y de los **Puntos del código** (fluidos, puntos o cuadrados). Los tres cuadros de las esquinas toman el color y la forma elegidos, sin logotipos.',
                    'Incluye el logotipo del negocio al centro si lo cargaste en su ficha.',
                    'Descarga en **PNG** (256–2048 px) o **SVG**. Usa **Guardar diseño** para conservar los ajustes.',
                    'Los diseños con poco contraste se bloquean para garantizar la lectura.',
                ],
            },
            {
                tipo: 'pasos',
                titulo: 'Grabar una tarjeta NFC (NFC Tools)',
                pasos: [
                    'Copia la URL de **Producto › pestaña NFC**.',
                    'En NFC Tools entra a **Escribir › Agregar un registro › URL/URI**, pega la URL y toca **Escribir**.',
                    'Acerca la tarjeta. Bloquearla es opcional e irreversible.',
                    'Pruébala con un teléfono y marca el producto como **Listo** o **Entregado** en _Editar_.',
                ],
            },
        ],
    },
    {
        id: 'transferencias',
        titulo: 'Destinos, suspensiones y transferencias',
        resumen:
            'Cambiar destinos, suspender, transferir un producto a otro negocio o eliminarlo.',
        icono: ArrowLeftRight,
        grupo: 'Negocios y productos',
        bloques: [
            {
                tipo: 'lista',
                elementos: [
                    '**Cambiar destino**: desde la ficha del producto o desde **Redirecciones › Editar destino**. Cada cambio queda en el historial.',
                    '**Suspender producto**: el enlace muestra «no disponible» y el cliente no puede editarlo. **Reactivar** lo restablece.',
                    '**Transferir**: elige el negocio destino y escribe el motivo (obligatorio). El negocio anterior pierde el acceso de inmediato y el código no cambia.',
                    '**Eliminar**: solo para productos registrados por error, pendientes y sin accesos. En cualquier otro caso, suspende.',
                ],
            },
            {
                tipo: 'nota',
                tono: 'aviso',
                titulo: 'Historial tras una transferencia',
                texto: 'El nuevo propietario empieza con historial, contadores y estadísticas propios: no ve los destinos ni la actividad del anterior. DASTI conserva el historial completo en la auditoría.',
            },
        ],
    },
    {
        id: 'servicios',
        titulo: 'Servicios y contrataciones',
        resumen:
            'Catálogo y precios de Fidelidad y Página de enlaces, activaciones y suspensiones.',
        icono: HeartHandshake,
        grupo: 'Servicios y pagos',
        enlace: {
            texto: 'Ir a Contrataciones',
            href: serviciosAdmin.url(),
            permisos: ['servicios.ver'],
        },
        bloques: [
            {
                tipo: 'lista',
                elementos: [
                    '**Catálogo y precios**: precio final en MXN, si incluye IVA, vigencia (vacía = permanente, pago único), descripción y beneficios que ve el cliente.',
                    'Habilita «Permitir contratación en línea» solo cuando el precio esté definido y Openpay configurado.',
                    '**Activar servicio**: para cortesías o pagos fuera de línea (importe y referencia). Solo se activa el servicio elegido.',
                    '**Suspender, reactivar o restringir**: nunca afecta las redirecciones QR/NFC ni borra datos.',
                ],
            },
            {
                tipo: 'nota',
                tono: 'consejo',
                titulo: 'Motivos opcionales',
                texto: 'En activaciones y suspensiones el motivo es opcional; la auditoría registra siempre quién, cuándo y qué cambió.',
            },
        ],
    },
    {
        id: 'pagos',
        titulo: 'Pagos con Openpay',
        resumen: 'Órdenes, intentos de cobro, reembolsos y contracargos.',
        icono: CreditCard,
        grupo: 'Servicios y pagos',
        enlace: {
            texto: 'Ir a Pagos',
            href: pagos.url(),
            permisos: ['pagos.ver'],
        },
        bloques: [
            {
                tipo: 'texto',
                texto: 'Cada orden muestra su folio, los intentos de cobro (marca y últimos 4 dígitos, nunca el número completo), reembolsos y contracargos.',
            },
            {
                tipo: 'lista',
                titulo: 'Acciones',
                elementos: [
                    '**Consultar en Openpay**: actualiza el estado real de la orden.',
                    '**Marcar revisado** (nota opcional): requiere «Pagos › Revisar incidencias».',
                    '**Reembolsar** (total o parcial, motivo obligatorio): requiere «Pagos › Reembolsar».',
                ],
            },
            {
                tipo: 'nota',
                tono: 'importante',
                titulo: 'Reglas automáticas',
                texto: 'Reembolso total: el servicio se suspende (si no hay otro periodo vigente). Reembolso parcial: queda en revisión y conserva el acceso. Contracargo: el servicio queda restringido (solo lectura) hasta resolverse.',
            },
        ],
    },
    {
        id: 'diseno-clientes',
        titulo: 'Diseño para clientes',
        resumen:
            'Diseña la tarjeta de Fidelidad o la Página de enlaces de un negocio como servicio adicional.',
        icono: Brush,
        grupo: 'Servicios y pagos',
        enlace: {
            texto: 'Ir a Diseño para clientes',
            href: disenosClientes.url(),
            permisos: [
                'fidelidad.disenar_negocios',
                'paginas.disenar_negocios',
            ],
        },
        bloques: [
            {
                tipo: 'pasos',
                pasos: [
                    'Abre **Servicios › Diseño para clientes**.',
                    'Busca y elige el negocio.',
                    'Pulsa **Abrir editor** en «Diseñar Fidelidad» o «Diseñar Página de enlaces».',
                    'Guarda y publica como lo haría el cliente. Arriba verás «Estás diseñando para…».',
                ],
            },
            {
                tipo: 'nota',
                tono: 'aviso',
                titulo: 'El servicio debe estar contratado',
                texto: 'Para guardar o publicar, el servicio del negocio debe estar activo y vigente: un permiso nunca sustituye la contratación. Cada acción queda en la auditoría con la marca «Realizado por personal DASTI».',
            },
        ],
    },
    {
        id: 'estadisticas',
        titulo: 'Panel y estadísticas',
        resumen:
            'Indicadores de la plataforma, uso de enlaces y resultados comerciales.',
        icono: ChartColumnBig,
        grupo: 'Administración',
        enlace: {
            texto: 'Ver estadísticas',
            href: estadisticasAdmin.url(),
            permisos: ['estadisticas.ver'],
        },
        bloques: [
            {
                tipo: 'lista',
                elementos: [
                    '**Panel**: negocios, productos, enlaces, usuarios e interacciones de 30 días frente a los 30 previos, reparto NFC/QR, altas por mes e incidencias. Cada bloque aparece solo si tu rol puede consultarlo.',
                    '**Uso de enlaces**: filtra por periodo, negocio, producto y canal; incluye evolución, horarios, dispositivos y productos sin uso.',
                    '**Comercial** (requiere «Servicios · Ver» o «Pagos · Ver»): contrataciones e ingresos cobrados y confirmados, netos de reembolsos.',
                ],
            },
            {
                tipo: 'nota',
                tono: 'importante',
                titulo: 'Qué no se mide',
                texto: 'No se registra ubicación. «Interacciones» no son personas, ventas ni conversiones. Las órdenes pendientes y los pagos de prueba nunca cuentan como ingreso.',
            },
        ],
    },
    {
        id: 'configuracion',
        titulo: 'Configuración y apariencia',
        resumen: 'Reglas de la plataforma y colores de la interfaz.',
        icono: Settings2,
        grupo: 'Administración',
        enlace: {
            texto: 'Abrir Configuración',
            href: configuracion.url(),
            permisos: ['configuracion.ver'],
        },
        bloques: [
            {
                tipo: 'lista',
                titulo: 'Configuración',
                elementos: [
                    'Permitir destinos `http`.',
                    'Permitir a los clientes descargar QR.',
                    'Mostrar estadísticas a los clientes.',
                    'Dominios bloqueados.',
                    'Días de retención de eventos.',
                ],
            },
            {
                tipo: 'texto',
                texto: 'En **Apariencia** personalizas los colores de la interfaz para todos los usuarios. Los cambios se aplican sin recompilar y puedes volver a la apariencia original.',
            },
        ],
    },
    {
        id: 'auditoria',
        titulo: 'Auditoría',
        resumen:
            'Historial legible de altas, cambios, suspensiones, transferencias y accesos.',
        icono: History,
        grupo: 'Administración',
        enlace: {
            texto: 'Abrir Auditoría',
            href: auditoria.url(),
            permisos: ['auditoria.ver'],
        },
        bloques: [
            {
                tipo: 'texto',
                texto: 'Cada registro muestra quién hizo qué, cuándo y sobre qué registro, con los valores antes y después. Puedes filtrarlo.',
            },
            {
                tipo: 'lista',
                titulo: 'Motivos obligatorios',
                elementos: [
                    'Transferir un producto a otro negocio.',
                    'Reembolsar un pago.',
                    'En Fidelidad: ajustar o anular visitas y recompensas, fusionar consumidores, reemplazar o revocar credenciales y emitir enlaces de recuperación.',
                ],
            },
        ],
    },
    {
        ...cuentaYSeguridad(true),
        grupo: 'Tu cuenta',
    },
    {
        id: 'privacidad',
        titulo: 'Datos que no ve DASTI',
        resumen:
            'Qué información de los negocios queda fuera del alcance del personal.',
        icono: ShieldCheck,
        grupo: 'Tu cuenta',
        enlace: {
            texto: 'Revisar Roles y permisos',
            href: roles.url(),
            permisos: ['roles.ver'],
        },
        bloques: [
            {
                tipo: 'texto',
                texto: 'El personal de DASTI ve contrataciones y pagos, pero nunca los datos personales de los consumidores de Fidelidad ni los mensajes de las Páginas de enlaces. La dirección pública, los dominios y las estadísticas de una página los administra el negocio.',
            },
        ],
    },
];

export function temasGuia(ambito: Ambito): Tema[] {
    return ambito === 'admin' ? temasAdmin() : temasCliente();
}

/** Texto plano de un tema, para el buscador. */
export function textoDeTema(tema: Tema): string {
    const partes: string[] = [tema.titulo, tema.resumen, tema.grupo];

    for (const b of tema.bloques) {
        switch (b.tipo) {
            case 'texto':
                partes.push(b.texto);
                break;
            case 'pasos':
                partes.push(b.titulo ?? '', ...b.pasos);
                break;
            case 'lista':
                partes.push(b.titulo ?? '', ...b.elementos);
                break;
            case 'nota':
                partes.push(b.titulo, b.texto);
                break;
            case 'preguntas':
                for (const p of b.preguntas) {
                    partes.push(p.pregunta, p.respuesta);
                }

                break;
        }
    }

    return normalizar(partes.join(' '));
}

/** Minúsculas y sin acentos, para buscar «estadistica» o «Estadística». */
export function normalizar(texto: string): string {
    return texto
        .toLowerCase()
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .replace(/[*_`]/g, '');
}

/** Tema de la guía que corresponde a la pantalla actual (ayuda «?»). */
export function temaSegunUrl(url: string, admin: boolean): string | null {
    const ruta = url.split(/[?#]/)[0];
    const mapa: [string, string][] = admin
        ? [
              ['/admin/negocios', 'negocios'],
              ['/admin/productos', 'productos'],
              ['/admin/enlaces', 'transferencias'],
              ['/admin/usuarios', 'usuarios'],
              ['/admin/roles', 'usuarios'],
              ['/admin/servicios', 'servicios'],
              ['/admin/pagos', 'pagos'],
              ['/admin/disenar', 'diseno-clientes'],
              ['/admin/paginas', 'servicios'],
              ['/admin/configuracion', 'configuracion'],
              ['/admin/auditoria', 'auditoria'],
              ['/admin/estadisticas', 'estadisticas'],
              ['/settings', 'cuenta'],
              ['/admin', 'estadisticas'],
          ]
        : [
              ['/panel/productos', 'productos'],
              ['/panel/estadisticas', 'estadisticas'],
              ['/panel/fidelidad', 'fidelidad'],
              ['/panel/paginas', 'pagina-enlaces'],
              ['/panel/servicios', 'servicios'],
              ['/settings', 'cuenta'],
              ['/panel', 'primeros-pasos'],
          ];

    return mapa.find(([prefijo]) => ruta.startsWith(prefijo))?.[1] ?? null;
}
