<?php

namespace App\Services\Estadisticas;

use Illuminate\Support\Facades\DB;

/**
 * Expresiones SQL de partes de fecha, portables entre MariaDB/MySQL y
 * SQLite. Las fechas se guardan en la zona horaria de la aplicación
 * (America/Mexico_City), así que la hora y el día que devuelven ya son
 * locales; no se aplica conversión adicional.
 */
final class SqlFechas
{
    private static function esSqlite(): bool
    {
        return DB::connection()->getDriverName() === 'sqlite';
    }

    /**
     * Día de la semana ISO: 1 = lunes … 7 = domingo.
     *
     * @param  literal-string  $columna
     * @return literal-string
     */
    public static function diaSemana(string $columna): string
    {
        return self::esSqlite()
            ? "((CAST(strftime('%w', {$columna}) AS INTEGER) + 6) % 7 + 1)"
            : "((DAYOFWEEK({$columna}) + 5) % 7 + 1)";
    }

    /**
     * Hora local 0–23.
     *
     * @param  literal-string  $columna
     * @return literal-string
     */
    public static function hora(string $columna): string
    {
        return self::esSqlite()
            ? "CAST(strftime('%H', {$columna}) AS INTEGER)"
            : "HOUR({$columna})";
    }

    /**
     * Mes en formato YYYY-MM.
     *
     * @param  literal-string  $columna
     * @return literal-string
     */
    public static function mes(string $columna): string
    {
        return self::esSqlite()
            ? "strftime('%Y-%m', {$columna})"
            : "DATE_FORMAT({$columna}, '%Y-%m')";
    }
}
