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
        'usuario.contrasena_cambiada' => 'Contraseña cambiada por el usuario',
        'usuario.perfil_actualizado' => 'Perfil actualizado por el usuario',
        'usuario.invitacion_fallida' => 'Envío de invitación fallido',
        'usuario.invitacion_aceptada' => 'Invitación aceptada',
        'usuario.2fa_activada' => 'Verificación en dos pasos activada',
        'usuario.2fa_desactivada' => 'Verificación en dos pasos desactivada',
        'usuario.codigos_recuperacion' => 'Códigos de recuperación regenerados',
        'sesion.iniciada' => 'Inicio de sesión',
        'sesion.bloqueada' => 'Inicio de sesión bloqueado por intentos fallidos',
        'producto.creado' => 'Alta de producto',
        'producto.actualizado' => 'Producto actualizado',
        'producto.destino_cambiado' => 'Cambio de destino',
        'producto.suspendido' => 'Producto suspendido',
        'producto.reactivado' => 'Producto reactivado',
        'producto.transferido' => 'Producto transferido',
        'producto.recibido' => 'Producto recibido por transferencia',
        'producto.eliminado' => 'Producto eliminado',
        'producto.qr_actualizado' => 'Diseño de QR actualizado',
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
