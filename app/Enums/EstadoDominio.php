<?php

namespace App\Enums;

/**
 * Ciclo de vida de un dominio personalizado. Un dominio solo se considera
 * operativo («activo») cuando el DNS demostró la propiedad, apunta a DASTI
 * y la comprobación HTTPS respondió con el token correcto.
 */
enum EstadoDominio: string
{
    use EnumConEtiqueta;

    case Pendiente = 'pendiente';
    case DnsVerificado = 'dns_verificado';
    case Activo = 'activo';
    case Error = 'error';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente de configurar',
            self::DnsVerificado => 'DNS verificado · activando HTTPS',
            self::Activo => 'Activo con HTTPS',
            self::Error => 'Requiere atención',
        };
    }

    /**
     * El DNS ya demostró propiedad y destino: se puede emitir el certificado
     * y servir la página en ese host.
     */
    public function enrutable(): bool
    {
        return $this === self::DnsVerificado || $this === self::Activo;
    }
}
