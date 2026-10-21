<?php

namespace App\Services\Auditoria;

/**
 * Nombres legibles de las acciones auditadas.
 */
final class CatalogoAcciones
{
    public const ETIQUETAS = [
        'negocio.creado' => 'Alta de negocio',
        'negocio.actualizado' => 'Datos de negocio actualizados',
        'negocio.suspendido' => 'Negocio suspendido',
        'negocio.reactivado' => 'Negocio reactivado',
        'negocio.logo_actualizado' => 'Logotipo actualizado',
        'negocio.logo_eliminado' => 'Logotipo eliminado',
        'usuario.creado' => 'Alta de usuario',
        'usuario.actualizado' => 'Usuario actualizado',
        'usuario.suspendido' => 'Usuario suspendido',
        'usuario.reactivado' => 'Usuario reactivado',
        'usuario.invitacion_enviada' => 'Enlace de acceso enviado',
        'usuario.contrasena_temporal' => 'Contraseña temporal asignada',
        'usuario.contrasena_inicial' => 'Contraseña personal definida',
        'usuario.contrasena_restablecida' => 'Contraseña restablecida',
        'producto.creado' => 'Alta de producto',
        'producto.actualizado' => 'Producto actualizado',
        'producto.destino_cambiado' => 'Cambio de destino',
        'producto.suspendido' => 'Producto suspendido',
        'producto.reactivado' => 'Producto reactivado',
        'producto.transferido' => 'Producto transferido',
        'producto.recibido' => 'Producto recibido por transferencia',
        'producto.eliminado' => 'Producto eliminado',
        'configuracion.actualizada' => 'Configuración actualizada',
    ];

    public static function etiqueta(string $accion): string
    {
        return self::ETIQUETAS[$accion] ?? $accion;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function opciones(): array
    {
        return array_map(
            fn (string $valor, string $etiqueta) => ['value' => $valor, 'label' => $etiqueta],
            array_keys(self::ETIQUETAS),
            self::ETIQUETAS,
        );
    }
}
