# Arquitectura

## Stack

| Capa          | Tecnología                                                                                                                     |
| ------------- | ------------------------------------------------------------------------------------------------------------------------------ |
| Backend       | Laravel 13 · PHP 8.4 · Fortify (login, recuperación, 2FA)                                                                      |
| Frontend      | Vue 3 + TypeScript · Inertia 3 · Tailwind CSS 4 · shadcn-vue (reka-ui) · Lucide · Vite (vite-plus) · Wayfinder (rutas tipadas) |
| Base de datos | MariaDB (InnoDB, utf8mb4) en desarrollo, pruebas y producción                                                                  |
| QR            | `endroid/qr-code` 6 (PNG/SVG). En desarrollo, `khanamiryan/qrcode-detector-decoder` para comprobar la lectura en las pruebas   |

No se usan Redis, Docker ni servicios externos de rastreo.

## Organización

```
Ruta → Controlador delgado → Form Request → Acción / Servicio → Modelo
```

- `routes/web.php` (inicio, QR compartido, cambio de contraseña), `routes/admin.php`, `routes/cliente.php`, `routes/settings.php`, `routes/redirecciones.php` (público, **sin** el grupo `web`: sin sesión ni cookies).
- `app/Actions/{Negocios,Usuarios,Productos}`: reglas de negocio transaccionales y auditadas (`CrearNegocio`, `CrearUsuario`, `CrearProducto`, `CambiarDestinoProducto`, `TransferirProducto`, `CambiarEstado*`, `EliminarProducto`, `GuardarOpcionesQr`…).
- `app/Services`:
    - `Redirecciones/GeneradorCodigo`: códigos aleatorios con CSPRNG.
    - `Redirecciones/ValidadorUrlDestino`: política de destinos.
    - `Qr/GeneradorQr` y `Qr/OpcionesQr`.
    - `Estadisticas/{ClasificadorAgente,ConsultasEstadisticas,FiltroEstadisticas}`.
    - `Auditoria/RegistradorAuditoria`.
    - `Configuracion/AjustesPlataforma`.
- `app/Http/Requests`: validación (Form Requests); `app/Http/Resources`: forma exacta de las props de Inertia (los campos internos solo se envían al administrador).
- `app/Policies/ProductoPolicy` + gates `administrar-plataforma`, `usar-panel-cliente` y `ver-estadisticas-cliente`.
- Middleware `VerificarCuentaActiva` (expulsa a usuarios o negocios suspendidos) y `RequerirContrasenaDefinitiva` (obliga a cambiar la contraseña temporal).
- `resources/js/pages/{admin,cliente,auth,settings}`; componentes compartidos en `resources/js/components/{comun,productos,graficas}`.

## Esquema de base de datos

```
negocios 1─┬─< users              (rol admin|cliente, estado, negocio_id, debe_cambiar_password, invitacion_*_en)
           ├─< productos           (codigo único, tipo, url_destino, estado, preparación, opciones_qr, contadores)
           │      ├─< cambios_destino   (historial inmutable: anterior → nueva, origen, autor, negocio de ese momento)
           │      ├─< eventos_acceso    (detalle mínimo por acceso: dispositivo, navegador, SO, bot, huella diaria)
           │      └─< accesos_diarios   (agregado producto+negocio+día; único)
           └─< registros_auditoria (actor, negocio, recurso polimórfico, acción, antes/después, IP del actor)
configuraciones (clave/valor JSON de ajustes de la plataforma)
```

- Claves foráneas con `restrict` sobre `negocios`: un negocio con productos o usuarios no se puede borrar.
- Aislamiento multiempresa lógico por `negocio_id`. Un negocio puede tener varios usuarios.
- `eventos_acceso` y `accesos_diarios` guardan el `negocio_id` vigente en el momento del acceso. Así, tras una transferencia, cada negocio solo ve su propia historia.

## Flujo de redirección (`GET /r/{codigo}`)

1. Normaliza el código a minúsculas y descarta formatos inválidos sin consultar la base.
2. Busca el producto (índice único) junto con el estado de su negocio.
3. Evalúa, en orden: negocio suspendido → producto suspendido → sin destino → redirigir.
4. Programa `RegistrarEventoAcceso` _después_ de enviar la respuesta con `defer()` (o en cola si `DASTI_EVENTOS_EN_COLA=true`). Las solicitudes `HEAD` no se registran.
5. Responde `302` con `Cache-Control: no-store` y `Referrer-Policy: no-referrer`. En los casos no disponibles (código inválido o inexistente, suspendido, sin destino, límite superado) devuelve una página neutra (`404`/`410`/`429`) sin datos del negocio.
6. Límite de 120 solicitudes por minuto e IP (configurable).

El servidor **nunca** descarga ni consulta el destino.

### Identificadores

Cada producto es una unidad física con su propio `codigo` (10 caracteres de un alfabeto de 31 símbolos sin ambiguos, `random_int`). `productos.codigo` es UNIQUE en MariaDB; si dos altas simultáneas generan el mismo código, `CrearProducto` reintenta el INSERT con uno nuevo dentro de un savepoint. El código no es asignable masivamente y no cambia al modificar el destino ni al transferir. El tipo «combinado» (QR + NFC) es una sola unidad lógica con un único enlace.

### Política de destinos (`ValidadorUrlDestino`)

El formulario consulta `POST /destinos/validacion` (mismo `ValidadorUrlDestino`) para mostrar el resultado en vivo; al guardar se vuelve a validar.

| Se acepta                                                                                              | Se rechaza                                                                                                           |
| ------------------------------------------------------------------------------------------------------ | -------------------------------------------------------------------------------------------------------------------- |
| Solo `https` (o también `http` si se activa en Configuración), con un dominio público y TLD alfabético | Otros esquemas (`javascript:`, `data:`, `file:`, `ftp:`…) y credenciales en la URL                                   |
| Dominios internacionales (IDN), que se normalizan a Punycode y codificación ASCII                      | IPs literales (incluidas las formas decimal y hexadecimal), `localhost`, `.local`, `.internal` y similares           |
|                                                                                                        | Puertos distintos de 80/443, el propio dominio del servicio (bucles) y la lista de dominios bloqueados (acortadores) |
|                                                                                                        | Más de 2048 caracteres                                                                                               |

## Autorización

| Recurso                | Administrador | Cliente                                                  |
| ---------------------- | ------------- | -------------------------------------------------------- |
| Panel `/admin/*`       | Sí            | 403                                                      |
| Ver producto           | Todos         | Solo de su negocio (ajenos → **404**)                    |
| Cambiar destino        | Sí            | Solo de su negocio y si producto y negocio están activos |
| Descargar o diseñar QR | Sí            | De su negocio, si «Clientes descargan QR» está activo    |
| Estadísticas           | Todas         | Las de su negocio, si el ajuste lo permite               |

Guardar el diseño del QR exige además, para el cliente, que el producto y su negocio estén activos (`ProductoPolicy::disenarQr`). La autorización se resuelve antes de validar parámetros, de modo que un producto ajeno responde siempre 404. Los administradores deben tener la verificación en dos pasos activa si `dasti.exigir_2fa_administradores` (por defecto en producción).

### Transferencias

Cada registro histórico (`cambios_destino`, `eventos_acceso`, `accesos_diarios`) guarda el negocio propietario en ese momento, así que las consultas del cliente, filtradas por su `negocio_id`, solo ven su periodo de propiedad. Al transferir, el retiro del destino se registra en el negocio de origen, `total_accesos` y `ultimo_acceso_en` reinician, y la auditoría conserva los valores previos para el administrador.

La interfaz oculta lo que no corresponde, pero la seguridad se aplica siempre en el backend (policies, gates, form requests y consultas filtradas por `negocio_id`). Los campos sensibles (`negocio_id`, `codigo`, `estado`, `rol`) no son asignables masivamente.

## Estadísticas y privacidad

- No se guardan la IP ni el User-Agent completos. Solo se guarda:
    - la categoría de dispositivo, el navegador y el sistema operativo;
    - un indicador de bot (heurístico, no perfecto);
    - una _huella diaria_ `HMAC(IP + agente + día, APP_KEY)`, que cambia cada día y se borra a los 30 días.
- «Visitantes estimados» = huellas distintas por día. No representa personas exactas. Como las huellas se eliminan a los 30 días, en periodos más largos la estimación se marca como parcial (`visitantes_desde`) y, si el periodo es completamente anterior, se muestra «No disponible» en lugar de 0.
- Con «Clientes ven estadísticas» desactivado, el cliente tampoco recibe los contadores de accesos.
- QR y NFC que comparten enlace (tipo «combinado») **no** se pueden distinguir. Si hace falta, registra dos productos.
- `dasti:depurar-eventos` (programada a diario) anonimiza las huellas y borra los eventos más antiguos que la retención configurada. Los totales diarios se conservan.

## Productos QR + NFC: destino compartido o independiente

- **Compartido** (comportamiento original y valor de todos los productos existentes): un único enlace permanente para el chip NFC y el QR.
- **Independiente**: el producto físico (fila principal de `productos`, `medio = nfc`, frente) tiene un **enlace secundario** (otra fila con `producto_principal_id`, `medio = qr`, reverso). Cada enlace tiene su código, destino, historial y estadísticas; el motor `/r/{codigo}` no cambia.
- Los enlaces secundarios no son unidades vendidas: los listados de productos y los contadores usan `Producto::principales()`. Redirecciones y estadísticas los muestran con su medio.
- Estado, transferencia, datos de preparación y eliminación se aplican al producto físico completo. La modalidad se elige al crear el producto (no se convierten productos existentes).

## Aplicación web progresiva

`public/manifest.webmanifest`, `public/sw.js`, `public/offline.html`, `resources/js/lib/pwa.ts`. El Service Worker solo guarda recursos públicos (página sin conexión, íconos y `/build/assets/*`); las navegaciones siempre van a la red y nunca intercepta `/r/`, `/f/`, `/mi-fidelidad`, `/stripe`, autenticación ni métodos distintos de GET. Las versiones nuevas esperan a que el usuario pulse «Actualizar».

## Página de enlaces

Servicio adicional (`servicios.clave = pagina_enlaces`), contratado aparte de Fidelidad. Cada ruta del panel exige el permiso (`paginas.*`) **y** el servicio (`RequiereServicio:pagina_enlaces[,operar]`).

### Datos

```
negocios 1─1 paginas_enlaces          (slug único, estado, borrador y publicado como JSON validado, version)
              ├─< paginas_enlaces_versiones  (copias: publicación, restauración, guardados; se conservan 30)
              ├─< paginas_enlaces_alias      (direcciones anteriores: 301 a la actual y reservadas)
              ├─< paginas_enlaces_dominios   (host, token TXT, estado, host_verificado único)
              ├─< paginas_enlaces_eventos    (visita/clic/contacto: fecha, hora, elemento, subpágina, dispositivo, host de procedencia, huella diaria)
              ├─< paginas_enlaces_diarios    (totales por día, tipo y elemento; se conservan)
              └─< paginas_enlaces_mensajes   (formulario de contacto; solo los ve el negocio)
```

Una página por negocio con varias **subpáginas** dentro del documento. Cada elemento tiene un `id` UUID estable: las estadísticas sobreviven a las ediciones y al reordenamiento.

### Documento y validación (`App\Services\Paginas\DocumentoPagina`)

Lista blanca estricta, la misma al guardar el borrador, publicar, restaurar y aplicar plantillas: claves desconocidas descartadas, colores solo `#rrggbb`, números acotados, textos sin etiquetas HTML y con longitud máxima, URL validadas con `ValidadorUrlDestino`, WhatsApp/teléfono/correo construidos en el servidor, insertados reducidos a proveedor + identificador (`Insertados`), imágenes y videos solo como UUID de archivos del propio negocio (`archivos_publicos`), límites de páginas, elementos y tamaño. Los errores se devuelven por elemento (`bloques.{id}.{campo}`). El borrador admite elementos incompletos; la publicación exige los datos obligatorios.

Concurrencia optimista: cada guardado envía `version`; si otra pestaña guardó antes, el servidor responde 409 y el editor ofrece recargar o sobrescribir.

### Renderizado

`resources/js/components/paginas/RenderizadorPagina.vue` es el **único** renderizador: lo usan la vista previa del editor y la página pública (`resources/js/publico/pagina.ts`). Usa consultas de contenedor (`@container`), variables CSS acotadas a `.pe-raiz` y nunca `v-html` (el formato enriquecido se convierte en segmentos de texto).

### Página pública (`/l/{slug}[/{subpagina}]`, sin sesión ni cookies)

1. Resuelve el slug (o un alias → 301). Negocio suspendido o servicio no vigente → 410; sin publicar → 404 (página neutra, `noindex`, sin marca).
2. `DocumentoPagina::paraPublico` quita lo oculto o fuera de su periodo programado y los destinos (solo queda la ruta `/ir/{id}`).
3. Blade (`resources/views/paginas/publica.blade.php`) emite título, descripción, Open Graph/Twitter, URL canónica (dominio principal activo o la de DASTI), favicon del negocio y el JSON incrustado (`@json`, etiquetas escapadas); CSP con nonce, `frame-src` limitado a los proveedores permitidos y `font-src` a fonts.bunny.net.
4. Clics: `/ir/{id}` registra y redirige (302, `no-store`) al destino **actual publicado**; `tel:`/`mailto:` se abren directo y avisan con `sendBeacon` (POST a la misma ruta).
5. Eventos tras responder (`defer`), sin IP ni User-Agent completos; bots marcados y fuera de los totales; precargas ignoradas.

### Dominios personalizados

`GestorDominios` (TXT `_dasti-verificacion.<host>` + CNAME/A hacia DASTI + HTTPS forzado a las IP de entrada), `ResolvedorDns` y `ComprobadorHttps` como interfaces (simuladas en las pruebas), `EnrutarDominiosPagina` (middleware global: un host ajeno solo sirve la página de su negocio) y `/interno/dominios/autorizar` para `on_demand_tls` de Caddy. Ver «Página de enlaces y dominios personalizados» en `despliegue.md`.

### Como destino de QR/NFC

`ValidadorUrlDestino` mantiene el bloqueo del dominio propio con una sola excepción: `https://<url_publica>/l/{slug}[/{subpagina}]` de una página **publicada**, sin parámetros. El cambio pasa por `CambiarDestinoProducto` (historial y auditoría habituales).
