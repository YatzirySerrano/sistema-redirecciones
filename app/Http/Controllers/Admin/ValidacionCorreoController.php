<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Comprobación en vivo de un correo de acceso (formato y duplicado) para los
 * formularios del panel de DASTI. Solo está disponible para el personal de
 * DASTI (gate administrar-plataforma), nunca en formularios públicos, para
 * no revelar qué cuentas existen.
 */
class ValidacionCorreoController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'email' => ['required', 'string', 'max:255'],
            'ignorar_id' => ['nullable', 'integer'],
        ]);

        $email = Str::lower(trim((string) $datos['email']));

        if (Validator::make(['email' => $email], ['email' => ['email:rfc']])->fails()) {
            return response()->json(['valido' => false, 'disponible' => false, 'mensaje' => 'Ingresa un correo electrónico válido.']);
        }

        $existe = User::query()
            ->where('email', $email)
            ->when($datos['ignorar_id'] ?? null, fn ($q, $id) => $q->whereKeyNot((int) $id))
            ->exists();

        return response()->json([
            'valido' => true,
            'disponible' => ! $existe,
            'mensaje' => $existe ? 'Este correo ya está registrado.' : 'El correo está disponible.',
        ]);
    }
}
