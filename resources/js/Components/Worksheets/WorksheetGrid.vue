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
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ LA IDENTIFICACIÓN DE LA FILA NO SE VA CON EL SCROLL                      │
 * └──────────────────────────────────────────────────────────────────────────┘
 * La hoja se ensancha con la plantilla: el Número Ácido tiene 9 columnas, la
 * cromatografía 13 y el Grado de Polimerización 16. Al correr la tabla de
 * costado para cargar la última, el analista dejaba de ver DE QUÉ MUESTRA es la
 * fila que está tipeando, que es la forma más barata de cargar un resultado en
 * la muestra equivocada.
 *
 * Por eso el tipo de fila y el Nº de muestra —las dos columnas que dicen qué es
 * esta fila— quedan CLAVADAS a la izquierda. El Nº de muestra no está escrito
 * acá: es la columna que la plantilla declara con `role = sample_code`, y por
 * eso se saca del recorrido de campos y se dibuja junto al tipo, aunque en la
 * plantilla venga en otra posición.
 *
 * VISTA PREVIA MIENTRAS SE ESCRIBE
 * El analista necesita ver el resultado antes de guardar —es como se da cuenta
 * de que la titulación le salió mal mientras todavía tiene la muestra—, y eso
 * es lo que el sistema viejo resolvía con JavaScript en la página. Acá se
 * resuelve preguntándole al servidor: la grilla manda lo tipeado a
 * `worksheets.preview`, que corre EL MISMO motor que el guardado y no escribe
 * nada. Este componente no tiene ni una fórmula: manda datos y dibuja números.
 */
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import {
    Button, Dropdown, Menu, MenuItem, MenuDivider, Input, Modal, Tag, Tooltip, Empty,
} from 'ant-design-vue';
import {
    PlusOutlined, SaveOutlined, DeleteOutlined, CalculatorOutlined,
} from '@ant-design/icons-vue';

import WorksheetCell from '@/Components/Worksheets/WorksheetCell.vue';
import InstrumentSelect from '@/Components/Worksheets/InstrumentSelect.vue';
import SampleTestSelect from '@/Components/Worksheets/SampleTestSelect.vue';
import { useI18n } from '@/Plugins/i18n';
import { useDateFormat } from '@/Composables/useDateFormat';
import { censoredText, kindColor } from '@/Pages/Worksheets/config/format';

const props = defineProps({
    worksheet:   { type: Object, required: true },
    fields:      { type: Array,  default: () => [] },
    // config/lab_field_types.php: dice en qué columna de la base cae cada tipo.
    // Llega del servidor para no repetir ese mapa acá.
    fieldTypes:  { type: Object, default: () => ({}) },
    instruments: { type: Array,  default: () => [] },
    instrumentsByField: { type: Object, default: () => ({}) },
    // Las pruebas que esta hoja todavía espera. La fila de muestra se ata a una
    // de ellas por clave foránea, en vez de tipear el correlativo.
    pendingTests: { type: Array, default: () => [] },
    // Quién registró cada fila: `{ [row_id]: { name, at } }`. Sale de
    // `worksheet_values.entered_by`, que se escribe desde siempre y no se
    // mostraba en ninguna pantalla.
    enteredBy:   { type: Object, default: () => ({}) },
    missing:     { type: Array,  default: () => [] },
    readonly:    { type: Boolean, default: false },
});

const { t } = useI18n();
const { formatDateTimeFull } = useDateFormat();

const KINDS = ['control', 'duplicate', 'blank', 'sample'];

const rows = computed(() => props.worksheet.rows ?? []);

/** Quién registró la fila, y cuándo (para el tooltip). */
const enteredByOf = (row) => props.enteredBy?.[row.id] ?? null;

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
 * Las columnas que van en el cuerpo de la tabla: todas menos la del Nº de
 * muestra, que se dibuja aparte y clavada a la izquierda junto al tipo de fila.
 */
const bodyFields = computed(
    () => props.fields.filter((field) => field !== sampleCodeField.value),
);

/**
 * El ancho de una columna sale de su TIPO, no de lo que mida su contenido.
 * Un número de tres cifras no necesita el mismo lugar que una observación, y
 * dejar que cada columna se estire sola es lo que llevaba la fila a los dos mil
 * píxeles. Se usa como clase (`ws-col--number`), y el CSS de abajo pone el
 * ancho.
 */
/**
 * Los instrumentos que ofrece UNA columna.
 *
 * El catálogo del laboratorio ya trae esa relación (la bureta en la columna de
 * la bureta, el colorímetro en la del color); la grilla la ignoraba y ofrecía
 * los 24 instrumentos en todas las columnas, así que en la columna de la bureta
 * se podía elegir el colorímetro. Sin relación cargada se cae al catálogo
 * completo: es preferible ofrecer de más que dejar la columna sin opciones.
 */
const instrumentsFor = (field) => props.instrumentsByField?.[field.id] ?? props.instruments;

const columnKind = (field) => {
    if (isComputed(field)) return 'computed';

    return ['number', 'select', 'date', 'instrument'].includes(field.type)
        ? field.type
        : 'text';
};

/**
 * La columna de instrumento de la FILA casi nunca corresponde.
 *
 * Si la plantilla declara columnas de instrumento propias (bureta, balanza…),
 * el equipo se elige por columna y repetirlo al costado confunde sobre cuál
 * manda. Y si NO las declara es porque la prueba no usa instrumentos
 * (cromatografía, partículas, condición visual…): mostrar igual un selector
 * con los 24 equipos del laboratorio era ofrecer un dato que no existe —
 * "como si fuera global". Solo queda visible como respaldo de lectura cuando
 * alguna fila vieja ya trae un instrumento cargado a nivel de fila.
 */
const showRowInstrument = computed(
    () => !props.fields.some((field) => field.type === 'instrument')
        && rows.value.some((row) => row.instrument_id),
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

    // El NOMBRE (PP-LA-01C-100): identifica al equipo sin ambigüedad y entra en
    // una celda de tabla. Agregarle la descripción ("Bureta") repite lo que ya
    // dice el encabezado de la columna y se come su ancho.
    if (storage === 'instrument_id') {
        const instrument = props.instruments.find(
            (i) => Number(i.id) === Number(value.instrument_id),
        );

        return instrument?.name || '';
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
        sample_test_id: row?.sample_test_id ?? null,
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

// ── Vista previa del cálculo ─────────────────────────────────────────────

/**
 * Estado de la vista previa POR FILA: `{ status, values, errors, cycles }`.
 * `status` es 'loading' | 'ready' | 'failed'; sin entrada, la celda muestra lo
 * que hay guardado. La clave es el id de la fila, o 'new' para la que se está
 * agregando.
 */
const previews = ref({});

/**
 * 400 ms de silencio antes de preguntar. Es la pausa que hace cualquiera al
 * terminar de tipear un número, y basta para que una medición de seis dígitos
 * salga en una sola petición en vez de en seis.
 */
const PREVIEW_DELAY = 400;

const timers   = {};
const inflight = {};

const previewKey = (draft) => draft.row_id ?? 'new';

/** ¿Hay algo que previsualizar? Sin columnas calculadas no se pregunta nada. */
const hasComputed = computed(() => props.fields.some(isComputed));

const setPreview = (key, patch) => {
    previews.value = {
        ...previews.value,
        [key]: { ...(previews.value[key] ?? {}), ...patch },
    };
};

const askPreview = async (key, draft) => {
    // Si el analista siguió tecleando, la respuesta que viene en camino ya no
    // corresponde a lo que hay en pantalla: se cancela en vez de dejar que
    // llegue tarde y pise el resultado de la petición nueva.
    inflight[key]?.abort();

    const controller = new AbortController();
    inflight[key] = controller;

    try {
        const { data } = await window.axios.post(
            route('lab_management.worksheets.preview', props.worksheet.slug),
            { values: draft.values },
            { signal: controller.signal },
        );

        setPreview(key, {
            status: 'ready',
            values: data?.values ?? {},
            errors: data?.errors ?? {},
            cycles: data?.cycles ?? [],
        });
    } catch (error) {
        // La abortó una petición más nueva: esa es la que manda, no hay nada
        // que informar.
        if (error?.code === 'ERR_CANCELED' || error?.name === 'CanceledError') return;

        // Sin servidor la celda queda VACÍA con el aviso. Calcularla acá sería
        // volver exactamente al sistema viejo.
        setPreview(key, { status: 'failed', values: {}, errors: {}, cycles: [] });
    } finally {
        if (inflight[key] === controller) inflight[key] = null;
    }
};

const schedulePreview = (draft) => {
    if (props.readonly || !hasComputed.value) return;

    const key = previewKey(draft);

    // El estado pasa a "calculando" apenas cambia el dato, no cuando sale la
    // petición: un número viejo que ya no corresponde a lo que hay en pantalla
    // se lee como si fuera el actual, y eso es peor que una celda en blanco.
    setPreview(key, { status: 'loading' });

    clearTimeout(timers[key]);
    timers[key] = setTimeout(() => askPreview(key, draft), PREVIEW_DELAY);
};

/**
 * Qué informar en la celda calculada de esta columna. Un ciclo o una fórmula
 * rota son problemas de la PLANTILLA y hay que decirlos donde se ven, no
 * dejar la celda muda.
 */
const previewMessage = (key, field) => {
    const preview = previews.value[key];
    if (!preview) return '';

    const cycle = (preview.cycles ?? []).find((path) => path.includes(field.code));
    if (cycle) return t('worksheets.formula_cycle', { path: cycle.join(' → ') });

    if ((preview.errors ?? {})[field.code]) {
        return t('worksheets.formula_error', { field: field.label });
    }

    return preview.status === 'failed' ? t('worksheets.preview_failed') : '';
};

const forgetPreview = (key) => {
    clearTimeout(timers[key]);
    inflight[key]?.abort();
    inflight[key] = null;

    const next = { ...previews.value };
    delete next[key];
    previews.value = next;
};

onBeforeUnmount(() => {
    Object.keys(timers).forEach((key) => clearTimeout(timers[key]));
    Object.values(inflight).forEach((controller) => controller?.abort());
});

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

        // La vista previa de una fila que se acaba de recargar del servidor ya
        // no hace falta: lo guardado ES el cálculo. La de una fila que el
        // analista tiene a medio cargar se conserva, porque su borrador no
        // cambió y el número sigue siendo el que corresponde.
        if (!keepLocal) forgetPreview(row.id);
    }

    drafts.value    = nextDrafts;
    baselines.value = nextBase;
    stored.value    = nextStored;
    lastSaved.value = null;
};

watch(() => props.worksheet, sync, { immediate: true });

const setCell = (draft, field, replicate, value) => {
    draft.values[field.code][replicate] = value === '' ? null : value;

    // Se pregunta ante CUALQUIER cambio de celda y no solo ante los campos que
    // la fórmula usa: saber cuáles son exigiría leer la fórmula en el
    // navegador, que es justo lo que este cambio saca de acá. Una consulta de
    // más no cuesta nada; una celda que no se entera de que su dato cambió
    // muestra un número que ya no corresponde.
    schedulePreview(draft);
};

/** El código de muestra que declara la plantilla, tal como quedó en el borrador. */
/**
 * Al elegir la muestra se llena también la celda del correlativo.
 *
 * El enlace de verdad es `sample_test_id`, pero la columna del Nº de muestra
 * existe en la plantilla y se imprime en la hoja de bancada firmada: dejarla
 * vacía porque ahora hay una clave foránea sería perder el papel.
 */
const onSamplePicked = (draft, test) => {
    if (!sampleCodeField.value) return;

    setCell(draft, sampleCodeField.value, 1, test?.code ?? '');
};

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
        // `notes` NO viaja: la columna Observaciones se quitó de la grilla
        // (2026-08-03, pedido del laboratorio). La clave se OMITE en vez de
        // mandarse en nulo para que `WorksheetService::saveRow` conserve lo que
        // ya está escrito en las filas viejas — mandar null lo borraría.
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

    // El enlace con la prueba pedida. Solo en filas de muestra: un patrón, un
    // duplicado o un blanco no vienen de ninguna. Se manda incluso en null
    // —a diferencia del equipo— porque desasignar la muestra de una fila ya
    // guardada tiene que poder hacerse.
    if (draft.kind === 'sample') {
        body.sample_test_id = draft.sample_test_id ?? null;
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
            onSuccess: () => {
                if (draft.row_id) return;
                forgetPreview('new');
                newDraft.value = null;
            },
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

const startRow = (kind) => {
    forgetPreview('new');
    newDraft.value = buildDraft({ kind });

    // La fila nueva puede arrancar con valores arrastrados de la anterior
    // (columnas constantes) o con el valor por omisión de la columna: ya hay
    // algo que calcular sin que el analista haya tocado una tecla.
    schedulePreview(newDraft.value);
};

const cancelRow = () => {
    forgetPreview('new');
    newDraft.value = null;
};

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

                        <!-- El Nº de muestra, clavado junto al tipo de fila: son
                             las dos columnas que dicen de qué es esta fila, y
                             tienen que seguir a la vista con la tabla corrida. -->
                        <th v-if="sampleCodeField" class="ws-th ws-th--code">
                            <div class="ws-th__label">
                                {{ sampleCodeField.label }}
                                <span v-if="sampleCodeField.is_required" class="ws-th__req">*</span>
                            </div>
                        </th>

                        <!-- El equipo va a la IZQUIERDA, junto al tipo de fila y
                             al código: es de qué se tomó la muestra, no un dato
                             medido. Si queda al final de treinta columnas, se
                             carga cuando alguien se acuerda. -->

                        <th
                            v-for="field in bodyFields"
                            :key="field.id"
                            class="ws-th"
                            :class="`ws-col--${columnKind(field)}`"
                        >
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
                        <!-- Quién la registró. Reemplaza a Observaciones, que
                             se quitó por pedido del laboratorio: nadie la
                             llenaba y quién cargó el dato sí se pregunta. -->
                        <th class="ws-th ws-th--who">{{ $t('worksheets.entered_by') }}</th>
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
                            <!-- El código guardado se repite acá SOLO si la
                                 plantilla no declara su columna: con la columna
                                 a la vista sería el mismo dato dos veces. -->
                            <div v-if="row.sample_code && !sampleCodeField" class="ws-td__code">
                                {{ row.sample_code }}
                            </div>
                        </td>

                        <td v-if="sampleCodeField" class="ws-td ws-td--code">
                            <SampleTestSelect
                                v-if="drafts[row.id]?.kind === 'sample' && pendingTests.length"
                                :tests="pendingTests"
                                :value="drafts[row.id].sample_test_id"
                                :disabled="readonly"
                                @update:value="(value) => (drafts[row.id].sample_test_id = value)"
                                @picked="(test) => onSamplePicked(drafts[row.id], test)"
                            />
                            <WorksheetCell
                                v-else
                                :field="sampleCodeField"
                                :values="drafts[row.id]?.values?.[sampleCodeField.code] ?? {}"
                                :stored="stored[row.id]?.[sampleCodeField.code] ?? {}"
                                :instruments="instruments"
                                :disabled="readonly"
                                @update="(replicate, value) => setCell(drafts[row.id], sampleCodeField, replicate, value)"
                            />
                        </td>


                        <td
                            v-for="field in bodyFields"
                            :key="field.id"
                            class="ws-td"
                            :class="`ws-col--${columnKind(field)}`"
                        >
                            <WorksheetCell
                                :field="field"
                                :values="drafts[row.id]?.values?.[field.code] ?? {}"
                                :stored="stored[row.id]?.[field.code] ?? {}"
                                :preview="previews[row.id]?.values?.[field.code] ?? {}"
                                :preview-state="previews[row.id]?.status ?? 'idle'"
                                :preview-message="previewMessage(row.id, field)"
                                :instruments="instrumentsFor(field)"
                                :disabled="readonly"
                                @update="(replicate, value) => setCell(drafts[row.id], field, replicate, value)"
                            />
                        </td>

                        <!-- `display="code"`: en la celda cerrada va SOLO el
                             código del equipo (PP-LA-01C-100). El nombre repite
                             el código y se come el ancho de la columna; el
                             desplegable abierto sí lo muestra entero. -->
                        <td v-if="showRowInstrument" class="ws-td ws-col--instrument">
                            <InstrumentSelect
                                :instruments="instruments"
                                :value="drafts[row.id]?.instrument_id ?? null"
                                :disabled="readonly"
                                display="code"
                                @update:value="(value) => (drafts[row.id].instrument_id = value)"
                            />
                        </td>

                        <td class="ws-td ws-td--who">
                            <Tooltip v-if="enteredByOf(row)" :title="formatDateTimeFull(enteredByOf(row).at)">
                                <span>{{ enteredByOf(row).name }}</span>
                            </Tooltip>
                            <span v-else class="ws-who--empty">—</span>
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

                        <td v-if="sampleCodeField" class="ws-td ws-td--code">
                            <SampleTestSelect
                                v-if="newDraft.kind === 'sample' && pendingTests.length"
                                :tests="pendingTests"
                                :value="newDraft.sample_test_id"
                                @update:value="(value) => (newDraft.sample_test_id = value)"
                                @picked="(test) => onSamplePicked(newDraft, test)"
                            />
                            <WorksheetCell
                                v-else
                                :field="sampleCodeField"
                                :values="newDraft.values[sampleCodeField.code] ?? {}"
                                :stored="{}"
                                :instruments="instruments"
                                @update="(replicate, value) => setCell(newDraft, sampleCodeField, replicate, value)"
                            />
                        </td>


                        <td
                            v-for="field in bodyFields"
                            :key="field.id"
                            class="ws-td"
                            :class="`ws-col--${columnKind(field)}`"
                        >
                            <WorksheetCell
                                :field="field"
                                :values="newDraft.values[field.code] ?? {}"
                                :stored="{}"
                                :preview="previews.new?.values?.[field.code] ?? {}"
                                :preview-state="previews.new?.status ?? 'idle'"
                                :preview-message="previewMessage('new', field)"
                                :instruments="instrumentsFor(field)"
                                @update="(replicate, value) => setCell(newDraft, field, replicate, value)"
                            />
                        </td>

                        <td v-if="showRowInstrument" class="ws-td ws-col--instrument">
                            <InstrumentSelect
                                :instruments="instruments"
                                :value="newDraft.instrument_id"
                                display="code"
                                @update:value="(value) => (newDraft.instrument_id = value)"
                            />
                        </td>

                        <!-- La fila nueva todavía no tiene autor: lo escribe el
                             servidor al guardar, con el usuario de la sesión. -->
                        <td class="ws-td ws-td--who"><span class="ws-who--empty">—</span></td>

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
                        <!-- La muestra NO se ofrece hasta que estén el patrón y
                             el duplicado que la prueba exige. Ofrecerla en gris
                             invita a hacer clic y a recibir un error; sacarla de
                             la lista dice sin palabras qué falta hacer primero.
                             El aviso de por qué no está va abajo del menú, que
                             es donde se mira cuando algo no aparece. -->
                        <MenuItem
                            v-for="kind in KINDS.filter((k) => !kindDisabled(k))"
                            :key="kind"
                            @click="startRow(kind)"
                        >
                            <Tooltip placement="right" :title="$t(`worksheets.kind_help.${kind}`)">
                                <span>{{ $t(`worksheets.kind.${kind}`) }}</span>
                            </Tooltip>
                        </MenuItem>
                        <MenuDivider v-if="missing.length > 0" />
                        <MenuItem v-if="missing.length > 0" disabled>
                            <span class="ws-menu__why">{{ missingReason }}</span>
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

/* El ancho de la columna del tipo de fila es un NÚMERO CONOCIDO porque de él
   depende dónde arranca la columna del Nº de muestra, que va clavada al lado.
   Con un ancho automático el `left` de la segunda columna fija sería una
   adivinanza y quedaría un hueco (o una superposición) al scrollear. */
.ws-table {
    --ws-kind-w: 116px;
    width: max-content;
    min-width: 100%;
    border-collapse: separate;
    border-spacing: 0;
}

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

/* Las dos columnas de identificación quedan fijas: al correr la tabla de
   costado hay que seguir sabiendo si lo que se lee es una muestra o el patrón,
   y DE QUÉ MUESTRA es la fila. Son las únicas dos que se congelan — con más, la
   parte que se puede correr deja de entrar en una pantalla de portátil. */
.ws-th--kind, .ws-td--kind,
.ws-th--code, .ws-td--code { position: sticky; z-index: 4; background: var(--color-surface); }
.ws-th--kind, .ws-th--code { z-index: 5; }

.ws-th--kind, .ws-td--kind { left: 0; width: var(--ws-kind-w); min-width: var(--ws-kind-w); }
.ws-th--code, .ws-td--code { left: var(--ws-kind-w); width: 132px; min-width: 132px; }

/* El corte entre lo que queda fijo y lo que se corre. Sin esa línea las dos
   partes se leen como una sola tabla y el salto al scrollear desorienta. */
.ws-th--code, .ws-td--code { border-right: 1px solid var(--color-border); }
.ws-table:not(:has(.ws-th--code)) :is(.ws-th--kind, .ws-td--kind) {
    border-right: 1px solid var(--color-border);
}

.ws-th--actions, .ws-td--actions { text-align: right; }

/* ── Ancho por TIPO de columna ─────────────────────────────────────────────
   Antes mandaba el contenido y la fila se iba a los dos mil píxeles: un número
   de tres cifras ocupaba lo mismo que una observación. El ancho se declara por
   tipo, y el rótulo largo del encabezado parte en dos líneas en vez de estirar
   la columna entera. */
.ws-th[class*="ws-col--"] { white-space: normal; }
.ws-th[class*="ws-col--"] .ws-th__label { white-space: normal; overflow-wrap: anywhere; }

.ws-col--number   { width: 92px;  max-width: 92px; }
.ws-col--computed { width: 128px; max-width: 128px; }
.ws-col--select,
.ws-col--date     { width: 140px; max-width: 140px; }
.ws-col--text     { width: 150px; max-width: 150px; }
.ws-col--instrument { width: 160px; max-width: 160px; }

/* Quién registró la fila: es contexto, no dato de trabajo. */
.ws-th--who { width: 150px; }
.ws-td--who { font-size: 0.78rem; color: var(--color-text-muted); white-space: nowrap; }
.ws-who--empty { color: var(--color-text-muted); }

/* El selector de equipo necesita ancho: el nombre de un equipo es largo y
   recortarlo obliga a abrir el desplegable para saber cuál está elegido. */

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

/* Tintes por tipo de fila. Van en rgba translúcido sobre la superficie para
   que funcionen igual en tema claro y oscuro.
   En la columna fija el tinte va como IMAGEN sobre un color de fondo opaco: si
   fuera translúcido, el resto de la tabla se vería pasar por debajo al
   scrollear de costado. */
.ws-row--control   .ws-td { background-color: rgba(114, 46, 209, 0.07); }
.ws-row--duplicate .ws-td { background-color: rgba(212, 136, 6, 0.09); }
.ws-row--blank     .ws-td { background-color: rgba(19, 151, 168, 0.08); }
.ws-row--new       .ws-td { background-color: var(--tint-dirty); }

.ws-row :is(.ws-td--kind, .ws-td--code) { background-color: var(--color-surface); }
.ws-row--control   :is(.ws-td--kind, .ws-td--code) { background-image: linear-gradient(rgba(114, 46, 209, 0.07), rgba(114, 46, 209, 0.07)); }
.ws-row--duplicate :is(.ws-td--kind, .ws-td--code) { background-image: linear-gradient(rgba(212, 136, 6, 0.09), rgba(212, 136, 6, 0.09)); }
.ws-row--blank     :is(.ws-td--kind, .ws-td--code) { background-image: linear-gradient(rgba(19, 151, 168, 0.08), rgba(19, 151, 168, 0.08)); }
.ws-row--new       :is(.ws-td--kind, .ws-td--code) { background-image: linear-gradient(var(--tint-dirty), var(--tint-dirty)); }

.ws-menu__why { font-size: 0.75rem; color: var(--color-text-muted); white-space: normal; display: block; max-width: 260px; line-height: 1.4; }
.ws-empty { padding: 24px 8px; }
.ws-grid__foot { display: flex; gap: 8px; flex-wrap: wrap; }
</style>
