<?php

namespace App\Console\Commands;

use App\Enums\EstadoCuenta;
use App\Enums\RolUsuario;
use App\Models\User;
use App\Services\Auditoria\RegistradorAuditoria;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

/**
 * Crea el administrador inicial (u otro administrador) de forma segura,
 * independiente de los seeders de demostración. La contraseña se solicita
 * de forma oculta y nunca se acepta como argumento (quedaría en el
 * historial de la terminal).
 */
class CrearAdministrador extends Command
{
    protected $signature = 'dasti:crear-administrador
        {--nombre= : Nombre completo}
        {--email= : Correo de acceso}';

    protected $description = 'Crea una cuenta de administrador de DASTI';

    public function handle(RegistradorAuditoria $auditoria): int
    {
        $nombre = $this->option('nombre');
        $nombre = is_string($nombre) && $nombre !== '' ? $nombre : text('Nombre completo', required: true);
        $email = $this->option('email');
        $email = Str::lower(is_string($email) && $email !== '' ? $email : text('Correo electrónico', required: true));

        $validacionDatos = Validator::make(
            ['name' => $nombre, 'email' => $email],
            ['name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email:rfc', 'max:255', 'unique:users,email']],
        );

        if ($validacionDatos->fails()) {
            foreach ($validacionDatos->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $clave = password('Contraseña', required: true, hint: 'Mínimo 12 caracteres con mayúsculas, minúsculas, números y símbolos en producción.');
        $confirmacion = password('Confirma la contraseña', required: true);

        $validacionClave = Validator::make(
            ['password' => $clave, 'password_confirmation' => $confirmacion],
            ['password' => ['required', 'confirmed', Password::defaults()]],
        );

        if ($validacionClave->fails()) {
            foreach ($validacionClave->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $usuario = new User(['name' => $nombre, 'email' => $email, 'password' => $clave]);
        $usuario->forceFill([
            'rol' => RolUsuario::Admin,
            'estado' => EstadoCuenta::Activo,
            'email_verified_at' => now(),
            'debe_cambiar_password' => false,
        ])->save();

        $auditoria->registrar(
            accion: 'usuario.creado',
            descripcion: "Alta del administrador {$email} desde consola",
            recurso: $usuario,
            nuevos: ['nombre' => $nombre, 'email' => $email, 'rol' => RolUsuario::Admin, 'metodo_acceso' => 'consola'],
            actor: $usuario,
        );

        $this->info("Administrador {$email} creado correctamente.");

        return self::SUCCESS;
    }
}
