<script setup>
/**
 * Con qué equipo se midió.
 *
 * El estado de calibración se marca EN el selector, no en otra pantalla. Un
 * ensayo corrido con un equipo vencido no es trazable, y el analista elige el
 * equipo justo en este momento: si el aviso vive en el módulo de Instrumentos
 * llega tarde. Es la razón de ser de ese catálogo (ISO 17025).
 *
 * En el sistema viejo esto era un `select` de textos como "Bureta PP-LA-01C",
 * con el código de calibración metido adentro del nombre y sin ninguna fecha.
 */
import { computed } from 'vue';
import { Select, SelectOption, Tooltip } from 'ant-design-vue';
import { WarningOutlined } from '@ant-design/icons-vue';
import { calibrationStatus, plainDate } from '@/Pages/Worksheets/config/format';

const props = defineProps({
    instruments: { type: Array,   default: () => [] },
    value:       { type: [Number, String, null], default: null },
    disabled:    { type: Boolean, default: false },
    /**
     * Qué se ve con la celda CERRADA: 'name' muestra "Bureta PP-LA-01C-100",
     * 'code' solo "PP-LA-01C-100".
     *
     * En la grilla del analista el nombre repetido en cada fila estiraba la
     * columna hasta que la fila no entraba en la pantalla, y no aportaba nada:
     * el nombre del instrumento ya está en el encabezado de la columna. El
     * desplegable ABIERTO sigue mostrando nombre + código + el aviso de
     * calibración, que es donde el analista elige.
     */
    display:     { type: String,  default: 'name' },
});

const emit = defineEmits(['update:value']);

const statusOf = (instrument) => calibrationStatus(instrument?.calibration_due_at);

/** Vencida o a menos de 30 días: las dos merecen que el analista se detenga. */
const isRisky = (instrument) => ['expired', 'due_soon'].includes(statusOf(instrument));

const selected = computed(
    () => props.instruments.find((i) => Number(i.id) === Number(props.value)) ?? null,
);
</script>

<template>
    <div class="ws-instrument" :class="{ 'ws-instrument--compact': display === 'code' }">
        <Select
            :value="value ?? undefined"
            :disabled="disabled"
            allow-clear
            show-search
            option-filter-prop="label"
            :option-label-prop="display === 'code' ? 'title' : 'children'"
            size="small"
            class="ws-instrument__select"
            :class="{ 'ws-instrument__select--risky': isRisky(selected) }"
            @change="emit('update:value', $event ?? null)"
        >
            <SelectOption
                v-for="instrument in instruments"
                :key="instrument.id"
                :value="instrument.id"
                :label="`${instrument.name} ${instrument.code ?? ''}`"
                :title="instrument.code || instrument.name"
            >
                <span class="ws-instrument__opt" :class="{ 'is-risky': isRisky(instrument) }">
                    <WarningOutlined v-if="isRisky(instrument)" />
                    {{ instrument.name }}
                    <span v-if="instrument.code" class="ws-instrument__code">{{ instrument.code }}</span>
                </span>
            </SelectOption>
        </Select>

        <!-- El aviso queda debajo del selector, no en un tooltip: si hay que
             pasar el mouse por encima para enterarse, no se entera nadie. -->
        <Tooltip
            v-if="isRisky(selected)"
            :title="`${$t('instruments.calibration_due_at')}: ${plainDate(selected.calibration_due_at)}`"
        >
            <span class="ws-instrument__warn" :class="`is-${statusOf(selected)}`">
                <WarningOutlined />
                {{ $t(`instruments.cal_status_${statusOf(selected)}`) }}
            </span>
        </Tooltip>
    </div>
</template>

<style scoped>
.ws-instrument { display: flex; flex-direction: column; gap: 2px; min-width: 170px; }
/* Mostrando solo el código, 170px sobran y son 170px que le faltan al resto
   de la fila. */
.ws-instrument--compact { min-width: 120px; }
.ws-instrument__select { width: 100%; }
.ws-instrument__opt { display: inline-flex; align-items: center; gap: 6px; }
.ws-instrument__code { color: var(--color-text-muted); font-size: 0.75rem; }
.ws-instrument__opt.is-risky { color: var(--color-warning); }
.ws-instrument__warn {
    display: inline-flex; align-items: center; gap: 4px;
    font-size: 0.72rem; font-weight: 600; line-height: 1.3;
}
.ws-instrument__warn.is-expired  { color: var(--color-danger-bright); }
.ws-instrument__warn.is-due_soon { color: var(--color-warning); }
/* El borde ámbar es el mismo aviso, para que se note con la celda cerrada. */
.ws-instrument__select--risky :deep(.ant-select-selector) {
    border-color: var(--color-warning) !important;
}
</style>
