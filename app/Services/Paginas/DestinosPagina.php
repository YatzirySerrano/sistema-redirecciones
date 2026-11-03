<?php

namespace App\Services\Paginas;

use App\Models\Negocio;
use App\Models\Paginas\PaginaEnlaces;
use App\Models\Servicio;
use App\Services\Servicios\AccesoServicios;

/**
 * Direcciones de la Página de enlaces publicada que un negocio puede usar
 * como destino de sus QR/NFC (sugerencias del formulario de destino). El
 * cambio en sí pasa por el flujo autorizado CambiarDestinoProducto.
 */
class DestinosPagina
{
    public function __construct(private readonly AccesoServicios $acceso) {}

    /**
     * @return list<array{etiqueta: string, url: string}>
     */
    public function paraNegocio(Negocio $negocio): array
    {
        if (! $this->acceso->tiene($negocio, Servicio::PAGINA_ENLACES)) {
            return [];
        }

        $pagina = PaginaEnlaces::query()->with('dominios')->where('negocio_id', $negocio->id)->first();

        if ($pagina === null || ! $pagina->estaPublicada()) {
            return [];
        }

        $destinos = [['etiqueta' => 'Mi página de enlaces', 'url' => $pagina->urlPublica()]];

        foreach ($pagina->dominios as $dominio) {
            if ($dominio->estaActivo()) {
                $destinos[] = ['etiqueta' => 'Mi página en '.$dominio->host_unicode, 'url' => 'https://'.$dominio->host.'/'];
            }
        }

        return $destinos;
    }
}
