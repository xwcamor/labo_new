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
import { Input, Select, SelectOption, DatePicker, Tooltip } from 'ant-design-vue';
import {
    LoadingOutlined, WarningOutlined,
} from '@ant-design/icons-vue';
import InstrumentSelect from '@/Components/Worksheets/InstrumentSelect.vue';
import { numText } from '@/Pages/Worksheets/config/format';

const props = defineProps({
    field:       { type: Object, required: true },
    // Lo que está cargado en el borrador de esta fila: { nro de réplica: valor }.
    values:      { type: Object, default: () => ({}) },
    // Lo que devolvió el SERVIDOR para esta celda, ya resuelto. Es lo que se
    // muestra en las columnas calculadas: el navegador no recalcula nada.
    stored:      { type: Object, default: () => ({}) },
    // La vista previa que devolvió el servidor para lo que hay tipeado y
    // todavía sin guardar: { nro de réplica: número|null }. Sale del MISMO
    // motor que el guardado; acá no se calcula nada, se dibuja.
    preview:     { type: Object, default: () => ({}) },
    /** 'idle' | 'loading' | 'ready' | 'failed'. */
    previewState: { type: String, default: 'idle' },
    /** Ciclo, fórmula rota o servidor inalcanzable, ya traducido. */
    previewMessage: { type: String, default: '' },
    /**
     * `(replicate) => 'la fórmula con los números de ESTA fila'`. La arma la
     * grilla, que es la que tiene todos los valores de la fila; acá solo se
     * dibuja. NO evalúa nada: el número de la celda sigue siendo el que
     * devolvió el servidor.
     */
    formulaTrace: { type: Function, default: null },
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

/**
 * El texto de la opción elegida, para el `title` del control.
 *
 * Aunque la celda cerrada ya no recorta —el ancho subió y el desplegable se
 * mide por su contenido—, una norma larga en una pantalla angosta puede seguir
 * quedando corta. El `title` la dice entera sin ocupar lugar.
 */
const textoDeOpcion = (id) => (props.field.options ?? []).find(
    (option) => Number(option.id) === Number(id),
)?.value ?? '';

const set = (replicate, value) => emit('update', replicate, value);

const valueAt = (replicate) => props.values?.[replicate] ?? null;
const storedAt = (replicate) => {
    const text = props.stored?.[replicate];

    return (text === null || text === undefined || text === '') ? '—' : text;
};

/**
 * Qué muestra la celda calculada.
 *
 * Mientras la respuesta está en camino NO se deja el número anterior: ya no
 * corresponde a lo que hay tipeado y se leería como si fuera el actual. Si la
 * consulta falló, la celda queda vacía con el aviso — el navegador no rellena
 * ese hueco con una cuenta propia, que es exactamente lo que hacía el sistema
 * viejo y el motivo de todo este cambio.
 */
const previewAt = (replicate) => {
    if (props.previewState !== 'ready') return null;

    const value = props.preview?.[replicate];

    return (value === null || value === undefined) ? '—' : numText(value);
};

const shownAt = (replicate) => {
    if (props.previewState === 'loading' || props.previewState === 'failed') return '—';
    if (props.previewState === 'ready') return previewAt(replicate);

    return storedAt(replicate);
};

/** El texto de ayuda dice de dónde salió el número que se está viendo. */
const computedHelp = computed(() => {
    if (props.previewState === 'loading') return 'worksheets.preview_calculating';
    if (props.previewState === 'ready')   return 'worksheets.preview_hint';

    return 'worksheets.computed_help';
});

/**
 * La cuenta de ESTA celda, con los números que se usaron.
 *
 * Es lo que se revisa cuando un calculado no cierra: hasta ahora la celda decía
 * 2.71 y para saber de dónde salía había que recorrer las columnas de la fila a
 * ojo. Una columna sin cargar sale como raya, no como cero.
 */
const trace = (replicate) => {
    if (! props.formulaTrace || ! String(props.field.formula ?? '').trim()) return '';

    return props.formulaTrace(replicate) || '';
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
            <Tooltip>
                <template #title>
                    <div class="ws-fx">
                        <div class="ws-fx__label">{{ $t(computedHelp) }}</div>
                        <!-- La misma fórmula del encabezado, pero con los
                             valores de esta fila puestos en su lugar. -->
                        <div v-if="trace(r)" class="ws-fx__human">{{ trace(r) }}</div>
                    </div>
                </template>
                <span class="ws-computed">
                    <!-- Sin icono de calculadora: el rótulo "Calculado" ya está
                         en el ENCABEZADO de la columna. Repetirlo fila por fila
                         —aunque sea en 14 px— es decir treinta veces algo que
                         se dice una, y le come ancho al número, que es lo único
                         que el analista viene a leer. El tooltip de la celda
                         sigue explicando de dónde sale el valor. -->
                    <LoadingOutlined v-if="previewState === 'loading'" class="ws-computed__wait" />
                    <span
                        v-else
                        class="ws-computed__value"
                        :class="{ 'ws-computed__value--preview': previewState === 'ready' }"
                    >{{ shownAt(r) }}</span>
                </span>
            </Tooltip>

            <!-- Un ciclo, una fórmula rota o el servidor caído se dicen en la
                 celda. El sistema viejo dejaba el hueco en blanco (o el texto
                 "NaN") y el analista se enteraba semanas después. -->
            <Tooltip v-if="previewMessage" :title="previewMessage">
                <WarningOutlined class="ws-computed__warn" />
            </Tooltip>
        </div>
    </div>

    <!-- ── Selección: la opción se guarda por clave foránea ─────────────────
         El viejo guardaba el ID de la opción como texto junto a todo lo demás:
         borrar una opción dejaba el histórico apuntando a un id inexistente.
         Las ocultas no se ofrecen, pero la fila vieja que ya las usa las
         conserva. -->
    <div v-else-if="field.type === 'select'" class="ws-cell ws-cell--select">
        <div v-for="r in replicates" :key="r" class="ws-cell__line">
            <span v-if="many" class="ws-cell__rep">{{ r }}</span>
            <Select
                :value="valueAt(r) ?? undefined"
                :disabled="disabled"
                allow-clear
                size="small"
                :title="textoDeOpcion(valueAt(r)) || undefined"
                :dropdown-match-select-width="false"
                class="ws-cell__control"
                @change="set(r, $event ?? null)"
            >
                <SelectOption v-for="option in visibleOptions" :key="option.id" :value="option.id">
                    {{ option.value }}
                </SelectOption>
            </Select>
        </div>
    </div>

    <div v-else-if="field.type === 'date'" class="ws-cell ws-cell--date">
        <div v-for="r in replicates" :key="r" class="ws-cell__line">
            <span v-if="many" class="ws-cell__rep">{{ r }}</span>
            <DatePicker
                        autocomplete="off"
                :value="valueAt(r) || null"
                :disabled="disabled"
                size="small"
                value-format="YYYY-MM-DD"
                class="ws-cell__control"
                @change="set(r, $event || null)"
            />
        </div>
    </div>

    <!-- ── Instrumento ──────────────────────────────────────────────────────
         `display="name"`: la celda cerrada muestra SOLO el nombre del equipo
         (PP-LA-01C-100). Agregarle la descripción ("Bureta") repite lo que ya
         dice el encabezado de la columna y obliga a una columna el doble de
         ancha; el desplegable abierto sí la dice, que es donde hace falta. -->
    <div v-else-if="field.type === 'instrument'" class="ws-cell ws-cell--instrument">
        <div v-for="r in replicates" :key="r" class="ws-cell__line">
            <span v-if="many" class="ws-cell__rep">{{ r }}</span>
            <InstrumentSelect
                :instruments="instruments"
                :value="valueAt(r)"
                :disabled="disabled"
                display="name"
                @update:value="set(r, $event)"
            />
        </div>
    </div>

    <!-- ── Número ───────────────────────────────────────────────────────────
         Va como texto y no como InputNumber porque una medición admite el
         signo de censura: ">75" es "el ensayador llegó a su tope", no 75. El
         servidor separa el signo del número y guarda los dos. -->
    <div v-else-if="field.type === 'number'" class="ws-cell ws-cell--number">
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

    <div v-else class="ws-cell ws-cell--text">
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

/* ── El ancho lo pone el TIPO de dato ──────────────────────────────────────
   Todos los controles pedían 130 px, midieran lo que midieran: nueve columnas
   de tres cifras ocupaban lo mismo que nueve observaciones y la fila terminaba
   más ancha que la pantalla. Ahora cada uno pide lo que su dato necesita, que
   es lo que deja leer la hoja sin correrla de costado. */
.ws-cell__control { min-width: 0; width: 100%; }
.ws-cell__control--num { text-align: right; }

/* Un ppm de cinco cifras entra de sobra en 68 px. */
.ws-cell--number .ws-cell__control { min-width: 68px; }
/* Una norma ("ASTM D1533") son diez caracteres, y con la flecha del control no
   entraban en 116 px: la celda decía "ASTM D15…" y desde ahí no se distingue
   la D1533 de la D1500. */
.ws-cell--select .ws-cell__control { min-width: 140px; }
.ws-cell--date   .ws-cell__control { min-width: 116px; }
.ws-cell--text   .ws-cell__control { min-width: 120px; }

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
.ws-computed__value {
    font-family: ui-monospace, Consolas, monospace;
    font-weight: 600;
    color: var(--color-text);
}

/* La vista previa se distingue de lo guardado: es el número que va a quedar,
   pero todavía no quedó. El subrayado punteado lo marca sin cambiar el número
   de lugar ni de tamaño, que haría saltar la fila a cada tecla. */
.ws-computed__value--preview {
    color: var(--color-text-muted);
    border-bottom: 1px dashed var(--color-border);
}

.ws-computed__wait { color: var(--color-text-muted); font-size: 0.8rem; }

/* El bloque de la fórmula dentro del tooltip. `:deep` porque ant-design monta
   el tooltip fuera del componente y el scoped no lo alcanza. La fórmula cruda
   va en monoespaciada: son códigos de columna, no prosa. */
:deep(.ws-fx) { display: flex; flex-direction: column; gap: 4px; max-width: 380px; }
:deep(.ws-fx__label) { font-size: 0.72rem; opacity: 0.75; }
:deep(.ws-fx__human) { font-size: 0.8rem; line-height: 1.4; }
:deep(.ws-fx__raw)   { font-size: 0.7rem; opacity: 0.6; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; word-break: break-word; }

.ws-computed__warn { color: var(--color-warning, #f59e0b); font-size: 0.85rem; }
</style>
