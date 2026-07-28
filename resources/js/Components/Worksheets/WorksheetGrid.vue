<script setup>
/**
 * La grilla de la bancada: una fila por muestra, una columna por campo de la
 * plantilla.
 *
 * Se guarda POR FILA y no con un botón único al pie. El analista carga una
 * muestra, la termina y pasa a la siguiente; guardar todo junto obliga a
 * terminar la tanda entera antes de que el servidor calcule nada, y el cálculo
 * es justamente lo que él necesita ver para saber si la medición le dio bien.
 *
 * Lo que se manda es el borrador de la fila; lo que vuelve es la fila que el
 * servidor guardó, con sus columnas calculadas ya resueltas. El navegador no
 * calcula: en el sistema viejo la fórmula era JavaScript guardado en la base e
 * inyectado en la página, el campo tenía `readonly` (que un envío directo
 * saltea) y cuando la fórmula operaba sobre un campo vacío quedaba el texto
 * "NaN" guardado en la base.
 */
import { computed, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import {
    Button, Dropdown, Menu, MenuItem, Input, Modal, Tag, Tooltip, Empty,
} from 'ant-design-vue';
import {
    PlusOutlined, SaveOutlined, DeleteOutlined, CalculatorOutlined,
} from '@ant-design/icons-vue';

import WorksheetCell from '@/Components/Worksheets/WorksheetCell.vue';
import InstrumentSelect from '@/Components/Worksheets/InstrumentSelect.vue';
import { useI18n } from '@/Plugins/i18n';
import { censoredText, kindColor } from '@/Pages/Worksheets/config/format';

const props = defineProps({
    worksheet:   { type: Object, required: true },
    fields:      { type: Array,  default: () => [] },
    // config/lab_field_types.php: dice en qué columna de la base cae cada tipo.
    // Llega del servidor para no repetir ese mapa acá.
    fieldTypes:  { type: Object, default: () => ({}) },
    instruments: { type: Array,  default: () => [] },
    missing:     { type: Array,  default: () => [] },
    readonly:    { type: Boolean, default: false },
});

const { t } = useI18n();

const KINDS = ['control', 'duplicate', 'blank', 'sample'];

const rows = computed(() => props.worksheet.rows ?? []);

const storageOf = (field) => props.fieldTypes?.[field.type]?.storage ?? 'value_text';

/**
 * Con fórmula es calculado aunque el tipo diga otra cosa: es el mismo criterio
 * del modelo (`TestField::isComputed`), y hace falta porque lo migrado del
 * sistema viejo trae columnas con fórmula tipadas como número.
 */
const isComputed = (field) =>
    field.type === 'computed' || String(field.formula ?? '').trim() !== '';

const sampleCodeField = computed(
    () => props.fields.find((field) => field.role === 'sample_code') ?? null,
);

/**
 * La columna de instrumento de la FILA solo se ofrece cuando la plantilla no
 * declara ninguna columna de instrumento propia. Si la declara, el equipo se
 * elige por columna —una misma prueba usa varios: el Número Ácido lleva bureta
 * y balanza— y repetirlo al costado solo confunde sobre cuál manda.
 */
const showRowInstrument = computed(
    () => !props.fields.some((field) => field.type === 'instrument'),
);

const replicatesOf = (field) => Math.max(1, Number(field.replicates ?? 1));

const valueOf = (row, field, replicate) => (row?.values ?? []).find(
    (value) => Number(value.test_field_id) === Number(field.id)
        && Number(value.replicate_no) === replicate,
) ?? null;

/** El valor guardado, en el formato que edita la celda. */
const editableValue = (field, value) => {
    if (!value) return null;

    const storage = storageOf(field);

    if (storage === 'option_id')     return value.option_id ?? null;
    if (storage === 'instrument_id') return value.instrument_id ?? null;

    // Sin número legible el servidor conserva el texto tal como se escribió, en
    // vez de descartarlo. La celda tiene que devolver ESO para que el analista
    // corrija lo que cargó y no una casilla vacía.
    if (storage === 'value_num') {
        return value.value_num !== null && value.value_num !== undefined
            ? (censoredText(value) || null)
            : (value.value_text ?? null);
    }

    return value.value_text ?? null;
};

/** El valor guardado, en texto, para lo que solo se lee (columnas calculadas). */
const displayValue = (field, value) => {
    if (!value) return '';

    const storage = storageOf(field);

    if (storage === 'option_id') {
        return (field.options ?? []).find((o) => Number(o.id) === Number(value.option_id))?.value ?? '';
    }

    if (storage === 'instrument_id') {
        return props.instruments.find((i) => Number(i.id) === Number(value.instrument_id))?.name ?? '';
    }

    if (storage === 'value_num') {
        return value.value_num !== null && value.value_num !== undefined
            ? censoredText(value)
            : (value.value_text ?? '');
    }

    return value.value_text ?? '';
};

/**
 * Lo que arranca cargado en una fila NUEVA.
 *
 * Una columna marcada como constante (`is_reusable`) arrastra el valor de la
 * fila anterior: el factor de la solución titulante y la temperatura de la sala
 * son los mismos para toda la tanda, y volver a tipearlos en cada muestra es
 * donde se cuelan los errores de dedo. Si no hay fila anterior se usa el valor
 * por omisión de la columna.
 */
const carriedValue = (field, replicate) => {
    if (field.is_reusable) {
        const previous = rows.value[rows.value.length - 1] ?? null;
        const carried = editableValue(field, valueOf(previous, field, replicate));

        if (carried !== null && carried !== '') return carried;
    }

    // El valor por omisión es texto: sirve para lo que se escribe, no para lo
    // que se elige de una lista (ahí el valor es una clave foránea).
    const storage = storageOf(field);
    if (storage === 'option_id' || storage === 'instrument_id') return null;

    return field.default_value ?? null;
};

/** Borrador editable de una fila (o de la que se está agregando). */
const buildDraft = (row = null) => {
    const values = {};
    const isNew = !row?.id;

    for (const field of props.fields) {
        // Las columnas calculadas no viajan: su valor lo produce el servidor y
        // lo que venga del formulario para ellas se descarta igual.
        if (isComputed(field)) continue;

        values[field.code] = {};
        for (let replicate = 1; replicate <= replicatesOf(field); replicate++) {
            values[field.code][replicate] = isNew
                ? carriedValue(field, replicate)
                : editableValue(field, valueOf(row, field, replicate));
        }
    }

    return {
        row_id:        row?.id ?? null,
        kind:          row?.kind ?? defaultKind(),
        position:      row?.position ?? null,
        instrument_id: row?.instrument_id ?? null,
        notes:         row?.notes ?? '',
        values,
    };
};

/** Lo guardado por el servidor, en texto, para las celdas de solo lectura. */
const buildStored = (row) => {
    const stored = {};

    for (const field of props.fields) {
        stored[field.code] = {};
        for (let replicate = 1; replicate <= replicatesOf(field); replicate++) {
            stored[field.code][replicate] = displayValue(field, valueOf(row, field, replicate));
        }
    }

    return stored;
};

/**
 * Qué tipo de fila conviene por omisión. Si a la hoja le falta el patrón o el
 * duplicado, se ofrece eso primero: es el orden en el que hay que cargarlas.
 */
function defaultKind() {
    return props.missing[0] ?? 'sample';
}

const drafts    = ref({});
const baselines = ref({});
const stored    = ref({});
const newDraft  = ref(null);
const savingId  = ref(null);

/** La última fila guardada: la única que debe adoptar lo que devolvió el servidor. */
const lastSaved = ref(null);

/** ¿La fila tiene cambios sin guardar? Es lo que habilita su botón de guardar. */
const isDirty = (id) => JSON.stringify(drafts.value[id]) !== baselines.value[id];

/**
 * Los borradores se rearman con lo que devuelve el servidor después de cada
 * guardado: así la fila muestra el calculado recién resuelto y no el que quedó
 * en pantalla.
 *
 * Las filas que el analista tiene a medio cargar se respetan. Guardar una fila
 * recarga la página entera (la respuesta trae la hoja completa), y pisar con
 * eso lo que hay tipeado en las otras filas borraría mediciones que nadie
 * volvió a leer del instrumento. Lo que sí se actualiza siempre es la
 * referencia contra la que se compara (`baselines`) y lo guardado (`stored`).
 */
const sync = () => {
    const nextDrafts = {};
    const nextBase   = {};
    const nextStored = {};

    for (const row of rows.value) {
        const fresh = buildDraft(row);
        const keepLocal = drafts.value[row.id]
            && row.id !== lastSaved.value
            && isDirty(row.id);

        nextDrafts[row.id] = keepLocal ? drafts.value[row.id] : fresh;
        nextBase[row.id]   = JSON.stringify(fresh);
        nextStored[row.id] = buildStored(row);
    }

    drafts.value    = nextDrafts;
    baselines.value = nextBase;
    stored.value    = nextStored;
    lastSaved.value = null;
};

watch(() => props.worksheet, sync, { immediate: true });

const setCell = (draft, field, replicate, value) => {
    draft.values[field.code][replicate] = value === '' ? null : value;
};

/** El código de muestra que declara la plantilla, tal como quedó en el borrador. */
const sampleCodeOf = (draft) => {
    const field = sampleCodeField.value;
    if (!field) return null;

    const raw = draft.values?.[field.code]?.[1];

    return typeof raw === 'string' && raw.trim() !== '' ? raw.trim() : null;
};

const payloadOf = (draft) => {
    const body = {
        row_id:        draft.row_id,
        kind:          draft.kind,
        position:      draft.position,
        instrument_id: draft.instrument_id,
        notes:         draft.notes || null,
        values:        draft.values,
    };

    // El código de la muestra sale de la columna que DECLARA llevarlo, no de la
    // primera columna de la tabla. Se manda solo en las filas de muestra: un
    // patrón, un duplicado o un blanco no son de un cliente y no llevan código.
    // Si la plantilla no declara esa columna, la clave se omite y el servidor
    // resuelve — mandar null la fijaría en nulo por encima de su criterio.
    if (draft.kind === 'sample' && sampleCodeField.value) {
        body.sample_code = sampleCodeOf(draft);
    }

    return body;
};

const save = (draft) => {
    savingId.value = draft.row_id ?? 'new';
    lastSaved.value = draft.row_id;

    router.post(
        route('lab_management.worksheets.rows.save', props.worksheet.slug),
        payloadOf(draft),
        {
            preserveScroll: true,
            onSuccess: () => { if (!draft.row_id) newDraft.value = null; },
            onFinish:  () => { savingId.value = null; },
        },
    );
};

const remove = (row) => {
    Modal.confirm({
        title:      t('global.delete_confirm_title'),
        content:    t('global.are_you_sure'),
        okText:     t('global.delete'),
        okType:     'danger',
        cancelText: t('global.cancel'),
        onOk: () => router.delete(
            route('lab_management.worksheets.rows.destroy', [props.worksheet.slug, row.id]),
            { preserveScroll: true },
        ),
    });
};

const startRow = (kind) => { newDraft.value = buildDraft({ kind }); };
const cancelRow = () => { newDraft.value = null; };

/**
 * Por qué no se puede agregar una muestra todavía. El sistema viejo se
 * limitaba a sacar la opción del select y no decía nada: la hoja quedaba sin
 * respaldo de calidad y el analista no entendía por qué le faltaba una opción.
 */
const missingReason = computed(() => t('worksheets.errors.missing_prerequisites', {
    kinds: props.missing.map((kind) => t(`worksheets.kind.${kind}`)).join(', '),
}));

const kindDisabled = (kind) => kind === 'sample' && props.missing.length > 0;
</script>

<template>
    <div class="ws-grid">
        <!-- El scroll horizontal vive ACÁ: la tabla es su propio contenedor y
             el cuerpo de la página nunca se corre de costado en el teléfono. -->
        <div class="ws-grid__scroll">
            <table class="ws-table">
                <thead>
                    <tr>
                        <th class="ws-th ws-th--kind">{{ $t('test_fields.type') }}</th>

                        <th v-for="field in fields" :key="field.id" class="ws-th">
                            <div class="ws-th__label">
                                {{ field.label }}
                                <span v-if="field.is_required" class="ws-th__req">*</span>
                            </div>
                            <div class="ws-th__meta">
                                <span v-if="field.unit">{{ field.unit }}</span>
                                <Tooltip v-if="isComputed(field)" :title="$t('worksheets.computed_help')">
                                    <Tag :bordered="false" class="ws-th__tag">
                                        <CalculatorOutlined /> {{ $t('worksheets.computed') }}
                                    </Tag>
                                </Tooltip>
                            </div>
                        </th>

                        <th v-if="showRowInstrument" class="ws-th">{{ $t('instruments.singular') }}</th>
                        <th class="ws-th">{{ $t('worksheets.notes') }}</th>
                        <th v-if="!readonly" class="ws-th ws-th--actions">{{ $t('global.actions') }}</th>
                    </tr>
                </thead>

                <tbody>
                    <tr
                        v-for="row in rows"
                        :key="row.id"
                        class="ws-row"
                        :class="`ws-row--${row.kind}`"
                    >
                        <td class="ws-td ws-td--kind">
                            <Tooltip :title="$t(`worksheets.kind_help.${row.kind}`)">
                                <Tag :color="kindColor(row.kind)" :bordered="false">
                                    {{ $t(`worksheets.kind.${row.kind}`) }}
                                </Tag>
                            </Tooltip>
                            <div v-if="row.sample_code" class="ws-td__code">{{ row.sample_code }}</div>
                        </td>

                        <td v-for="field in fields" :key="field.id" class="ws-td">
                            <WorksheetCell
                                :field="field"
                                :values="drafts[row.id]?.values?.[field.code] ?? {}"
                                :stored="stored[row.id]?.[field.code] ?? {}"
                                :instruments="instruments"
                                :disabled="readonly"
                                @update="(replicate, value) => setCell(drafts[row.id], field, replicate, value)"
                            />
                        </td>

                        <td v-if="showRowInstrument" class="ws-td">
                            <InstrumentSelect
                                :instruments="instruments"
                                :value="drafts[row.id]?.instrument_id ?? null"
                                :disabled="readonly"
                                @update:value="(value) => (drafts[row.id].instrument_id = value)"
                            />
                        </td>

                        <td class="ws-td">
                            <Input
                                :value="drafts[row.id]?.notes ?? ''"
                                :disabled="readonly"
                                size="small"
                                class="ws-notes"
                                @change="(event) => (drafts[row.id].notes = event.target.value)"
                            />
                        </td>

                        <td v-if="!readonly" class="ws-td ws-td--actions">
                            <Tooltip :title="$t('global.save')">
                                <Button
                                    type="primary"
                                    size="small"
                                    :disabled="!isDirty(row.id)"
                                    :loading="savingId === row.id"
                                    @click="save(drafts[row.id])"
                                >
                                    <SaveOutlined />
                                </Button>
                            </Tooltip>
                            <Tooltip :title="$t('global.delete')">
                                <Button danger size="small" @click="remove(row)">
                                    <DeleteOutlined />
                                </Button>
                            </Tooltip>
                        </td>
                    </tr>

                    <!-- La fila que se está agregando. Va en la grilla y no en un
                         modal aparte: se carga igual que las demás. -->
                    <tr v-if="newDraft" class="ws-row ws-row--new" :class="`ws-row--${newDraft.kind}`">
                        <td class="ws-td ws-td--kind">
                            <Tooltip :title="$t(`worksheets.kind_help.${newDraft.kind}`)">
                                <Tag :color="kindColor(newDraft.kind)" :bordered="false">
                                    {{ $t(`worksheets.kind.${newDraft.kind}`) }}
                                </Tag>
                            </Tooltip>
                        </td>

                        <td v-for="field in fields" :key="field.id" class="ws-td">
                            <WorksheetCell
                                :field="field"
                                :values="newDraft.values[field.code] ?? {}"
                                :stored="{}"
                                :instruments="instruments"
                                @update="(replicate, value) => setCell(newDraft, field, replicate, value)"
                            />
                        </td>

                        <td v-if="showRowInstrument" class="ws-td">
                            <InstrumentSelect
                                :instruments="instruments"
                                :value="newDraft.instrument_id"
                                @update:value="(value) => (newDraft.instrument_id = value)"
                            />
                        </td>

                        <td class="ws-td">
                            <Input
                                :value="newDraft.notes"
                                size="small"
                                class="ws-notes"
                                @change="(event) => (newDraft.notes = event.target.value)"
                            />
                        </td>

                        <td class="ws-td ws-td--actions">
                            <Tooltip :title="$t('global.save')">
                                <Button
                                    type="primary"
                                    size="small"
                                    :loading="savingId === 'new'"
                                    @click="save(newDraft)"
                                >
                                    <SaveOutlined />
                                </Button>
                            </Tooltip>
                            <Tooltip :title="$t('global.cancel')">
                                <Button size="small" @click="cancelRow">
                                    {{ $t('global.cancel') }}
                                </Button>
                            </Tooltip>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Empty
            v-if="rows.length === 0 && !newDraft"
            :description="$t('worksheets.empty_rows')"
            class="ws-empty"
        />

        <div v-if="!readonly" class="ws-grid__foot">
            <Dropdown :trigger="['click']" placement="topLeft">
                <Button type="dashed">
                    <PlusOutlined /> {{ $t('worksheets.actions.add_row') }}
                </Button>
                <template #overlay>
                    <Menu>
                        <MenuItem
                            v-for="kind in KINDS"
                            :key="kind"
                            :disabled="kindDisabled(kind)"
                            @click="startRow(kind)"
                        >
                            <Tooltip
                                placement="right"
                                :title="kindDisabled(kind) ? missingReason : $t(`worksheets.kind_help.${kind}`)"
                            >
                                <span>{{ $t(`worksheets.kind.${kind}`) }}</span>
                            </Tooltip>
                        </MenuItem>
                    </Menu>
                </template>
            </Dropdown>
        </div>
    </div>
</template>

<style scoped>
.ws-grid { display: flex; flex-direction: column; gap: 12px; }

/* La grilla scrollea sola. Sin esto la página entera se corre de costado en el
   teléfono y la barra inferior de acciones deja de estar donde el pulgar la
   busca. */
.ws-grid__scroll {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    border: 1px solid var(--color-border);
    border-radius: 10px;
    background: var(--color-surface);
}

.ws-table { width: max-content; min-width: 100%; border-collapse: separate; border-spacing: 0; }

.ws-th {
    position: sticky;
    top: 0;
    z-index: 3;
    background: var(--color-surface);
    border-bottom: 1px solid var(--color-border);
    padding: 8px 10px;
    text-align: left;
    vertical-align: bottom;
    white-space: nowrap;
}
.ws-th__label { font-size: 0.78rem; font-weight: 600; color: var(--color-text-strong); }
.ws-th__req   { color: var(--color-danger-bright); margin-left: 2px; }
.ws-th__meta  { display: flex; align-items: center; gap: 6px; font-size: 0.7rem; color: var(--color-text-muted); }
.ws-th__tag   { font-size: 0.62rem; }

/* La columna del tipo de fila queda fija: al correr la tabla de costado hay
   que seguir sabiendo si lo que se está leyendo es una muestra o el patrón. */
.ws-th--kind, .ws-td--kind { position: sticky; left: 0; z-index: 4; background: var(--color-surface); }
.ws-th--kind { z-index: 5; }
.ws-th--actions, .ws-td--actions { text-align: right; }

.ws-td {
    padding: 8px 10px;
    border-bottom: 1px solid var(--color-border-soft);
    vertical-align: top;
}
.ws-td__code {
    margin-top: 4px;
    font-family: ui-monospace, Consolas, monospace;
    font-size: 0.72rem;
    color: var(--color-text-muted);
}
.ws-td--actions { white-space: nowrap; }
.ws-td--actions .ant-btn + .ant-btn { margin-left: 6px; }

.ws-notes { min-width: 160px; }

/* Tintes por tipo de fila. Van en rgba translúcido sobre la superficie para
   que funcionen igual en tema claro y oscuro.
   En la columna fija el tinte va como IMAGEN sobre un color de fondo opaco: si
   fuera translúcido, el resto de la tabla se vería pasar por debajo al
   scrollear de costado. */
.ws-row--control   .ws-td { background-color: rgba(114, 46, 209, 0.07); }
.ws-row--duplicate .ws-td { background-color: rgba(212, 136, 6, 0.09); }
.ws-row--blank     .ws-td { background-color: rgba(19, 151, 168, 0.08); }
.ws-row--new       .ws-td { background-color: var(--tint-dirty); }

.ws-row .ws-td--kind { background-color: var(--color-surface); }
.ws-row--control   .ws-td--kind { background-image: linear-gradient(rgba(114, 46, 209, 0.07), rgba(114, 46, 209, 0.07)); }
.ws-row--duplicate .ws-td--kind { background-image: linear-gradient(rgba(212, 136, 6, 0.09), rgba(212, 136, 6, 0.09)); }
.ws-row--blank     .ws-td--kind { background-image: linear-gradient(rgba(19, 151, 168, 0.08), rgba(19, 151, 168, 0.08)); }
.ws-row--new       .ws-td--kind { background-image: linear-gradient(var(--tint-dirty), var(--tint-dirty)); }

.ws-empty { padding: 24px 8px; }
.ws-grid__foot { display: flex; gap: 8px; flex-wrap: wrap; }
</style>
