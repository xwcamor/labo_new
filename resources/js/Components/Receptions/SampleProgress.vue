<script setup>
/**
 * El avance de UNA muestra, como semáforo.
 *
 * Los números llegan ya contados desde el servidor (`progress`, indexado por
 * `sample_id`, una sola consulta agregada para toda la recepción). Acá no se
 * cuenta nada ni se recorre la lista de pruebas: solo se dibuja.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ UN COLOR POR ETAPA, NO UN COLOR POR ESTADO                               │
 * └──────────────────────────────────────────────────────────────────────────┘
 * Antes la barra pintaba un segmento por estado de prueba (gris/azul/verde/
 * violeta) y dos muestras completas salían una verde y otra violeta: para
 * leerla había que saberse el código de memoria. La pregunta que responde esta
 * columna es "¿en qué está la muestra?", y eso es UNA etapa, no cuatro conteos.
 * El detalle por estado no se pierde: vive en el tooltip.
 *
 * Las etapas son CUATRO y no tres, porque "a medio ensayar" es el estado más
 * frecuente durante la semana y meterlo en rojo o en amarillo miente en las
 * dos direcciones:
 *
 *   rojo     — nada: ni un ensayo cargado (o sin pruebas pedidas).
 *   naranja  — bancada en curso: algo cargado, faltan ensayos.
 *   amarillo — ensayada: todo validado, FALTA EL INFORME. Es el accionable.
 *   verde    — informada: el informe salió.
 *
 * Rojo → naranja → amarillo → verde es un degradé natural de avance y se lee
 * como semáforo sin leyenda.
 */
import { computed } from 'vue';
import { Tooltip } from 'ant-design-vue';
import { useI18n } from '@/Plugins/i18n';

const { t } = useI18n();

const props = defineProps({
    // { pedidas, pendientes, en_proceso, validadas, informadas } o null.
    stats: { type: Object, default: null },
});

/** Postgres puede devolver los conteos como cadena; se normalizan una vez. */
const n = (value) => Number(value ?? 0) || 0;

const total = computed(() => n(props.stats?.pedidas));

/** Lo terminado: validado o informado. Es lo que el cliente ya puede recibir. */
const done = computed(() => n(props.stats?.validadas) + n(props.stats?.informadas));

const stage = computed(() => {
    if (total.value === 0) return 'none';
    if (n(props.stats?.informadas) >= total.value) return 'reported';
    if (done.value >= total.value) return 'awaiting_report';
    if (done.value > 0 || n(props.stats?.en_proceso) > 0) return 'in_progress';

    return 'not_started';
});

/**
 * La barra se llena con lo TERMINADO. En rojo queda vacía a propósito: pedida
 * y sin arrancar es exactamente "empezó en cero", y el color ya dice que eso
 * es un problema, no un adorno.
 */
const fill = computed(() => (total.value === 0 ? 0 : (done.value / total.value) * 100));

/** El detalle por estado, para el tooltip: la etapa resume, esto desglosa. */
const detalle = computed(() => [
    ['pending',     n(props.stats?.pendientes),  'receptions.test_status_pending'],
    ['in_progress', n(props.stats?.en_proceso),  'receptions.test_status_in_progress'],
    ['validated',   n(props.stats?.validadas),   'receptions.test_status_validated'],
    ['reported',    n(props.stats?.informadas),  'receptions.test_status_reported'],
]
    .filter(([, count]) => count > 0)
    .map(([, count, label]) => `${t(label)}: ${count}`)
    .join(' · '));

const tooltip = computed(() =>
    `${t('receptions.stage_' + stage.value)}${detalle.value ? ' — ' + detalle.value : ''}`);
</script>

<template>
    <!-- Sin pruebas pedidas: se dice con palabras Y en rojo. No es un estado
         neutro — una muestra que entró y a la que nadie le pidió nada es
         trabajo detenido. -->
    <div v-if="total === 0" class="rc-progress rc-progress--empty">
        <span class="rc-progress__dot rc-progress__dot--not_started" />
        {{ $t('receptions.no_tests') }}
    </div>

    <Tooltip v-else :title="tooltip">
        <div class="rc-progress">
            <div class="rc-progress__bar" :class="`rc-progress__bar--${stage}`">
                <span
                    class="rc-progress__fill"
                    :class="`rc-progress__fill--${stage}`"
                    :style="{ width: `${fill}%` }"
                />
            </div>
            <span class="rc-progress__count" :class="`rc-progress__count--${stage}`">
                {{ done }}/{{ total }}
            </span>
        </div>
    </Tooltip>
</template>

<style scoped>
.rc-progress { display: flex; align-items: center; gap: 8px; min-width: 120px; }
.rc-progress--empty {
    color: #c8281d;
    font-size: 0.78rem;
    display: inline-flex; align-items: center; gap: 6px;
}
.rc-progress__dot { width: 8px; height: 8px; border-radius: 50%; flex: none; }
.rc-progress__dot--not_started { background: #c8281d; }

.rc-progress__bar {
    flex: 1 1 auto;
    display: flex;
    height: 8px;
    min-width: 70px;
    border-radius: 999px;
    overflow: hidden;
    background: var(--color-surface-alt, #f0f2f5);
}
/* En rojo la pista entera se tiñe suave: una barra gris vacía se lee como
   "todavía no le toca", y sí le toca — está pedida y nadie la cargó. */
.rc-progress__bar--not_started { background: #fde8e8; }

.rc-progress__fill { display: block; height: 100%; border-radius: 999px; }
.rc-progress__fill--in_progress    { background: #fa8c16; }
.rc-progress__fill--awaiting_report{ background: #f5b50a; }
.rc-progress__fill--reported       { background: #52c41a; }

.rc-progress__count {
    flex-shrink: 0;
    font-size: 0.75rem;
    font-variant-numeric: tabular-nums;
    color: var(--color-text-muted);
}
.rc-progress__count--not_started { color: #c8281d; font-weight: 600; }
</style>
