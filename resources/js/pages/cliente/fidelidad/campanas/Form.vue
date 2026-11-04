<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { CircleAlert, Save } from '@lucide/vue';
import CampoFormulario from '@/components/comun/CampoFormulario.vue';
import EncabezadoPagina from '@/components/comun/EncabezadoPagina.vue';
import Panel from '@/components/comun/Panel.vue';
import NavFidelidad from '@/components/fidelidad/NavFidelidad.vue';
import type {
    EstadoServicio,
    ReglasCampana,
} from '@/components/fidelidad/tipos';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { hoyIso } from '@/lib/formato';
import { inicio } from '@/routes/cliente/fidelidad';
import { index, store, update } from '@/routes/cliente/fidelidad/campanas';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Fidelidad', href: inicio() },
            { title: 'Campañas', href: index() },
            { title: 'Campaña', href: '#' },
        ],
    },
});

const props = defineProps<{
    servicio: EstadoServicio;
    campana: {
        id: number;
        nombre: string;
        version: number;
        participantes: number;
        reglas: ReglasCampana | null;
    } | null;
}>();

const r = props.campana?.reglas;
const form = useForm({
    nombre: props.campana?.nombre ?? '',
    descripcion: r?.descripcion ?? '',
    meta_visitas: r?.meta_visitas ?? 8,
    recompensa: r?.recompensa ?? '',
    vigencia_visitas_dias: (r?.vigencia_visitas_dias ?? undefined) as
        | number
        | undefined,
    vigencia_recompensa_dias: (r?.vigencia_recompensa_dias ?? undefined) as
        | number
        | undefined,
    inicia_en: r?.inicia_en ?? hoyIso(),
    termina_en: r?.termina_en ?? '',
    limite_participantes: (r?.limite_participantes ?? undefined) as
        | number
        | undefined,
    max_visitas_por_dia: r?.max_visitas_por_dia ?? 1,
    intervalo_minimo_minutos: r?.intervalo_minimo_minutos ?? 0,
    repetir_ciclo: r?.repetir_ciclo ?? true,
});

const guardar = () => {
    form.transform((d) => ({
        ...d,
        termina_en: d.termina_en || null,
        descripcion: d.descripcion.trim() || null,
        vigencia_visitas_dias: d.vigencia_visitas_dias || null,
        vigencia_recompensa_dias: d.vigencia_recompensa_dias || null,
        limite_participantes: d.limite_participantes || null,
    }));

    if (props.campana) {
        form.put(update.url(props.campana.id));
    } else {
        form.post(store.url());
    }
};
</script>

<template>
    <Head :title="campana ? 'Editar campaña' : 'Nueva campaña'" />

    <NavFidelidad :servicio="servicio" />

    <EncabezadoPagina
        :titulo="campana ? `Editar «${campana.nombre}»` : 'Nueva campaña'"
        descripcion="Define la meta, la recompensa y las reglas que protegen tu programa."
    />

    <p
        v-if="campana && campana.participantes > 0"
        class="flex items-start gap-2 rounded-lg bg-warning-soft p-3 text-sm text-warning"
    >
        <CircleAlert class="mt-0.5 size-4 shrink-0" />
        {{ campana.participantes }} clientes ya participan. Si cambias las
        reglas se creará la versión {{ campana.version + 1 }}: ellos conservan
        sus condiciones y los nuevos usarán las nuevas.
    </p>

    <form
        class="grid max-w-4xl gap-6 lg:grid-cols-2"
        novalidate
        @submit.prevent="guardar"
    >
        <Panel titulo="Meta y recompensa">
            <div class="grid gap-5">
                <CampoFormulario
                    etiqueta="Nombre"
                    para="nombre"
                    :error="form.errors.nombre"
                >
                    <Input
                        id="nombre"
                        v-model="form.nombre"
                        maxlength="120"
                        placeholder="Ej. Café de la casa"
                    />
                </CampoFormulario>
                <div class="grid grid-cols-[7rem_1fr] gap-3">
                    <CampoFormulario
                        etiqueta="Visitas"
                        para="meta"
                        :error="form.errors.meta_visitas"
                    >
                        <Input
                            id="meta"
                            v-model.number="form.meta_visitas"
                            type="number"
                            min="2"
                            max="100"
                        />
                    </CampoFormulario>
                    <CampoFormulario
                        etiqueta="Recompensa"
                        para="recompensa"
                        :error="form.errors.recompensa"
                    >
                        <Input
                            id="recompensa"
                            v-model="form.recompensa"
                            maxlength="160"
                            placeholder="Ej. Un café gratis"
                        />
                    </CampoFormulario>
                </div>
                <CampoFormulario
                    etiqueta="Descripción"
                    para="descripcion"
                    :error="form.errors.descripcion"
                    opcional
                >
                    <Textarea
                        id="descripcion"
                        v-model="form.descripcion"
                        rows="2"
                        maxlength="500"
                    />
                </CampoFormulario>
                <label
                    class="flex items-center justify-between gap-3 rounded-lg border p-3 text-sm"
                >
                    <span>
                        <span class="font-medium">Repetir el ciclo</span>
                        <span class="block text-xs text-muted-foreground"
                            >Al ganar la recompensa, empieza a acumular de
                            nuevo.</span
                        >
                    </span>
                    <Switch v-model="form.repetir_ciclo" />
                </label>
            </div>
        </Panel>

        <Panel titulo="Vigencia y reglas antifraude">
            <div class="grid gap-5 sm:grid-cols-2">
                <CampoFormulario
                    etiqueta="Inicia"
                    para="inicia"
                    :error="form.errors.inicia_en"
                >
                    <Input id="inicia" v-model="form.inicia_en" type="date" />
                </CampoFormulario>
                <CampoFormulario
                    etiqueta="Termina"
                    para="termina"
                    :error="form.errors.termina_en"
                    opcional
                >
                    <Input
                        id="termina"
                        v-model="form.termina_en"
                        type="date"
                        :min="form.inicia_en"
                    />
                </CampoFormulario>
                <CampoFormulario
                    etiqueta="Visitas por día"
                    para="por-dia"
                    :error="form.errors.max_visitas_por_dia"
                    ayuda="Máximo por cliente."
                >
                    <Input
                        id="por-dia"
                        v-model.number="form.max_visitas_por_dia"
                        type="number"
                        min="1"
                        max="10"
                    />
                </CampoFormulario>
                <CampoFormulario
                    etiqueta="Minutos entre visitas"
                    para="intervalo"
                    :error="form.errors.intervalo_minimo_minutos"
                    ayuda="0 = sin mínimo."
                >
                    <Input
                        id="intervalo"
                        v-model.number="form.intervalo_minimo_minutos"
                        type="number"
                        min="0"
                        max="1440"
                    />
                </CampoFormulario>
                <CampoFormulario
                    etiqueta="Visitas caducan tras"
                    para="vig-visitas"
                    :error="form.errors.vigencia_visitas_dias"
                    opcional
                    ayuda="Días sin volver."
                >
                    <Input
                        id="vig-visitas"
                        v-model.number="form.vigencia_visitas_dias"
                        type="number"
                        min="1"
                        placeholder="No caducan"
                    />
                </CampoFormulario>
                <CampoFormulario
                    etiqueta="Recompensa vence en"
                    para="vig-recompensa"
                    :error="form.errors.vigencia_recompensa_dias"
                    opcional
                    ayuda="Días para canjearla."
                >
                    <Input
                        id="vig-recompensa"
                        v-model.number="form.vigencia_recompensa_dias"
                        type="number"
                        min="1"
                        placeholder="No vence"
                    />
                </CampoFormulario>
                <CampoFormulario
                    etiqueta="Límite de participantes"
                    para="limite"
                    :error="form.errors.limite_participantes"
                    opcional
                    class="sm:col-span-2"
                >
                    <Input
                        id="limite"
                        v-model.number="form.limite_participantes"
                        type="number"
                        min="1"
                        placeholder="Sin límite"
                    />
                </CampoFormulario>
            </div>
        </Panel>

        <div
            class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end lg:col-span-2"
        >
            <Button variant="outline" as-child
                ><Link :href="index()">Cancelar</Link></Button
            >
            <Button type="submit" :disabled="form.processing">
                <Spinner v-if="form.processing" />
                <Save v-else />
                {{ campana ? 'Guardar cambios' : 'Crear campaña' }}
            </Button>
        </div>
    </form>
</template>
