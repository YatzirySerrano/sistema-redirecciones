<?php

namespace App\Services\Redirecciones;

use App\Models\Producto;
use RuntimeException;

/**
 * Genera identificadores públicos aleatorios para los enlaces permanentes.
 *
 * Usa random_int (CSPRNG). Con el alfabeto de 31 símbolos y longitud 10 hay
 * ~8.2 × 10^14 combinaciones, lo que hace impráctico adivinar enlaces.
 */
class GeneradorCodigo
{
    public function generar(): string
    {
        $alfabeto = (string) config('dasti.codigo.alfabeto');
        $longitud = (int) config('dasti.codigo.longitud');
        $maximo = strlen($alfabeto) - 1;

        for ($intento = 0; $intento < 10; $intento++) {
            $codigo = '';

            for ($i = 0; $i < $longitud; $i++) {
                $codigo .= $alfabeto[random_int(0, $maximo)];
            }

            if (! Producto::query()->where('codigo', $codigo)->exists()) {
                return $codigo;
            }
        }

        throw new RuntimeException('No fue posible generar un código único para el enlace permanente.');
    }

    /**
     * Comprueba si un texto tiene la forma de un código válido, para descartar
     * solicitudes basura sin consultar la base de datos.
     */
    public function tieneFormatoValido(string $codigo): bool
    {
        $alfabeto = preg_quote((string) config('dasti.codigo.alfabeto'), '/');

        return (bool) preg_match('/^['.$alfabeto.']{6,16}$/', $codigo);
    }
}
