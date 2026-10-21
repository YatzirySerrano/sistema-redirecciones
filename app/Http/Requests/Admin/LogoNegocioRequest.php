<?php

namespace App\Http\Requests\Admin;

class LogoNegocioRequest extends SolicitudAdministrativa
{
    /**
     * Solo mapas de bits (sin SVG, que puede contener scripts), máximo 1 MB.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'logo' => ['required', 'file', 'image', 'mimes:png,jpg,jpeg,webp', 'max:1024', 'dimensions:min_width=64,min_height=64,max_width=4000,max_height=4000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'logo.mimes' => 'El logotipo debe ser PNG, JPG o WEBP.',
            'logo.max' => 'El logotipo no debe pesar más de 1 MB.',
            'logo.dimensions' => 'El logotipo debe medir entre 64 y 4000 píxeles por lado.',
        ];
    }
}
