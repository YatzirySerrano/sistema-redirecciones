<?php

namespace App\Services\Usuarios;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Cierra las sesiones abiertas de un usuario tras un cambio de credenciales
 * o una suspensión, para que una sesión robada no siga activa.
 *
 * Con SESSION_DRIVER=database (el configurado) se eliminan las filas de la
 * tabla de sesiones; con cualquier driver se rota el token «recordarme».
 */
class SesionesUsuario
{
    /**
     * @param  string|null  $conservar  ID de la sesión actual que debe seguir abierta.
     */
    public function cerrarTodas(User $usuario, ?string $conservar = null): void
    {
        $usuario->forceFill(['remember_token' => Str::random(60)])->saveQuietly();

        if (config('session.driver') !== 'database') {
            return;
        }

        DB::connection(config('session.connection'))
            ->table((string) config('session.table', 'sessions'))
            ->where('user_id', $usuario->id)
            ->when($conservar !== null, fn ($q) => $q->where('id', '!=', $conservar))
            ->delete();
    }
}
