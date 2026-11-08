# Manual del administrador (DASTI)

> Dentro de la plataforma está la **Guía de uso** (`/guia`): menú lateral › Guía de uso, o el botón **?** del encabezado, que abre el tema de la pantalla actual. Muestra contenido distinto al personal DASTI y a los negocios.

Entra en `https://redireccion.dasti.cloud/login` con tu cuenta de administrador.

## Crear un cliente (negocio)

1. **Negocios → Nuevo negocio.**
2. Captura el nombre comercial (obligatorio), el responsable, el correo y el teléfono de contacto, y notas internas si hace falta. El cliente nunca ve las notas internas.
3. Deja activo **Crear acceso ahora** y elige el método:
    - **Invitación por correo**: el cliente recibe un enlace para definir su contraseña. El enlace funciona **una sola vez** y caduca a las **72 horas** (`DASTI_HORAS_VALIDEZ_INVITACION`). El correo se envía después de guardar el alta: si el servidor de correo falla, el negocio y el usuario quedan creados, verás un aviso y el usuario aparecerá con «El correo de invitación no pudo enviarse». Cuando el correo funcione, usa **Enviar enlace de acceso**; cada reenvío invalida los enlaces anteriores.
    - **Contraseña temporal**: pulsa _Generar_, cópiala y entrégala por un canal seguro. Al entrar, el sistema le obliga a cambiarla.
4. Guarda. Se abre la ficha del negocio.

Para dar acceso a otro usuario del mismo negocio: en la ficha, **Usuarios → Agregar acceso**. También puedes hacerlo desde **Usuarios y accesos → Nuevo acceso**. Los negocios o usuarios suspendidos no reciben invitaciones.

## Registrar productos y asignarlos

1. En la ficha del negocio, pulsa **Registrar productos** (o **Productos → Registrar producto**).
2. Indica el nombre (p. ej. «Tarjeta NFC mostrador»), el tipo (QR, NFC o QR + NFC) y la **cantidad** (para un lote de 3 se crean «Nombre 1…3»).
3. Opcional: indica el destino inicial, el estado de preparación, la fecha de entrega y la referencia o lote.
4. Cada producto recibe un **enlace permanente** único del tipo `https://redireccion.dasti.cloud/r/abcd2345ef`.

**Cada unidad física tiene su propio código**: un lote de 5 tarjetas crea 5 productos con 5 enlaces distintos, aunque todas apunten al mismo destino. El código nunca cambia (ni al cambiar el destino ni al transferir el producto) y nunca se reutiliza; MariaDB lo garantiza con un índice único.

Al escribir el destino, el formulario lo comprueba con las mismas reglas que se aplican al guardar: solo muestra «Lleva a …» si el servidor lo aceptará.

> Para medir QR y NFC por separado, registra dos productos. El tipo «QR + NFC» comparte un solo enlace y no permite distinguir el canal.

## Copiar la URL para NFC Tools

En **Producto → pestaña NFC** aparece la URL exacta para grabar y los pasos:

1. En NFC Tools, entra a **Escribir → Agregar un registro → URL/URI**.
2. Pega la URL permanente y toca **Escribir**.
3. Acerca la tarjeta. Opcionalmente puedes bloquearla; ese paso es irreversible.
4. Prueba la tarjeta con un teléfono.

El sistema no programa ni verifica tarjetas. Marca el estado **Listo** o **Entregado** de forma manual en _Editar_.

## Descargar el QR

En **Producto → pestaña Código QR** puedes:

- elegir el color del código y del fondo (o fondo transparente) y el margen;
- incluir el logotipo del negocio, si lo cargaste en su ficha (en PNG y en SVG; se usa corrección de errores alta y los módulos bajo el logotipo se recortan);
- descargar en **PNG** (256–2048 px) o **SVG** (vectorial).

Cada QR codifica exactamente la URL permanente de su producto. Si algo impide generarlo (logotipo dañado, contraste insuficiente, permisos o sesión expirada), la vista previa y la descarga muestran el motivo.

Los diseños con poco contraste se bloquean para garantizar la lectura. El QR no lleva el nombre de DASTI; sus tres esquinas (ojos) llevan la insignia de DASTI. Un producto puede tener 1 o 2 QR, cada uno con su propio destino, diseño y estadísticas. Usa _Guardar diseño_ para conservar los ajustes del producto.

## Cambiar el destino

Desde la ficha del producto o desde **Redirecciones → Editar destino**. El enlace permanente no cambia. Cada cambio queda en el historial.

## Suspender, reactivar, transferir o eliminar

- **Suspender producto**: el enlace muestra «no disponible» y el cliente no puede editar el destino. **Reactivar** lo restablece.
- **Suspender negocio** (en su ficha): todos sus enlaces dejan de redirigir y sus usuarios no pueden entrar.
- **Transferir**: elige el negocio destino y escribe el motivo. El negocio anterior pierde el acceso de inmediato y el código no cambia. El nuevo propietario empieza con historial, contadores y estadísticas propios: no ve los destinos ni la actividad del anterior (si conservas el destino, solo ve ese destino como punto de partida). Tú conservas el historial completo y la auditoría registra los valores previos.
- **Eliminar**: solo para productos registrados por error, pendientes y sin accesos. En cualquier otro caso, suspende.
- **Usuarios**: puedes suspender o reactivar, usar **Enviar enlace de acceso** o asignar una contraseña temporal. Suspender, asignar una contraseña temporal o restablecerla cierra las sesiones abiertas del usuario.

## Verificación en dos pasos

En producción (o con `DASTI_EXIGIR_2FA_ADMIN=true`), los administradores deben activar la verificación en dos pasos en **Mi cuenta → Contraseña y seguridad** antes de usar el panel. Guarda los códigos de recuperación en un lugar seguro.

## Estadísticas, auditoría y configuración

- **Panel**: negocios, productos, enlaces, usuarios e interacciones de los últimos 30 días comparados con los 30 previos, reparto NFC/QR, altas por mes, incidencias (enlaces sin destino, accesos que no llegaron a destino, negocios sin actividad, dominios con error, pagos por revisar) y, con permiso, contrataciones e ingresos. Cada bloque aparece solo si tu rol puede consultarlo.
- **Estadísticas › Uso de enlaces**: filtra por periodo (hoy, 7/30/90 días, este mes, mes anterior, 12 meses o fechas propias), negocio, producto y canal. Incluye evolución por día/semana/mes con el periodo anterior, NFC frente a QR, productos y negocios más activos, actividad por día de la semana y por horario, dispositivos, navegadores y productos sin uso. El análisis de toda la plataforma se reutiliza 5 minutos (`DASTI_CACHE_ESTADISTICAS`).
- **Estadísticas › Comercial** (requiere «Servicios · Ver» o «Pagos · Ver»): contrataciones por servicio y estado, nuevas y canceladas, próximas a vencer, e ingresos **cobrados y confirmados** netos de reembolsos. Las órdenes pendientes y los pagos de prueba de Openpay nunca se cuentan como ingreso.
- **Qué no se mide**: no se registra ubicación ni el destino al que llevaba un enlace en cada acceso (el historial de destinos está en cada producto); «interacciones» no son personas, ventas ni conversiones.
- **Auditoría**: historial legible de altas, cambios de destino, suspensiones, transferencias y accesos, con valores antes/después. Puedes filtrarlo.
- **Configuración**:
    - permitir destinos `http`;
    - permitir a los clientes descargar QR;
    - mostrar estadísticas a los clientes;
    - dominios bloqueados;
    - días de retención de eventos.

## Servicios, precios y pagos (Openpay)

- **Servicios contratados › Catálogo y precios**: elige el servicio (Fidelidad o Página de enlaces) y define el precio final en MXN, si incluye IVA, la vigencia (vacía = permanente, pago único), la descripción y los beneficios que ve el cliente. Habilita «Permitir contratación en línea» solo cuando el precio esté definido y Openpay configurado.
- **Activar servicio**: activa un servicio para un negocio sin pago en línea (cortesía o pago fuera de línea con importe y referencia). El motivo es opcional; la auditoría registra siempre quién, cuándo y qué. Solo se activa el servicio elegido.
- **Suspender / reactivar / restringir**: motivo opcional (la auditoría guarda el estado anterior y el nuevo). Nunca afecta las redirecciones QR/NFC ni borra datos.
- **Pagos**: cada orden muestra su folio, intentos de cobro (marca y últimos 4 dígitos de la tarjeta, nunca el número completo), reembolsos y contracargos. Acciones: **Consultar en Openpay** (actualiza el estado real) y **Marcar revisado** (nota opcional) con el permiso «Pagos › Revisar incidencias»; **Reembolsar** (total o parcial, motivo obligatorio) con «Pagos › Reembolsar».
- Reglas automáticas: reembolso total → el servicio se suspende (si no hay otro periodo vigente); reembolso parcial → queda en revisión y se conserva el acceso; contracargo → el servicio queda restringido (solo lectura) hasta que se resuelve.
- Configuración técnica de Openpay: ver `docs/despliegue.md` › «Pagos en línea con Openpay». El código de verificación del webhook aparece en Pagos › Openpay.
- El personal de DASTI ve contrataciones y pagos, nunca los datos personales de los consumidores de Fidelidad ni los mensajes de las Páginas de enlaces.

## Motivos: cuándo son obligatorios

En las operaciones ordinarias (activar, suspender o reactivar servicios, negocios, usuarios y productos; marcar pagos revisados) el motivo es **opcional**: si lo dejas vacío se guarda vacío y las pantallas muestran «No especificado». La auditoría registra siempre el usuario, la fecha y hora, la operación, el registro y el estado anterior y nuevo.

Siguen siendo **obligatorios** por control antifraude o de seguridad:

- transferir un producto a otro negocio (cambia el dueño de un enlace permanente);
- reembolsar un pago (devuelve dinero);
- en Fidelidad: ajustar o anular visitas y recompensas, fusionar consumidores, reemplazar o revocar credenciales y emitir enlaces de recuperación (protegen el saldo y la identidad del consumidor).

## Diseño para clientes

DASTI puede diseñar la tarjeta de Fidelidad o la Página de enlaces de un negocio como servicio adicional.

1. **Servicios › Diseño para clientes**.
2. Busca y elige el negocio.
3. Pulsa **Abrir editor** en «Diseñar Fidelidad» o «Diseñar Página de enlaces».
4. El editor muestra arriba «Estás diseñando para «Negocio»». Guarda y publica como lo haría el cliente.

- Requiere el permiso «Diseñar para negocios» del módulo correspondiente (el rol Administrador lo tiene; el superadministrador puede darlo a otros roles).
- El negocio debe tener el servicio contratado: para guardar o publicar, activo y vigente. Un permiso nunca sustituye la contratación.
- La dirección pública, los dominios, los mensajes y las estadísticas de la página los administra el negocio.
- Cada apertura, guardado y publicación queda en la auditoría del negocio con tu nombre y la marca «Realizado por personal DASTI». Las operaciones anteriores conservan a su autor.

## Mostrar u ocultar «Mis servicios» a los clientes

El permiso **Servicios · Ver** (`servicios.ver`) controla si las cuentas de un negocio ven el apartado **Mis servicios** (catálogo, estado y contratación en línea).

- **Política inicial**: ningún rol de cliente lo tiene. Los clientes no ven el apartado ni pueden abrirlo por URL ni iniciar pagos; sus productos QR/NFC, estadísticas, Fidelidad y Página de enlaces contratados siguen funcionando igual.
- **Habilitarlo**: Roles y permisos › rol «Cliente» (u otro rol de negocio) › Editar › Servicios › marca **Ver** (y **Contratar** si deben poder pagar en línea) › Guardar. Aplica en la siguiente acción de cada usuario, aunque tenga la sesión abierta.
- **Deshabilitarlo**: desmarca **Ver** en el mismo rol.
- Para el personal DASTI este mismo permiso sigue significando «ver servicios y contrataciones» en la administración; no cambia.

## Roles y permisos

**Administración › Roles y permisos** muestra el catálogo completo, separado en «Administración de la plataforma» y «Servicios y operación de los negocios». Puedes buscar permisos, ver solo los marcados y abrir la ayuda de cada módulo. Los permisos que no aplican al tipo de cuenta del rol aparecen como referencia en «permisos no aplican a este tipo de cuenta».

- Nadie otorga ni retira permisos que no tiene; solo un superadministrador gestiona el rol Superadministrador, y siempre queda al menos uno activo.
- Los permisos de negocio nunca salen del negocio del usuario.
- Permisos separados en esta versión: «Pagos › Revisar incidencias», «Pagos › Reembolsar» y «Página de enlaces › Consultar estadísticas». Los roles existentes los recibieron automáticamente si tenían el permiso equivalente anterior.
