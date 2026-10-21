# Manual del administrador (DASTI)

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

Los diseños con poco contraste se bloquean para garantizar la lectura. El QR nunca lleva marca de DASTI. Usa _Guardar diseño_ para conservar los ajustes del producto.

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

- **Estadísticas**: filtra por periodo, negocio y producto. Muestra accesos (sin bots identificados), visitantes estimados, productos y negocios más activos, y desglose por dispositivo, navegador y sistema operativo.
- **Auditoría**: historial legible de altas, cambios de destino, suspensiones, transferencias y accesos, con valores antes/después. Puedes filtrarlo.
- **Configuración**:
    - permitir destinos `http`;
    - permitir a los clientes descargar QR;
    - mostrar estadísticas a los clientes;
    - dominios bloqueados;
    - días de retención de eventos.
