<?php

namespace App\Services\Auditoria;

use App\Models\Negocio;
use App\Models\RegistroAuditoria;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

/**
 * Registra operaciones sensibles en la bitácora de auditoría.
 *
 * Nunca guarda contraseñas, tokens ni secretos: las claves sensibles se
 * eliminan de los valores antes de persistirlos.
 */
class RegistradorAuditoria
{
    private const CLAVES_SENSIBLES = [
        'password', 'password_confirmation', 'current_password', 'remember_token',
        'two_factor_secret', 'two_factor_recovery_codes', 'token',
    ];

    public function __construct(private readonly Request $request) {}

    /**
     * @param  array<string, mixed>|null  $anteriores
     * @param  array<string, mixed>|null  $nuevos
     */
    public function registrar(
        string $accion,
        string $descripcion,
        ?Model $recurso = null,
        Negocio|int|null $negocio = null,
        ?array $anteriores = null,
        ?array $nuevos = null,
        ?User $actor = null,
    ): RegistroAuditoria {
        $actor ??= $this->request->user();

        return RegistroAuditoria::query()->create([
            'user_id' => $actor?->getKey(),
            'negocio_id' => $negocio instanceof Negocio ? $negocio->getKey() : $negocio,
            'auditable_type' => $recurso?->getMorphClass(),
            'auditable_id' => $recurso?->getKey(),
            'accion' => $accion,
            'descripcion' => mb_substr($descripcion, 0, 255),
            'valores_anteriores' => $this->limpiar($anteriores),
            'valores_nuevos' => $this->limpiar($nuevos),
            'ip' => $this->request->ip(),
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $valores
     * @return array<string, mixed>|null
     */
    private function limpiar(?array $valores): ?array
    {
        if ($valores === null) {
            return null;
        }

        $limpios = Arr::except($valores, self::CLAVES_SENSIBLES);

        foreach ($limpios as $clave => $valor) {
            if ($valor instanceof \BackedEnum) {
                $limpios[$clave] = $valor->value;
            } elseif ($valor instanceof \DateTimeInterface) {
                $limpios[$clave] = $valor->format('Y-m-d H:i:s');
            }
        }

        return $limpios === [] ? null : $limpios;
    }
}
