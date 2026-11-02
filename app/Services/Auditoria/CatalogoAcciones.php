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
        'usuario.rol_cambiado' => 'Rol de usuario cambiado',
        'rol.creado' => 'Alta de rol',
        'rol.actualizado' => 'Rol actualizado',
        'rol.eliminado' => 'Rol eliminado',
        'apariencia.actualizada' => 'Apariencia actualizada',
        'apariencia.restablecida' => 'Apariencia restablecida',
        'servicio.activado' => 'Servicio activado',
        'servicio.suspendido' => 'Servicio suspendido',
        'servicio.suspendido_automaticamente' => 'Servicio suspendido automáticamente',
        'servicio.reactivado' => 'Servicio reactivado',
        'servicio.restringido' => 'Servicio restringido',
        'servicio.restriccion_levantada' => 'Restricción de servicio levantada',
        'servicio.cancelado' => 'Periodo de servicio cancelado',
        'servicio.vencido' => 'Servicio vencido',
        'servicio.configurado' => 'Configuración comercial de servicio',
        'pago.iniciado' => 'Pago en línea iniciado',
        'pago.confirmado' => 'Pago confirmado',
        'pago.rechazado_validacion' => 'Pago rechazado por validación',
        'pago.reembolsado' => 'Pago reembolsado',
        'pago.reembolso_parcial' => 'Reembolso parcial',
        'pago.disputa_abierta' => 'Disputa de pago abierta',
        'pago.disputa_ganada' => 'Disputa ganada',
        'pago.disputa_perdida' => 'Disputa perdida',
        'pago.revisado' => 'Incidencia de pago revisada',
        'fidelidad.campana_creada' => 'Alta de campaña',
        'fidelidad.campana_actualizada' => 'Campaña actualizada',
        'fidelidad.campana_estado' => 'Estado de campaña',
        'fidelidad.consumidor_registrado' => 'Alta de consumidor',
        'fidelidad.consumidor_actualizado' => 'Consumidor actualizado',
        'fidelidad.correo_verificado' => 'Correo de consumidor verificado',
        'fidelidad.credencial_emitida' => 'Credencial emitida',
        'fidelidad.credencial_revocada' => 'Credencial revocada',
        'fidelidad.enlace_emitido' => 'Enlace digital emitido',
        'fidelidad.enlace_revocado' => 'Enlace digital revocado',
        'fidelidad.consumidores_fusionados' => 'Cuentas de consumidor fusionadas',
        'fidelidad.visita_registrada' => 'Visita registrada',
        'fidelidad.meta_completada' => 'Meta completada',
        'fidelidad.recompensa_canjeada' => 'Recompensa canjeada',
        'fidelidad.recompensa_anulada' => 'Recompensa anulada',
        'fidelidad.saldo_ajustado' => 'Ajuste de visitas',
        'fidelidad.visita_anulada' => 'Visita anulada',
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
