# Manual del administrador (DASTI)

Entra en `https://redireccion.dasti.cloud/login` con tu cuenta de administrador.

## Crear un cliente (negocio)

1. **Negocios → Nuevo negocio.**
2. Captura el nombre comercial (obligatorio), el responsable, el correo y el teléfono de contacto, y notas internas si hace falta. El cliente nunca ve las notas internas.
3. Deja activo **Crear acceso ahora** y elige el método:
    - **Invitación por correo**: el cliente recibe un enlace para definir su contraseña.
    - **Contraseña temporal**: pulsa _Generar_, cópiala y entrégala por un canal seguro. Al entrar, el sistema le obliga a cambiarla.
4. Guarda. Se abre la ficha del negocio.

Para dar acceso a otro usuario del mismo negocio: en la ficha, **Usuarios → Agregar acceso**. También puedes hacerlo desde **Usuarios y accesos → Nuevo usuario**.

## Registrar productos y asignarlos

1. En la ficha del negocio, pulsa **Registrar productos** (o **Productos → Registrar producto**).
2. Indica el nombre (p. ej. «Tarjeta NFC mostrador»), el tipo (QR, NFC o QR + NFC) y la **cantidad** (para un lote de 3 se crean «Nombre 1…3»).
3. Opcional: indica el destino inicial, el estado de preparación, la fecha de entrega y la referencia o lote.
4. Cada producto recibe un **enlace permanente** único del tipo `https://redireccion.dasti.cloud/r/abcd2345ef`.

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
- incluir el logotipo del negocio, si lo cargaste en su ficha;
- descargar en **PNG** (256–2048 px) o **SVG**.

Los diseños con poco contraste se bloquean para garantizar la lectura. El QR nunca lleva marca de DASTI. Usa _Guardar diseño_ para conservar los ajustes del producto.

## Cambiar el destino

Desde la ficha del producto o desde **Redirecciones → Editar destino**. El enlace permanente no cambia. Cada cambio queda en el historial.

## Suspender, reactivar, transferir o eliminar

- **Suspender producto**: el enlace muestra «no disponible» y el cliente no puede editar el destino. **Reactivar** lo restablece.
- **Suspender negocio** (en su ficha): todos sus enlaces dejan de redirigir y sus usuarios no pueden entrar.
- **Transferir**: elige el negocio destino y escribe el motivo. El negocio anterior pierde el acceso de inmediato. Opcionalmente puedes conservar el destino.
- **Eliminar**: solo para productos registrados por error, pendientes y sin accesos. En cualquier otro caso, suspende.
- **Usuarios**: puedes suspender o reactivar, reenviar el enlace de acceso o asignar una contraseña temporal.

## Estadísticas, auditoría y configuración

- **Estadísticas**: filtra por periodo, negocio y producto. Muestra accesos (sin bots identificados), visitantes estimados, productos y negocios más activos, y desglose por dispositivo, navegador y sistema operativo.
- **Auditoría**: historial legible de altas, cambios de destino, suspensiones, transferencias y accesos, con valores antes/después. Puedes filtrarlo.
- **Configuración**:
    - permitir destinos `http`;
    - permitir a los clientes descargar QR;
    - mostrar estadísticas a los clientes;
    - dominios bloqueados;
    - días de retención de eventos.
