<script setup>
/**
 * Una celda de la hoja: la columna de la plantilla cruzada con la fila.
 *
 * El control que se dibuja sale del TIPO de la columna (`config/lab_field_types`),
 * que llega como prop desde el servidor. No hay ids numéricos escritos a mano
 * acá: en el sistema viejo los tipos 1/2/3/4 estaban repetidos en unas diez
 * vistas con el comentario "INPUT =1,2 SELECT: 3 DATE: 4", y agregar un tipo
 * quinto guardaba la fila sin que ninguna vista supiera dibujarlo.
 *
 * Una columna puede pedir varias mediciones sobre la misma muestra
 * (`replicates`): la rigidez dieléctrica se mide cinco o seis veces. Cada
 * medición es una casilla y viaja por separado — el promedio no es una casilla
 * más, es una columna calculada con su fórmula.
 */
import { computed } from 'vue';
import { Input, Select, SelectOption, DatePicker, Tag, Tooltip } from 'ant-design-vue';
import { CalculatorOutlined } from '@ant-design/icons-vue';
import InstrumentSelect from '@/Components/Worksheets/InstrumentSelect.vue';

const props = defineProps({
    field:       { type: Object, required: true },
    // Lo que está cargado en el borrador de esta fila: { nro de réplica: valor }.
    values:      { type: Object, default: () => ({}) },
    // Lo que devolvió el SERVIDOR para esta celda, ya resuelto. Es lo que se
    // muestra en las columnas calculadas: el navegador no recalcula nada.
    stored:      { type: Object, default: () => ({}) },
    instruments: { type: Array,  default: () => [] },
    disabled:    { type: Boolean, default: false },
});

const emit = defineEmits(['update']);

const replicates = computed(() => Math.max(1, Number(props.field.replicates ?? 1)));
const many = computed(() => replicates.value > 1);

/**
 * Una columna con fórmula es calculada aunque el tipo diga otra cosa. Es el
 * mismo criterio que aplica el modelo (`TestField::isComputed`): en lo migrado
 * del sistema viejo hay columnas con fórmula y tipo "número", porque allá el
 * cálculo era JavaScript pegado en otro campo.
 */
const isComputed = computed(
    () => props.field.type === 'computed' || String(props.field.formula ?? '').trim() !== '',
);

const visibleOptions = computed(
    () => (props.field.options ?? []).filter((option) => !option.is_hidden),
);

const set = (replicate, value) => emit('update', replicate, value);

const valueAt = (replicate) => props.values?.[replicate] ?? null;
const storedAt = (replicate) => {
    const text = props.stored?.[replicate];

    return (text === null || text === undefined || text === '') ? '—' : text;
};
</script>

<template>
    <!-- ── Calculado: solo lectura de verdad ────────────────────────────────
         No es un input con `readonly` (que un envío directo saltea) ni un
         cálculo del navegador: es lo que devolvió el servidor. En el sistema
         viejo la fórmula era JavaScript guardado en la base, y cuando operaba
         sobre un campo vacío dejaba el texto "NaN" guardado. -->
    <div v-if="isComputed" class="ws-cell ws-cell--computed">
        <div v-for="r in replicates" :key="r" class="ws-cell__line">
            <span v-if="many" class="ws-cell__rep">{{ r }}</span>
            <Tooltip :title="$t('worksheets.computed_help')">
                <span class="ws-computed">
                    <Tag :bordered="false" class="ws-computed__tag">
                        <CalculatorOutlined /> {{ $t('worksheets.computed') }}
                    </Tag>
                    <span class="ws-computed__value">{{ storedAt(r) }}</span>
                </span>
            </Tooltip>
        </div>
    </div>

    <!-- ── Selección: la opción se guarda por clave foránea ─────────────────
         El viejo guardaba el ID de la opción como texto junto a todo lo demás:
         borrar una opción dejaba el histórico apuntando a un id inexistente.
         Las ocultas no se ofrecen, pero la fila vieja que ya las usa las
         conserva. -->
    <div v-else-if="field.type === 'select'" class="ws-cell">
        <div v-for="r in replicates" :key="r" class="ws-cell__line">
            <span v-if="many" class="ws-cell__rep">{{ r }}</span>
            <Select
                :value="valueAt(r) ?? undefined"
                :disabled="disabled"
                allow-clear
                size="small"
                class="ws-cell__control"
                @change="set(r, $event ?? null)"
            >
                <SelectOption v-for="option in visibleOptions" :key="option.id" :value="option.id">
                    {{ option.value }}
                </SelectOption>
            </Select>
        </div>
    </div>

    <div v-else-if="field.type === 'date'" class="ws-cell">
        <div v-for="r in replicates" :key="r" class="ws-cell__line">
            <span v-if="many" class="ws-cell__rep">{{ r }}</span>
            <DatePicker
                :value="valueAt(r) || null"
                :disabled="disabled"
                size="small"
                value-format="YYYY-MM-DD"
                class="ws-cell__control"
                @change="set(r, $event || null)"
            />
        </div>
    </div>

    <div v-else-if="field.type === 'instrument'" class="ws-cell">
        <div v-for="r in replicates" :key="r" class="ws-cell__line">
            <span v-if="many" class="ws-cell__rep">{{ r }}</span>
            <InstrumentSelect
                :instruments="instruments"
                :value="valueAt(r)"
                :disabled="disabled"
                @update:value="set(r, $event)"
            />
        </div>
    </div>

    <!-- ── Número ───────────────────────────────────────────────────────────
         Va como texto y no como InputNumber porque una medición admite el
         signo de censura: ">75" es "el ensayador llegó a su tope", no 75. El
         servidor separa el signo del número y guarda los dos. -->
    <div v-else-if="field.type === 'number'" class="ws-cell">
        <div v-for="r in replicates" :key="r" class="ws-cell__line">
            <span v-if="many" class="ws-cell__rep">{{ r }}</span>
            <Tooltip :title="$t('worksheets.censored.hint')">
                <Input
                    :value="valueAt(r) ?? ''"
                    :disabled="disabled"
                    size="small"
                    inputmode="decimal"
                    class="ws-cell__control ws-cell__control--num"
                    :placeholder="field.unit || '>75'"
                    @change="set(r, $event.target.value)"
                />
            </Tooltip>
        </div>
    </div>

    <div v-else class="ws-cell">
        <div v-for="r in replicates" :key="r" class="ws-cell__line">
            <span v-if="many" class="ws-cell__rep">{{ r }}</span>
            <Input
                :value="valueAt(r) ?? ''"
                :disabled="disabled"
                size="small"
                class="ws-cell__control"
                @change="set(r, $event.target.value)"
            />
        </div>
    </div>
</template>

<style scoped>
.ws-cell { display: flex; flex-direction: column; gap: 4px; }
.ws-cell__line { display: flex; align-items: center; gap: 6px; }
.ws-cell__control { min-width: 130px; width: 100%; }
.ws-cell__control--num { text-align: right; }

/* El número de medición (1..N). Chico y tenue: ordena la lectura sin competir
   con el dato. */
.ws-cell__rep {
    flex: 0 0 auto;
    min-width: 16px;
    font-size: 0.68rem;
    font-weight: 600;
    color: var(--color-text-muted);
    text-align: right;
}

.ws-computed { display: inline-flex; align-items: center; gap: 6px; white-space: nowrap; }
.ws-computed__tag { font-size: 0.65rem; color: var(--color-text-muted); }
.ws-computed__value {
    font-family: ui-monospace, Consolas, monospace;
    font-weight: 600;
    color: var(--color-text);
}
</style>
