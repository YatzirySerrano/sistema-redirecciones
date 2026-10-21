<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { CircleAlert, Save, Server, UserRound } from '@lucide/vue';
import CampoFormulario from '@/components/comun/CampoFormulario.vue';
import EncabezadoPagina from '@/components/comun/EncabezadoPagina.vue';
import Panel from '@/components/comun/Panel.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import {
    edit as configuracionEdit,
    update as configuracionUpdate,
} from '@/routes/admin/configuracion';
import { edit as perfilEdit } from '@/routes/profile';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Configuración', href: configuracionEdit() }],
    },
});

const props = defineProps<{
    ajustes: {
        permitir_destinos_http: boolean;
        clientes_descargan_qr: boolean;
        clientes_ven_estadisticas: boolean;
        dominios_bloqueados: string[];
        dias_retencion_eventos: number;
    };
    entorno: {
        url_publica: string;
        ejemplo_enlace: string;
        eventos_en_cola: boolean;
        dias_conservacion_huella: number;
        limite_redirecciones_por_minuto: number;
        zona_horaria: string;
    };
}>();

const form = useForm({
    permitir_destinos_http: props.ajustes.permitir_destinos_http,
    clientes_descargan_qr: props.ajustes.clientes_descargan_qr,
    clientes_ven_estadisticas: props.ajustes.clientes_ven_estadisticas,
    dominios_bloqueados: props.ajustes.dominios_bloqueados.join('\n'),
    dias_retencion_eventos: props.ajustes.dias_retencion_eventos,
});

const erroresDominios = () =>
    Object.entries(form.errors)
        .filter(([clave]) => clave.startsWith('dominios_bloqueados'))
        .map(([, mensaje]) => mensaje)
        .join(' ');

const guardar = () => {
    form.put(configuracionUpdate.url(), {
        preserveScroll: true,
        onSuccess: () => form.defaults(),
    });
};

const INTERRUPTORES = [
    {
        campo: 'clientes_descargan_qr',
        titulo: 'Los clientes pueden descargar sus QR',
        texto: 'Muestra el diseñador y la descarga del QR en el panel del cliente.',
    },
    {
        campo: 'clientes_ven_estadisticas',
        titulo: 'Los clientes pueden ver estadísticas',
        texto: 'Habilita la sección de estadísticas y las gráficas en el panel del cliente.',
    },
    {
        campo: 'permitir_destinos_http',
        titulo: 'Permitir destinos sin conexión segura (http://)',
        texto: 'Por defecto solo se aceptan destinos https://.',
    },
] as const;
</script>

<template>
    <Head title="Configuración" />

    <EncabezadoPagina
        titulo="Configuración de la plataforma"
        descripcion="Reglas que se aplican a todos los negocios y enlaces."
    >
        <template #acciones>
            <Button variant="outline" as-child>
                <Link :href="perfilEdit()"><UserRound /> Mi cuenta</Link>
            </Button>
        </template>
    </EncabezadoPagina>

    <div
        class="grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,22rem)]"
    >
        <form class="space-y-6" @submit.prevent="guardar">
            <Panel titulo="Permisos y destinos">
                <div class="divide-y">
                    <div
                        v-for="item in INTERRUPTORES"
                        :key="item.campo"
                        class="flex items-start justify-between gap-4 py-4 first:pt-0"
                    >
                        <div>
                            <label
                                :for="`ajuste-${item.campo}`"
                                class="text-sm font-medium"
                                >{{ item.titulo }}</label
                            >
                            <p class="text-sm text-muted-foreground">
                                {{ item.texto }}
                            </p>
                            <p
                                v-if="
                                    item.campo === 'permitir_destinos_http' &&
                                    form.permitir_destinos_http
                                "
                                class="mt-2 flex items-start gap-1.5 text-xs text-warning"
                            >
                                <CircleAlert class="mt-0.5 size-3.5 shrink-0" />
                                Los destinos http:// no cifran la conexión y
                                algunos navegadores muestran advertencias. Úsalo
                                solo si es indispensable.
                            </p>
                        </div>
                        <Switch
                            :id="`ajuste-${item.campo}`"
                            v-model="form[item.campo]"
                            class="mt-1"
                        />
                    </div>
                </div>

                <div class="mt-2 grid gap-6 border-t pt-5">
                    <CampoFormulario
                        etiqueta="Dominios bloqueados como destino"
                        para="dominios_bloqueados"
                        ayuda="Uno por línea. También bloquea sus subdominios. Útil para impedir acortadores u otros redireccionadores."
                    >
                        <Textarea
                            id="dominios_bloqueados"
                            v-model="form.dominios_bloqueados"
                            rows="6"
                            spellcheck="false"
                            class="font-mono text-sm"
                            placeholder="bit.ly"
                            :aria-invalid="!!erroresDominios() || undefined"
                        />
                        <InputError :message="erroresDominios()" />
                    </CampoFormulario>

                    <CampoFormulario
                        etiqueta="Días de retención de eventos individuales"
                        para="dias_retencion_eventos"
                        :error="form.errors.dias_retencion_eventos"
                        ayuda="Entre 30 y 1825 días. Después se eliminan los eventos detallados; los totales diarios se conservan."
                    >
                        <Input
                            id="dias_retencion_eventos"
                            v-model.number="form.dias_retencion_eventos"
                            type="number"
                            min="30"
                            max="1825"
                            class="max-w-40"
                        />
                    </CampoFormulario>
                </div>
            </Panel>

            <div class="flex justify-end">
                <Button
                    type="submit"
                    :disabled="form.processing || !form.isDirty"
                >
                    <Spinner v-if="form.processing" />
                    <Save v-else />
                    Guardar configuración
                </Button>
            </div>
        </form>

        <Panel
            titulo="Entorno del servidor"
            descripcion="Valores de solo lectura definidos en el archivo .env."
        >
            <template #acciones>
                <Server
                    class="size-4 text-muted-foreground"
                    aria-hidden="true"
                />
            </template>
            <dl class="space-y-3 text-sm">
                <div>
                    <dt class="text-muted-foreground">URL pública</dt>
                    <dd class="font-mono break-all">
                        {{ entorno.url_publica }}
                    </dd>
                </div>
                <div>
                    <dt class="text-muted-foreground">
                        Ejemplo de enlace permanente
                    </dt>
                    <dd class="font-mono break-all">
                        {{ entorno.ejemplo_enlace }}
                    </dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt class="text-muted-foreground">
                        Registro de accesos en cola
                    </dt>
                    <dd>
                        {{
                            entorno.eventos_en_cola
                                ? 'Sí (requiere worker)'
                                : 'No (tras la respuesta)'
                        }}
                    </dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt class="text-muted-foreground">
                        Conservación de huella
                    </dt>
                    <dd class="tabular-nums">
                        {{ entorno.dias_conservacion_huella }} días
                    </dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt class="text-muted-foreground">Límite por IP</dt>
                    <dd class="tabular-nums">
                        {{ entorno.limite_redirecciones_por_minuto }}
                        solicitudes/min
                    </dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt class="text-muted-foreground">Zona horaria</dt>
                    <dd>{{ entorno.zona_horaria }}</dd>
                </div>
            </dl>
            <div
                class="mt-4 flex gap-2 rounded-lg bg-warning-soft p-3 text-xs text-warning"
            >
                <CircleAlert class="mt-0.5 size-3.5 shrink-0" />
                <p>
                    Estos valores los cambia el administrador del servidor. La
                    URL pública
                    <strong>no debe cambiar</strong> después de entregar
                    productos: los QR impresos y las tarjetas NFC dejarían de
                    funcionar.
                </p>
            </div>
        </Panel>
    </div>
</template>
