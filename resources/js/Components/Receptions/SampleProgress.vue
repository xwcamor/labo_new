<script setup>
/**
 * El avance de UNA muestra: cuánto se le pidió y en qué va cada prueba.
 *
 * Los números llegan ya contados desde el servidor (`progress`, indexado por
 * `sample_id`, una sola consulta agregada para toda la recepción). Acá no se
 * cuenta nada ni se recorre la lista de pruebas: solo se dibuja.
 *
 * Sin pruebas pedidas NO se muestra una barra vacía al 0 %, que se lee como
 * "empezó y no avanzó". Se dice lo que pasa: todavía no se le pidió nada.
 */
import { computed } from 'vue';
import { Tooltip } from 'ant-design-vue';

const props = defineProps({
    // { pedidas, pendientes, en_proceso, validadas, informadas } o null.
    stats: { type: Object, default: null },
});

/** Postgres puede devolver los conteos como cadena; se normalizan una vez. */
const n = (value) => Number(value ?? 0) || 0;

const total = computed(() => n(props.stats?.pedidas));

const segments = computed(() => [
    { key: 'reported',    count: n(props.stats?.informadas),  label: 'receptions.test_status_reported' },
    { key: 'validated',   count: n(props.stats?.validadas),   label: 'receptions.test_status_validated' },
    { key: 'in_progress', count: n(props.stats?.en_proceso),  label: 'receptions.test_status_in_progress' },
    { key: 'pending',     count: n(props.stats?.pendientes),  label: 'receptions.test_status_pending' },
].filter((segment) => segment.count > 0));

/** Lo terminado: validado o informado. Es lo que el cliente ya puede recibir. */
const done = computed(() => n(props.stats?.validadas) + n(props.stats?.informadas));

const width = (count) => (total.value === 0 ? '0%' : `${(count / total.value) * 100}%`);
</script>

<template>
    <div v-if="total === 0" class="rc-progress rc-progress--empty">
        {{ $t('receptions.no_tests') }}
    </div>

    <div v-else class="rc-progress">
        <div class="rc-progress__bar">
            <Tooltip
                v-for="segment in segments"
                :key="segment.key"
                :title="`${$t(segment.label)}: ${segment.count}`"
            >
                <span
                    class="rc-progress__seg"
                    :class="`rc-progress__seg--${segment.key}`"
                    :style="{ width: width(segment.count) }"
                />
            </Tooltip>
        </div>
        <span class="rc-progress__count">{{ done }}/{{ total }}</span>
    </div>
</template>

<style scoped>
.rc-progress { display: flex; align-items: center; gap: 8px; min-width: 120px; }
.rc-progress--empty { color: var(--color-text-muted); font-size: 0.78rem; }

.rc-progress__bar {
    flex: 1 1 auto;
    display: flex;
    height: 8px;
    min-width: 70px;
    border-radius: 999px;
    overflow: hidden;
    background: var(--color-surface-alt, #f0f2f5);
}
.rc-progress__seg { display: block; height: 100%; }
/* Mismo lenguaje de color que los estados de prueba: gris pendiente, azul en
   curso, verde validado, violeta informado. */
.rc-progress__seg--pending     { background: var(--color-border-strong, #d0d5dd); }
.rc-progress__seg--in_progress { background: #2f6df6; }
.rc-progress__seg--validated   { background: #52c41a; }
.rc-progress__seg--reported    { background: #722ed1; }

.rc-progress__count {
    flex-shrink: 0;
    font-size: 0.75rem;
    font-variant-numeric: tabular-nums;
    color: var(--color-text-muted);
}
</style>
