<?php

namespace App\Services\Fidelidad;

use App\Enums\EstadoCampana;
use App\Enums\EstadoRecompensa;
use App\Models\Fidelidad\Campana;
use App\Models\Fidelidad\Consumidor;
use App\Models\Fidelidad\Participacion;

/**
 * Representación de un consumidor para el personal del negocio. Los datos
 * de contacto se muestran enmascarados (solo los necesarios para confirmar
 * identidad), salvo en la ficha de edición.
 */
final class FichaConsumidor
{
    /**
     * @return array<string, mixed>
     */
    public static function resumen(Consumidor $consumidor, bool $contactoCompleto = false): array
    {
        $consumidor->loadMissing(['participaciones.version', 'participaciones.campana']);

        $campanas = Campana::query()->with('vigente')
            ->where('negocio_id', $consumidor->negocio_id)->where('estado', EstadoCampana::Activa)
            ->orderBy('nombre')->get();

        $progreso = $campanas->map(function (Campana $campana) use ($consumidor) {
            /** @var Participacion|null $p */
            $p = $consumidor->participaciones->firstWhere('campana_id', $campana->id);
            $meta = $p !== null ? $p->version->meta_visitas : ($campana->vigente->meta_visitas ?? 0);

            return [
                'campana_id' => $campana->id,
                'campana' => $campana->nombre,
                'participacion_id' => $p?->id,
                'visitas' => $p->visitas_ciclo ?? 0,
                'meta' => $meta,
                'recompensa' => $p?->version->recompensa ?? $campana->vigente?->recompensa,
                'completada' => $p?->estado === Participacion::COMPLETADA,
                'ultima_visita_en' => $p?->ultima_visita_en?->toIso8601String(),
                'version' => $p?->version->version ?? $campana->version_actual,
            ];
        })->values()->all();

        return [
            'id' => $consumidor->id,
            'nombre' => $consumidor->nombre,
            'alias' => $consumidor->alias,
            'telefono' => $contactoCompleto ? $consumidor->telefono : self::enmascarar($consumidor->telefono),
            'email' => $contactoCompleto ? $consumidor->email : self::enmascararCorreo($consumidor->email),
            'email_verificado' => $consumidor->email_verificado_en !== null,
            'estado' => $consumidor->estado,
            'tiene_enlace' => $consumidor->token_acceso_hash !== null,
            'consentimiento_en' => $consumidor->consentimiento_en->toIso8601String(),
            'created_at' => $consumidor->created_at?->toIso8601String(),
            'progreso' => $progreso,
            'recompensas' => $consumidor->recompensas()->where('estado', EstadoRecompensa::Disponible)
                ->where(fn ($q) => $q->whereNull('vence_en')->orWhere('vence_en', '>', now()))
                ->orderBy('created_at')->get()
                ->map(fn ($r) => ['id' => $r->id, 'descripcion' => $r->descripcion, 'vence_en' => $r->vence_en?->toIso8601String()])
                ->all(),
        ];
    }

    public static function enmascarar(?string $telefono): ?string
    {
        return $telefono === null ? null : '••• ••• '.substr($telefono, -4);
    }

    public static function enmascararCorreo(?string $email): ?string
    {
        if ($email === null) {
            return null;
        }

        [$usuario, $dominio] = array_pad(explode('@', $email, 2), 2, '');

        return mb_substr($usuario, 0, 1).'•••@'.$dominio;
    }
}
