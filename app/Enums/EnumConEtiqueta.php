<?php

namespace App\Enums;

/**
 * Utilidades compartidas por los enums con etiqueta legible.
 */
trait EnumConEtiqueta
{
    abstract public function etiqueta(): string;

    /**
     * Opciones listas para selects del frontend.
     *
     * @return list<array{value: string, label: string}>
     */
    public static function opciones(): array
    {
        return array_map(
            fn (self $caso) => ['value' => $caso->value, 'label' => $caso->etiqueta()],
            self::cases(),
        );
    }

    /**
     * @return list<string>
     */
    public static function valores(): array
    {
        return array_column(self::cases(), 'value');
    }
}
