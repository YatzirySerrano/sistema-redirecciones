<?php

namespace App\Http\Requests\Admin;

use App\Services\Medios\LimitesArchivos;

class LogoNegocioRequest extends SolicitudAdministrativa
{
    public static function mensajeGeneral(): string
    {
        return 'No pudimos subir la imagen. '.app(LimitesArchivos::class)->texto(LimitesArchivos::LOGO);
    }

    /**
     * Solo mapas de bits (sin SVG, que puede contener scripts), con el límite
     * de logotipos de LimitesArchivos.
     * Se comprueba la extensión (mimes) y también el contenido real del
     * archivo (mimetypes), de modo que un archivo renombrado no pasa.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'logo' => [
                'required',
                'file',
                'image',
                'mimes:png,jpg,jpeg,webp',
                'mimetypes:image/png,image/jpeg,image/webp',
                'max:'.intdiv(app(LimitesArchivos::class)->bytes(LimitesArchivos::LOGO), 1024),
                'dimensions:min_width=64,min_height=64,max_width=4000,max_height=4000',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            // Fallo de PHP al recibir el archivo (p. ej. supera upload_max_filesize).
            'logo.uploaded' => self::mensajeGeneral(),
            'logo.required' => 'Selecciona una imagen para el logotipo.',
            'logo.file' => self::mensajeGeneral(),
            'logo.image' => 'El archivo no es una imagen válida. Usa PNG, JPG o WEBP.',
            'logo.mimes' => 'El logotipo debe ser PNG, JPG o WEBP.',
            'logo.mimetypes' => 'El contenido del archivo no corresponde a una imagen PNG, JPG o WEBP.',
            'logo.max' => 'El logotipo no debe pesar más de '.app(LimitesArchivos::class)->descripcion(LimitesArchivos::LOGO)['max_texto'].'.',
            'logo.dimensions' => 'El logotipo debe medir entre 64 y 4000 píxeles por lado.',
        ];
    }
}
