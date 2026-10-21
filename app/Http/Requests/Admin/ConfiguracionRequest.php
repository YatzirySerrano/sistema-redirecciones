<?php

namespace App\Http\Requests\Admin;

class ConfiguracionRequest extends SolicitudAdministrativa
{
    protected function prepareForValidation(): void
    {
        $dominios = preg_split('/[\s,]+/', strtolower((string) $this->input('dominios_bloqueados', ''))) ?: [];

        $this->merge([
            'permitir_destinos_http' => $this->boolean('permitir_destinos_http'),
            'clientes_descargan_qr' => $this->boolean('clientes_descargan_qr'),
            'clientes_ven_estadisticas' => $this->boolean('clientes_ven_estadisticas'),
            'dominios_bloqueados' => array_values(array_unique(array_filter($dominios))),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'permitir_destinos_http' => ['boolean'],
            'clientes_descargan_qr' => ['boolean'],
            'clientes_ven_estadisticas' => ['boolean'],
            'dominios_bloqueados' => ['array', 'max:200'],
            'dominios_bloqueados.*' => ['string', 'max:253', 'regex:/^(?!-)[a-z0-9-]+(\.[a-z0-9-]+)+$/'],
            'dias_retencion_eventos' => ['required', 'integer', 'min:30', 'max:1825'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'dominios_bloqueados.*.regex' => '«:input» no es un dominio válido.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['dias_retencion_eventos' => 'días de retención'];
    }
}
