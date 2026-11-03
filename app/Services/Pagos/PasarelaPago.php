<?php

namespace App\Services\Pagos;

use App\Models\IntentoPago;
use App\Models\Pago;

/**
 * Proveedor de pagos en línea (hoy: Openpay, formulario alojado). La
 * interfaz aísla el dominio (órdenes, contrataciones) del proveedor y
 * permite simularlo en pruebas.
 *
 * Regla: el estado de un cobro SIEMPRE se obtiene consultando al proveedor
 * desde el servidor (consultarCargo); nunca de lo que diga el navegador ni
 * del contenido de una notificación.
 */
interface PasarelaPago
{
    public function clave(): string;

    public function configurada(): bool;

    /**
     * ¿Opera en el ambiente de producción (cargos reales)?
     */
    public function modoReal(): bool;

    /**
     * Crea el cargo en el proveedor con el importe y la moneda del intento
     * (fijados por el servidor) y devuelve el cargo con la URL del
     * formulario de pago.
     *
     * @param  array{nombre: string, apellidos: string, email: string, telefono: string|null}  $cliente
     *
     * @throws ErrorPasarela
     */
    public function crearCargo(Pago $pago, IntentoPago $intento, array $cliente, string $urlRetorno): CargoExterno;

    /**
     * @throws ErrorPasarela
     */
    public function consultarCargo(string $transaccionId): CargoExterno;

    /**
     * Busca el cargo por la referencia interna (recuperación si se perdió
     * la respuesta al crearlo).
     *
     * @throws ErrorPasarela
     */
    public function buscarPorOrden(string $ordenExterna): ?CargoExterno;

    /**
     * Solicita un reembolso total (null) o parcial (centavos).
     *
     * @throws ErrorPasarela
     */
    public function reembolsar(string $transaccionId, ?int $centavos, string $descripcion): CargoExterno;
}
