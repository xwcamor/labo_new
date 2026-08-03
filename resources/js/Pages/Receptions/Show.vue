<script setup>
/**
 * La ficha de la recepción.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ ESTA PANTALLA NO ESCRIBE NADA AL ABRIRSE                                 │
 * └──────────────────────────────────────────────────────────────────────────┘
 * Todo lo que se ve llega como dato ya calculado: el avance de TODAS las
 * muestras sale de una sola consulta agregada (`progress`, indexado por
 * `sample_id`) y lo que le falta a la entrega lo dice el servidor (`missing`).
 * Acá no se recorre ni se recuenta nada, y sobre todo no se guarda nada por el
 * hecho de mirar.
 *
 * En el sistema anterior abrir la remisión ejecutaba `update` y `update_all`
 * desde la propia vista —unas 320 consultas y 40 escrituras para una entrega de
 * 40 muestras— y, peor, el estado dependía de que alguien abriera la pantalla:
 * si nadie la abría, los filtros del listado mentían.
 *
 * Se escribe solo cuando el usuario lo pide, y cada cosa por su ruta: confirmar
 * (emite los correlativos), asignar equipo, pedir pruebas.
 */
import { computed, ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    Alert, Button, Card, Dropdown, InputNumber, Menu, MenuItem, Modal, Space,
    Tabs, TabPane, Tag, Textarea, Tooltip,
} from 'ant-design-vue';
import {
    DeleteOutlined, DownloadOutlined, EditOutlined, ExperimentOutlined,
    FileTextOutlined, FilePdfOutlined, HistoryOutlined, InboxOutlined,
    LockOutlined, PlusOutlined, SolutionOutlined, ThunderboltFilled,
} from '@ant-design/icons-vue';

import AppLayout from '@/Layouts/AppLayout.vue';
import SectionHeader from '@/Components/Common/SectionHeader.vue';
import ResponsiveTable from '@/Components/Common/ResponsiveTable.vue';
import ReceptionStatusTag from '@/Components/Receptions/ReceptionStatusTag.vue';
import ReceptionHeader from '@/Components/Receptions/ReceptionHeader.vue';
import ConfirmSamplesCard from '@/Components/Receptions/ConfirmSamplesCard.vue';
import SampleEquipmentSelect from '@/Components/Receptions/SampleEquipmentSelect.vue';
import SampleProgress from '@/Components/Receptions/SampleProgress.vue';
import AssignTestsModal from '@/Components/Receptions/AssignTestsModal.vue';
import ReportFormModal from '@/Components/Receptions/ReportFormModal.vue';
import ReportAnalysisModal from '@/Components/Receptions/ReportAnalysisModal.vue';
import CollapsibleTags from '@/Components/Common/CollapsibleTags.vue';
import EntityShowActions from '@/Components/Common/EntityShowActions.vue';
import RecordHistory from '@/Components/Common/RecordHistory.vue';
import EntityShowTabs from '@/Components/Common/EntityShowTabs.vue';
import { useAuth } from '@/Composables/useAuth';
import { useI18n } from '@/Plugins/i18n';
import { plainDate, testStatusColor } from './config/format';

defineOptions({ layout: AppLayout });

const props = defineProps({
    reception:  { type: Object, required: true },
    samples:    { type: Array,  default: () => [] },
    /** Los informes de las muestras de esta entrega, emitidos o en borrador. */
    reports:    { type: Array,  default: () => [] },
    // Indexado por sample_id: { pedidas, pendientes, en_proceso, validadas, informadas }.
    progress:   { type: [Object, Array], default: () => ({}) },
    // 'equipment' y/o 'tests'. Vacío = la recepción está completa.
    missing:    { type: Array,  default: () => [] },
    tests:      { type: Array,  default: () => [] },
    equipment:  { type: Array,  default: () => [] },
    oilTypes:   { type: Array,  default: () => [] },
    nextNumber: { type: [String, null], default: null },
    // Si la cantidad de muestras todavía se puede corregir: los números de
    // esta entrega son los últimos emitidos del año.
    canAdjust:  { type: Boolean, default: false },
    // El historial del registro, igual que en el resto de las fichas.
    activity:    { type: Array,  default: () => [] },
    recordAudit: { type: Object, default: null },
    /** { is_locked, can_lock, can_unlock, lock_scope } — el candado del registro. */
    lock:        { type: Object, default: null },
});

const { t } = useI18n();
const { can, hasRole, canSeeAudit } = useAuth();
const page = usePage();

const isDraft = computed(() => props.reception.status === 'draft');
const canEdit   = computed(() => can('receptions.edit'));
const canDelete = computed(() => can('receptions.delete'));

// ── Corregir la cantidad después de confirmar («puse 32 y eran 20») ──────
// Solo se ofrece mientras el servidor diga que se puede: los números de esta
// entrega son la cola de la numeración del año. Después, la ventana se cierra
// y quedan los caminos por-muestra.
const adjustOpen = ref(false);
const adjustCount = ref(props.samples.length);
const adjustBusy = ref(false);
const adjustError = ref(null);

const openAdjust = () => {
    adjustCount.value = props.samples.length;
    adjustError.value = null;
    adjustOpen.value = true;
};

const submitAdjust = () => {
    if (!adjustCount.value || adjustCount.value < 1) return;

    router.post(route('lab_management.receptions.adjust', props.reception.slug), {
        samples: adjustCount.value,
    }, {
        preserveScroll: true,
        onStart:  () => { adjustBusy.value = true; adjustError.value = null; },
        onFinish: () => { adjustBusy.value = false; },
        onSuccess: () => { adjustOpen.value = false; },
        onError: (errors) => { adjustError.value = errors.samples ?? Object.values(errors)[0] ?? null; },
    });
};

/**
 * Dar de baja UNA MUESTRA: solo admin y super, y no alcanza el permiso del
 * módulo.
 *
 * La baja se lleva los resultados y QUEMA el correlativo —ese número no se
 * reasigna nunca—, así que no es una corrección de carga: es una decisión sobre
 * el registro del laboratorio. `receptions.delete` lo tiene quien recibe las
 * muestras, que es justamente quien no debería poder borrarlas. La ruta está
 * gateada igual del lado del servidor (`role:super|admin`).
 */
const canDeleteSample = computed(() => hasRole('super', 'admin'));

/**
 * Quién abre el candado de un informe emitido.
 *
 * Emitir alcanza con `receptions.edit` —es el trabajo del día—; DESbloquear pide
 * admin o super, porque es admitir que salió un papel con un error y decidir
 * corregirlo. En el sistema anterior las dos acciones estaban bajo el mismo
 * permiso, así que cualquiera que pudiera cargar un informe podía desbloquear uno
 * ya entregado. El controlador lo verifica igual del lado del servidor.
 */
const canUnlock = computed(() => hasRole('super', 'admin'));

const title = computed(
    () => props.reception.code || `${t('receptions.singular')} #${props.reception.id}`,
);

/** Lo que rebotó del servidor (equipo de otro cliente, prueba inexistente…). */
const serverErrors = computed(() => Object.values(page.props.errors ?? {}).filter(Boolean));

const statsOf = (sample) => props.progress?.[sample.id] ?? null;

/** Lo que se le pide a la muestra. Lo dado de baja no se lista como pedido. */
const requestedOf = (sample) => (sample.tests ?? []).filter((test) => test.status !== 'cancelled');

/**
 * Las pruebas pedidas como etiquetas para la celda desplegable.
 *
 * El estado va en el color Y en el tooltip: un verde y un ámbar lado a lado no
 * dicen "validada" y "en proceso" a nadie que no sepa la convención de
 * memoria, y esta pantalla la mira quien recibe las muestras, no quien la
 * programó.
 */
const testTagsOf = (sample) => requestedOf(sample).map((test) => ({
    key:   test.id,
    label: test.definition?.name ?? test.definition?.code ?? '—',
    color: testStatusColor(test.status),
    title: t(`receptions.test_status_${test.status}`),
}));

// ── Asignación de pruebas ────────────────────────────────────────────────
const modalOpen = ref(false);
// La muestra a la que se le piden las pruebas; en `null` se piden a todas.
const modalSample = ref(null);

const openForSample = (sample) => {
    modalSample.value = sample;
    modalOpen.value = true;
};

const openForAll = () => {
    modalSample.value = null;
    modalOpen.value = true;
};

/** ¿Tiene al menos un ensayo firmado? Es la condición para poder informar. */
const hasValidated = (sample) => (sample.tests ?? [])
    .some((t) => ['validated', 'reported'].includes(t.status));

// ── Tabla de muestras ────────────────────────────────────────────────────
const columns = computed(() => [
    {
        title: t('receptions.sample_code'),
        dataIndex: 'code',
        key: 'code',
        width: 130,
    },
    {
        title: t('receptions.equipment'),
        dataIndex: 'equipment_id',
        key: 'equipment',
        width: 260,
    },
    // SIN ancho a propósito: cuando la pantalla es más ancha que la suma de
    // columnas, Ant reparte el sobrante entre TODAS proporcionalmente y el
    // select de equipo quedaba flotando en una columna enorme. Con una sola
    // columna sin ancho, el sobrante se lo queda esta —que es la que crece
    // con los datos— y las demás quedan del tamaño de su contenido.
    {
        title: t('receptions.requested_tests'),
        dataIndex: 'tests',
        key: 'tests',
    },
    {
        title: t('receptions.progress'),
        key: 'progress',
        width: 190,
    },
    {
        title: t('global.actions'),
        key: 'actions',
        width: 220,
        align: 'right',
    },
]);

/**
 * Qué pestaña está abierta.
 *
 * Vive como estado y no como el `defaultActiveKey` de Ant porque hay UNA acción
 * que tiene que cambiarla: al guardar un informe la ficha salta a "Informes".
 * Antes el modal se cerraba y la pantalla volvía a "Muestras" —la pestaña donde
 * uno estaba parado—, así que el informe recién creado no se veía y parecía que
 * no se había guardado.
 */
const tabActiva = ref('samples');

// Qué pestaña PRINCIPAL (Detalles/Historial) está abierta: el bloque de
// trabajo (Muestras/Informes) es parte de "Detalles" y con "Historial"
// abierto no tiene que verse — quedaba colgando debajo del historial.
const tabPrincipal = ref('general');

// ── Informes ─────────────────────────────────────────────────────────────
const reportOpen   = ref(false);
const reportSample = ref(null);
const reportRecord = ref(null);

const nuevoInforme = (sample) => {
    reportSample.value = sample;
    reportRecord.value = null;
    reportOpen.value = true;
};

const editarInforme = (report) => {
    reportSample.value = null;
    reportRecord.value = report;
    reportOpen.value = true;
};

/**
 * Emitir es irreversible: el papel sale con ese número y su contenido queda
 * congelado. Se pregunta una vez, diciendo exactamente eso, en vez de dejar que
 * se descubra después.
 */
const emitir = (report) => {
    Modal.confirm({
        title: t('sample_reports.issue'),
        content: t('sample_reports.issue_confirm', { code: report.code }),
        okText: t('sample_reports.issue'),
        cancelText: t('global.cancel'),
        onOk: () => router.post(
            route('lab_management.sample_reports.issue', report.slug),
            {},
            { preserveScroll: true },
        ),
    });
};

// ── Baja de un informe BORRADOR ──────────────────────────────────────────
//
// Solo el borrador: el emitido no se borra ni acá ni en el servidor (el
// cliente tiene un papel que cita ese número). El motivo es obligatorio,
// como en toda baja del sistema.
const delReportOpen   = ref(false);
const delReportRecord = ref(null);
const delReportReason = ref('');
const delReportError  = ref('');

const abrirBajaInforme = (report) => {
    delReportRecord.value = report;
    delReportReason.value = '';
    delReportError.value = '';
    delReportOpen.value = true;
};

const confirmarBajaInforme = () => {
    if (delReportReason.value.trim().length < 3) {
        delReportError.value = t('sample_reports.delete_reason_required');

        return;
    }

    router.delete(
        route('lab_management.sample_reports.destroy', delReportRecord.value.slug),
        {
            data: { deleted_description: delReportReason.value.trim() },
            preserveScroll: true,
            onSuccess: () => { delReportOpen.value = false; },
        },
    );
};

// ── Resultados y diagnóstico del informe ─────────────────────────────────
const analysisOpen   = ref(false);
const analysisReport = ref(null);

const verAnalisis = (report) => {
    analysisReport.value = report;
    analysisOpen.value = true;
};

// ── Baja de una muestra ──────────────────────────────────────────────────
//
// Una muestra con informe EMITIDO no se ofrece para borrar, y el motivo se
// dice: el cliente tiene un papel que cita ese número y el portal de
// verificación tiene que seguir encontrándolo. Con resultados cargados sí se
// puede, pero avisando ANTES lo que se lleva puesto.
const bajaOpen   = ref(false);
const bajaSample = ref(null);
const bajaReason = ref('');
const bajaError  = ref('');

const puedeBorrar = (sample) => !sample.issued_reports_count;

const abrirBaja = (sample) => {
    bajaSample.value = sample;
    bajaReason.value = '';
    bajaError.value = '';
    bajaOpen.value = true;
};

const confirmarBaja = () => {
    if (bajaReason.value.trim().length < 3) {
        bajaError.value = t('receptions.delete_sample_reason');

        return;
    }

    router.delete(
        route('lab_management.receptions.samples.destroy', [props.reception.slug, bajaSample.value.id]),
        {
            data: { deleted_description: bajaReason.value.trim() },
            preserveScroll: true,
            onSuccess: () => { bajaOpen.value = false; },
        },
    );
};

const reportsOf = (sample) => props.reports.filter((r) => r.sample_id === sample.id);

/**
 * Las columnas de la pestaña Informes.
 *
 * Son las del "Listado de Nº de Reportes" del sistema anterior. Antes eran seis
 * y faltaban justamente las que se usan para ENCONTRAR un informe: el cliente
 * llama citando el transformador ("el de la serie TR-99887") y no el número de
 * informe, así que sin la serie, el tipo de equipo y la razón del análisis había
 * que abrir muestra por muestra.
 */
const reportColumns = computed(() => [
    // `dataIndex` en las columnas de texto puro: sin él Ant no sabe de qué
    // propiedad de la fila sacar la celda, y las cuatro columnas nuevas salían
    // VACÍAS —encabezado dibujado y celda en blanco, que se lee como un dato que
    // falta y no como una columna mal cableada—.
    { title: t('sample_reports.col_sample_code'), key: 'sample', dataIndex: 'sample_code', width: 130 },
    { title: t('sample_reports.status'), key: 'status', dataIndex: 'status', width: 110 },
    { title: t('sample_reports.col_code'), key: 'code', dataIndex: 'code', width: 180 },
    { title: t('sample_reports.col_equipment_serial'), key: 'equipment_serial', dataIndex: 'equipment_serial', width: 170 },
    { title: t('sample_reports.col_equipment_type'), key: 'equipment_type', dataIndex: 'equipment_type', width: 150 },
    { title: t('sample_reports.col_oil_type'), key: 'oil_type', dataIndex: 'oil_type', width: 140 },
    { title: t('sample_reports.col_received_at'), key: 'received_at', dataIndex: 'received_at', width: 120 },
    { title: t('sample_reports.col_issued_at'), key: 'issued_at', dataIndex: 'issued_at', width: 120 },
    { title: t('sample_reports.col_delivered_at'), key: 'delivered_at', dataIndex: 'delivered_at', width: 120 },
    { title: t('sample_reports.col_sampling_reason'), key: 'sampling_reason', dataIndex: 'sampling_reason', width: 170 },
    { title: t('sample_reports.kind'), key: 'kind', dataIndex: 'kind', width: 110 },
    { title: t('sample_reports.tests_count'), key: 'tests_count', dataIndex: 'tests_count', width: 110, align: 'right' },
    // Ancho para las dos plantillas de exportación + editar/emitir/eliminar.
    { title: t('global.actions'), key: 'actions', width: 300, align: 'right' },
]);

// ── El candado del informe emitido ───────────────────────────────────────
/**
 * Desbloquear un informe emitido lo devuelve a borrador para corregirlo.
 *
 * El sistema anterior tenía este botón y no avisaba de nada. Acá se dice lo que
 * NO devuelve —el número— y se pide el motivo, que queda en la auditoría: un
 * informe que salió y volvió es lo primero que una auditoría pregunta.
 */
const unlockOpen   = ref(false);
const unlockRecord = ref(null);
const unlockReason = ref('');
const unlockError  = ref('');

const abrirDesbloqueo = (report) => {
    unlockRecord.value = report;
    unlockReason.value = '';
    unlockError.value = '';
    unlockOpen.value = true;
};

const confirmarDesbloqueo = () => {
    if (unlockReason.value.trim().length < 5) {
        unlockError.value = t('sample_reports.unissue_reason_required');
        return;
    }

    router.post(
        route('lab_management.sample_reports.unissue', unlockRecord.value.slug),
        { reason: unlockReason.value.trim() },
        {
            preserveScroll: true,
            onSuccess: () => { unlockOpen.value = false; },
        },
    );
};
</script>

<template>
    <Head :title="`${$t('receptions.show_title')} — ${title}`" />

    <div class="show-page sap-show">
        <SectionHeader
            :back-href="route('lab_management.receptions.index')"
            :title="title"
        >
            <template #icon><InboxOutlined /></template>
            <template #subtitle>
                <Space :size="8" wrap>
                    <ReceptionStatusTag :status="reception.status" />
                    <Tag v-if="reception.is_urgent" color="red" :bordered="false">
                        <ThunderboltFilled /> {{ $t('receptions.is_urgent') }}
                    </Tag>
                    <span v-if="reception.customer" class="rc-sub">{{ reception.customer.name }}</span>
                    <span class="rc-sub">{{ plainDate(reception.received_at) }}</span>
                </Space>
            </template>
            <template #actions>
                <!-- Descargar la entrega en Excel: sus muestras con el equipo,
                     las pruebas pedidas y el avance. El sistema anterior lo
                     tenía y es lo que el laboratorio manda por correo cuando el
                     cliente pregunta "¿en qué van mis muestras?". -->
                <Tooltip :title="$t('receptions.download_hint')">
                    <Button
                        :href="route('lab_management.receptions.export', reception.slug)"
                        download
                    >
                        <template #icon><DownloadOutlined /></template>
                        <span class="rc-hide-sm">{{ $t('global.download') }}</span>
                    </Button>
                </Tooltip>
                <!-- Editar / Eliminar / Bloquear: el estándar de los módulos.
                     Esta ficha solo tenía "Editar", así que la baja había que
                     buscarla en el listado y el candado —que el modelo ya
                     soportaba— no se podía poner desde ninguna parte. -->
                <EntityShowActions
                    module="receptions"
                    route-prefix="lab_management"
                    :slug="reception.slug"
                    :id="reception.id"
                    :can-edit="canEdit"
                    :can-delete="canDelete"
                    :can-see-audit="canSeeAudit"
                    :lock="lock"
                />
            </template>
        </SectionHeader>

        <Alert
            v-for="(error, index) in serverErrors"
            :key="index"
            type="error"
            show-icon
            class="rc-alert"
            :message="error"
        />

        <!-- Lo que falta para poder trabajar la entrega. Se dice arriba y con
             todas las letras: una muestra sin equipo no se puede informar ni
             graficar, y una sin pruebas pedidas no llega nunca a la bancada. -->
        <template v-if="!isDraft">
            <Alert
                v-for="item in missing"
                :key="item"
                type="warning"
                show-icon
                class="rc-alert"
                :message="$t(`receptions.missing_${item}`)"
            />
            <Alert
                v-if="missing.length === 0 && samples.length > 0"
                type="success"
                show-icon
                class="rc-alert"
                :message="$t('receptions.nothing_missing')"
            />
        </template>

        <!-- Muestras · Informes, como en el sistema anterior: lo que ENTRÓ y lo
             que SALIÓ. Son dos trabajos distintos sobre la misma entrega
             —cargar muestras y emitir informes— y mezclarlos en una sola tabla
             obliga a mirar veinte filas para encontrar los tres papeles que se
             entregaron. La tercera, Historial, es la misma de todas las fichas.

             Las pestañas se dibujan también en BORRADOR: antes el bloque entero
             colgaba de un `v-else` y la entrega recién registrada —justo cuando
             uno quiere ver quién la cargó— era la única ficha del sistema sin
             historial. -->
        <!-- ── ARRIBA: el registro. ABAJO: el trabajo. ─────────────────────
             La ficha quedaba con CUATRO pestañas al mismo nivel —Muestras,
             Informes, Detalles, Historial— y eso mezcla dos cosas distintas: dos
             de ellas son el REGISTRO de la entrega (sus datos y su auditoría,
             igual que en todos los módulos del sistema) y las otras dos son el
             TRABAJO que cuelga de ella.

             Ahora son dos bloques. Arriba `EntityShowTabs`, el mismo componente
             que usan Laboratorios, Instrumentos y el resto: Detalles ·
             Historial. Abajo, las dos pestañas de trabajo. Así la ficha se lee
             como cualquier otra del sistema y no hay que aprenderla aparte. -->
        <EntityShowTabs
            :show-history="canSeeAudit"
            :history-count="activity.length"
            @change="tabPrincipal = $event"
        >
            <template #general>
                <ReceptionHeader :reception="reception" />
            </template>
            <template #history>
                <RecordHistory
                    :record-audit="recordAudit"
                    :activity="activity"
                    :can-see-activity="canSeeAudit"
                />
            </template>
        </EntityShowTabs>

        <!-- Lo que CUELGA de la entrega: lo que entró y lo que salió. Son dos
             trabajos distintos sobre la misma entrega —cargar muestras y emitir
             informes— y mezclarlos en una tabla obliga a mirar veinte filas para
             encontrar los tres papeles que se entregaron. -->
        <Tabs
            v-show="tabPrincipal === 'general'"
            v-model:activeKey="tabActiva"
            class="rc-tabs rc-tabs--work"
        >
        <TabPane key="samples">
            <template #tab>
                <span><ExperimentOutlined /> {{ $t('receptions.section_samples') }} ({{ samples.length }})</span>
            </template>

            <!-- Borrador: todavía no hay muestras porque todavía no hay números. -->
            <ConfirmSamplesCard
                v-if="isDraft"
                :reception="reception"
                :next-number="nextNumber"
                :disabled="!canEdit"
            />
            <template v-else>

            <Card :body-style="{ padding: 0 }" class="rc-samples grid-card">
            <div class="rc-samples__bar">
                <h2 class="rc-samples__title">
                    <ExperimentOutlined /> {{ $t('receptions.section_samples') }}
                    <span class="rc-samples__count">{{ samples.length }}</span>
                </h2>

                <Space>
                    <!-- Corregir la cantidad: solo mientras los números de esta
                         entrega sean los últimos emitidos del año. -->
                    <Tooltip :title="$t('receptions.adjust_help')">
                        <Button
                            v-if="canEdit && canAdjust"
                            size="small"
                            @click="openAdjust"
                        >
                            {{ $t('receptions.adjust') }}
                        </Button>
                    </Tooltip>

                    <Tooltip :title="$t('receptions.assign_to_all_hint')">
                        <Button
                            v-if="canEdit && samples.length > 0"
                            type="primary"
                            size="small"
                            @click="openForAll"
                        >
                            {{ $t('receptions.assign_to_all') }}
                        </Button>
                    </Tooltip>
                </Space>
            </div>

            <!-- `view="table"` a propósito: en móvil la tabla scrollea en
                 horizontal en vez de volverse tarjetas. El selector de equipo y
                 el botón de pruebas necesitan su fila. -->
            <ResponsiveTable
                :columns="columns"
                :data-source="samples"
                :pagination="false"
                :scroll="{ x: 'max-content' }"
                view="table"
                row-key="id"
            >
                <template #empty>
                    <div class="rc-empty">{{ $t('receptions.no_samples_yet') }}</div>
                </template>

                <template #bodyCell="{ column, record }">
                    <template v-if="column.key === 'code'">
                        <span class="rc-code">{{ record.code }}</span>
                    </template>

                    <template v-else-if="column.key === 'equipment'">
                        <SampleEquipmentSelect
                            :reception="reception"
                            :sample="record"
                            :equipment="equipment"
                            :disabled="!canEdit"
                        />
                    </template>

                    <!-- Las pruebas pedidas se despliegan. Con las cuatro del
                         pedido normal se muestran solas; una campaña que pide
                         veinte o cien convertía la celda en una pared de
                         etiquetas que empujaba la tabla a lo alto. El color de
                         cada etiqueta sigue siendo su estado, y el tooltip lo
                         dice con palabras: el color solo no se entiende. -->
                    <template v-else-if="column.key === 'tests'">
                        <CollapsibleTags
                            :items="testTagsOf(record)"
                            :limit="4"
                            summary-key="receptions.tests_count"
                            :empty-text="$t('receptions.no_tests')"
                        />
                    </template>

                    <template v-else-if="column.key === 'progress'">
                        <SampleProgress :stats="statsOf(record)" />
                    </template>

                    <template v-else-if="column.key === 'actions'">
                        <Space :size="6">
                            <Button
                                v-if="canEdit"
                                size="small"
                                @click="openForSample(record)"
                            >
                                {{ $t('receptions.assign_tests') }}
                            </Button>
                            <!-- ACÁ HABÍA UN BOTÓN "VISTA PREVIA" y se quitó.
                                 Abría el PDF de la muestra en vivo, sin
                                 correlativo, sin código de verificación y sin
                                 firmas — pero con la misma pinta que el informe
                                 de verdad, y a un clic del botón que lo crea.
                                 Un papel con aspecto de definitivo, en la fila
                                 de al lado del definitivo, es exactamente lo
                                 que termina saliendo del laboratorio por
                                 equivocación. Para revisar antes de emitir está
                                 la pantalla del análisis, que además es donde
                                 hay que confirmar. -->
                            <Tooltip v-if="canEdit && hasValidated(record)" :title="$t('sample_reports.new')">
                                <Button size="small" type="primary" @click="nuevoInforme(record)">
                                    <PlusOutlined /> {{ $t('sample_reports.singular') }}
                                </Button>
                            </Tooltip>
                            <!-- Con un informe emitido no se ofrece el botón, y
                                 el tooltip dice por qué: el papel ya está en
                                 manos del cliente. -->
                            <Tooltip
                                v-if="canDeleteSample"
                                :title="puedeBorrar(record)
                                    ? $t('receptions.delete_sample')
                                    : $t('receptions.delete_blocked.issued_report', { code: record.code })"
                            >
                                <Button
                                    size="small"
                                    danger
                                    :disabled="!puedeBorrar(record)"
                                    @click="abrirBaja(record)"
                                >
                                    <DeleteOutlined />
                                </Button>
                            </Tooltip>
                        </Space>
                    </template>
                </template>
            </ResponsiveTable>
            </Card>
            </template>
        </TabPane>

        <TabPane key="reports">
            <template #tab>
                <span><FileTextOutlined /> {{ $t('sample_reports.tab') }} ({{ reports.length }})</span>
            </template>

            <Card :body-style="{ padding: 0 }" class="rc-samples grid-card">
                <ResponsiveTable
                    :columns="reportColumns"
                    :data-source="reports"
                    :pagination="false"
                    :scroll="{ x: 'max-content' }"
                    view="table"
                    row-key="id"
                >
                    <template #empty>
                        <div class="rc-empty">
                            {{ $t('sample_reports.empty') }}
                            <div class="rc-empty__hint">{{ $t('sample_reports.empty_hint') }}</div>
                        </div>
                    </template>

                    <template #bodyCell="{ column, record }">
                        <template v-if="column.key === 'code'">
                            <span class="rc-code">{{ record.code }}</span>
                        </template>

                        <template v-else-if="column.key === 'sample'">
                            <span class="rc-code">{{ record.sample_code ?? '—' }}</span>
                        </template>

                        <template v-else-if="column.key === 'kind'">
                            <Tag :bordered="false" :color="record.kind === 'primary' ? 'blue' : 'default'">
                                {{ $t(`sample_reports.kind_${record.kind}`) }}
                            </Tag>
                        </template>

                        <template v-else-if="column.key === 'status'">
                            <Tag :bordered="false" :color="record.status === 'issued' ? 'green' : 'orange'">
                                {{ $t(`sample_reports.status_${record.status}`) }}
                            </Tag>
                        </template>

                        <!-- Las tres fechas del sistema anterior: cuándo entró,
                             cuándo salió el informe y cuándo se entregó. -->
                        <template
                            v-else-if="['received_at', 'issued_at', 'delivered_at'].includes(column.key)"
                        >
                            {{ plainDate(record[column.key]) || '—' }}
                        </template>

                        <template v-else-if="column.key === 'tests_count'">
                            {{ record.tests_count }}
                        </template>

                        <template v-else-if="column.key === 'actions'">
                            <Space :size="6">
                                <!-- Un informe emitido no se edita: el papel ya
                                     salió con ese contenido y ese número. Lo que
                                     corresponde es un adicional, y por eso el
                                     botón desaparece en vez de fallar al
                                     guardar. -->
                                <Button
                                    v-if="canEdit && record.status === 'draft'"
                                    size="small"
                                    @click="editarInforme(record)"
                                >
                                    <EditOutlined /> {{ $t('global.edit') }}
                                </Button>
                                <!-- Los valores detectados y el párrafo que va
                                     impreso. Se abre también con el informe ya
                                     emitido, de solo lectura: hay que poder
                                     mirar qué se firmó. -->
                                <Tooltip :title="$t('sample_reports.analysis_tab')">
                                    <Button size="small" @click="verAnalisis(record)">
                                        <SolutionOutlined />
                                    </Button>
                                </Tooltip>
                                <!-- EMITIR APARECE RECIÉN CON EL ANÁLISIS
                                     CONFIRMADO.

                                     El motor compone los párrafos del análisis
                                     al abrirse esa pantalla. Si nadie la abría,
                                     el informe salía con los títulos de familia
                                     —FISICOQUIMICO, CROMATOGRAFICO, AZUFRE
                                     CORROSIVO…— y ni una línea debajo de
                                     ninguno: un papel firmado que no dice nada
                                     en la única sección donde el laboratorio
                                     opina. Mientras falte, en lugar del botón va
                                     el aviso de qué falta, para que el que
                                     emite sepa adónde ir. -->
                                <template v-if="canEdit && record.status === 'draft'">
                                    <Button
                                        v-if="record.analysis_confirmed"
                                        size="small"
                                        type="primary"
                                        @click="emitir(record)"
                                    >
                                        {{ $t('sample_reports.issue') }}
                                    </Button>
                                    <Tooltip v-else :title="$t('sample_reports.issue_needs_analysis')">
                                        <Tag :bordered="false" color="orange" class="rc-pending">
                                            {{ $t('sample_reports.analysis_pending') }}
                                        </Tag>
                                    </Tooltip>
                                </template>
                                <!-- UN botón de descarga con las dos plantillas
                                     adentro. Antes eran dos botones con su
                                     rótulo, y en una fila que ya tiene cinco
                                     acciones eso es media pantalla gastada en
                                     decir dos veces "PDF". La elección de
                                     plantilla no es una acción distinta: es cómo
                                     se quiere el mismo papel. -->
                                <!-- Y DESCARGAR, RECIÉN CON EL INFORME EMITIDO.
                                     Un borrador no es un informe: no tiene
                                     número de verificación, su contenido todavía
                                     puede cambiar y el análisis puede estar sin
                                     escribir. Bajarlo en PDF es fabricar un
                                     papel con aspecto de definitivo que después
                                     alguien manda por correo. Para revisar antes
                                     de emitir está la pantalla del análisis. -->
                                <Dropdown v-if="record.status === 'issued'" :trigger="['click']">
                                    <Tooltip :title="$t('sample_reports.download')">
                                        <Button size="small">
                                            <DownloadOutlined />
                                        </Button>
                                    </Tooltip>
                                    <template #overlay>
                                        <Menu>
                                            <MenuItem key="modern">
                                                <a
                                                    :href="route('lab_management.sample_reports.pdf', record.slug)"
                                                    target="_blank"
                                                >
                                                    <FilePdfOutlined /> {{ $t('sample_reports.template_modern') }}
                                                </a>
                                            </MenuItem>
                                            <MenuItem key="legacy">
                                                <a
                                                    :href="route('lab_management.sample_reports.pdf_legacy', record.slug)"
                                                    target="_blank"
                                                >
                                                    <FilePdfOutlined /> {{ $t('sample_reports.template_legacy') }}
                                                </a>
                                            </MenuItem>
                                        </Menu>
                                    </template>
                                </Dropdown>
                                <!-- Solo el BORRADOR se elimina: el emitido es
                                     un papel que el cliente ya cita. -->
                                <Tooltip
                                    v-if="canDelete && record.status === 'draft'"
                                    :title="$t('global.delete')"
                                >
                                    <Button size="small" danger @click="abrirBajaInforme(record)">
                                        <DeleteOutlined />
                                    </Button>
                                </Tooltip>
                                <!-- EMITIDO = CANDADO, Y EL CANDADO SE ABRE.
                                     El informe emitido no se edita ni se borra:
                                     su contenido quedó congelado y el cliente
                                     tiene un papel que cita ese número. El
                                     candado lo dice con un icono en el lugar
                                     donde estaban Editar y Eliminar, en vez de
                                     dejar el hueco y que parezca que la fila
                                     perdió sus acciones.

                                     Y se puede DESBLOQUEAR, como en el sistema
                                     anterior: se emite un informe con un dato mal
                                     cargado y hay que corregirlo antes de que
                                     salga del laboratorio. Solo admin o super, y
                                     con motivo. Para el resto el candado sigue
                                     siendo solo un cartel. -->
                                <Tooltip
                                    v-if="record.status !== 'draft'"
                                    :title="canUnlock
                                        ? $t('sample_reports.unlock')
                                        : $t('sample_reports.issued_is_final')"
                                >
                                    <Button
                                        v-if="canUnlock"
                                        size="small"
                                        class="rc-unlock"
                                        @click="abrirDesbloqueo(record)"
                                    >
                                        <LockOutlined />
                                    </Button>
                                    <Tag v-else color="gold" :bordered="false" class="rc-lock">
                                        <LockOutlined />
                                    </Tag>
                                </Tooltip>
                            </Space>
                        </template>

                        <!-- Respaldo. Sin esta rama, una columna que no tenga su
                             `v-if` arriba se dibuja VACÍA aunque la fila traiga
                             el dato, y una celda en blanco se lee como un dato
                             que falta. Es lo que pasó al agregar las cuatro
                             columnas del equipo. -->
                        <template v-else>{{ record[column.dataIndex] ?? '—' }}</template>
                    </template>
                </ResponsiveTable>
            </Card>
        </TabPane>

        </Tabs>

        <!-- El «puse 32 y eran 20»: corrige la cantidad mientras los números
             sigan siendo la cola del año. El servidor re-verifica la condición
             dentro de la misma transacción. -->
        <Modal
            v-model:open="adjustOpen"
            :title="$t('receptions.adjust_title')"
            :confirm-loading="adjustBusy"
            :ok-text="$t('receptions.adjust')"
            :cancel-text="$t('global.cancel')"
            @ok="submitAdjust"
        >
            <p class="rc-adjust__help">{{ $t('receptions.adjust_help') }}</p>
            <InputNumber
                v-model:value="adjustCount"
                :min="1"
                :max="500"
                size="large"
                style="width: 160px"
            />
            <Alert
                v-if="adjustError"
                type="error"
                show-icon
                class="rc-adjust__error"
                :message="adjustError"
            />
        </Modal>

        <AssignTestsModal
            v-model:open="modalOpen"
            :reception="reception"
            :sample="modalSample"
            :tests="tests"
        />

        <ReportFormModal
            v-model:open="reportOpen"
            :sample="reportSample"
            :report="reportRecord"
            @saved="tabActiva = 'reports'"
        />

        <!-- `saved` recarga la ficha: de esa pantalla sale la confirmación del
             análisis, y de ella depende que la fila muestre el botón de emitir.
             Sin recargar, hay que salir y volver para verlo aparecer. -->
        <ReportAnalysisModal
            v-model:open="analysisOpen"
            :report="analysisReport"
            @saved="router.reload({ only: ['reports'] })"
        />

        <!-- Desbloquear un informe emitido. El aviso va PRIMERO: lo que importa
             no es que vuelva a editable, es que el número no cambia y que el
             papel que el cliente tiene en la mano cita ese número. -->
        <Modal
            v-model:open="unlockOpen"
            :title="$t('sample_reports.unlock_title', { code: unlockRecord?.code ?? '' })"
            :ok-text="$t('sample_reports.unlock')"
            :cancel-text="$t('global.cancel')"
            @ok="confirmarDesbloqueo"
        >
            <Alert type="warning" show-icon class="rc-alert">
                <template #message>{{ $t('sample_reports.unlock_warning') }}</template>
                <template #description>{{ $t('sample_reports.unlock_intro') }}</template>
            </Alert>

            <label class="rc-baja__label">{{ $t('sample_reports.unlock_reason') }}</label>
            <Textarea
                v-model:value="unlockReason"
                :rows="3"
                :maxlength="500"
                show-count
                :placeholder="$t('sample_reports.unlock_reason_help')"
            />
            <div v-if="unlockError" class="rc-baja__error">{{ unlockError }}</div>
        </Modal>

        <Modal
            v-model:open="bajaOpen"
            :title="$t('receptions.delete_sample')"
            :ok-text="$t('global.delete')"
            :cancel-text="$t('global.cancel')"
            :ok-button-props="{ danger: true }"
            @ok="confirmarBaja"
        >
            <p class="rc-baja__intro">
                {{ $t('receptions.delete_sample_confirm', { code: bajaSample?.code ?? '' }) }}
            </p>

            <!-- El aviso va ANTES, no después: si la muestra ya tiene
                 resultados, borrarla se los lleva puestos. -->
            <Alert
                v-if="bajaSample?.results_count > 0"
                type="warning"
                show-icon
                class="rc-baja__warn"
                :message="$t('receptions.delete_sample_has_work')"
            />

            <!-- Mientras la corrección de cantidad siga abierta, la baja
                 individual es la herramienta equivocada para un error de
                 cantidad: quema el número Y cierra la corrección. Se avisa
                 ANTES de que pase. -->
            <Alert
                v-if="canAdjust"
                type="info"
                show-icon
                class="rc-baja__warn"
                :message="$t('receptions.delete_vs_adjust')"
            />

            <label class="rc-baja__label">{{ $t('receptions.delete_sample_reason') }}</label>
            <Textarea v-model:value="bajaReason" :rows="3" :maxlength="1000" show-count />

            <p v-if="bajaError" class="rc-baja__error">{{ bajaError }}</p>
        </Modal>

        <!-- Baja de un informe BORRADOR, con su motivo. El emitido no se
             ofrece: el servidor también lo rechaza. -->
        <Modal
            v-model:open="delReportOpen"
            :title="$t('global.delete') + ' — ' + (delReportRecord?.code ?? '')"
            :ok-text="$t('global.delete')"
            :cancel-text="$t('global.cancel')"
            :ok-button-props="{ danger: true }"
            @ok="confirmarBajaInforme"
        >
            <p class="rc-baja__intro">{{ $t('sample_reports.delete_confirm') }}</p>

            <label class="rc-baja__label">{{ $t('global.delete_description') }}</label>
            <Textarea v-model:value="delReportReason" :rows="3" :maxlength="1000" show-count />

            <p v-if="delReportError" class="rc-baja__error">{{ delReportError }}</p>
        </Modal>
    </div>
</template>

<style scoped>
.rc-alert { margin-bottom: 12px; }
.rc-sub { color: var(--color-text-muted); font-size: 0.8125rem; }
/* Ocupa el lugar del botón Emitir mientras el análisis no esté confirmado: sin
   esto la fila pierde su acción principal y parece que le falta algo. */
.rc-pending { margin: 0; }

.rc-samples__bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
    padding: 14px 16px;
    border-bottom: 1px solid var(--color-border);
}
.rc-samples__title {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-size: 0.95rem;
    font-weight: 600;
    margin: 0;
    color: var(--color-text);
}
.rc-samples__count {
    font-size: 0.75rem;
    font-weight: 500;
    color: var(--color-text-muted);
    background: var(--color-surface-alt, #f0f2f5);
    border-radius: 999px;
    padding: 1px 8px;
}

.rc-code { font-weight: 600; font-variant-numeric: tabular-nums; }
.rc-muted { color: var(--color-text-muted); font-size: 0.8125rem; }
.rc-empty { padding: 40px 16px; text-align: center; color: var(--color-text-muted); }
.rc-empty__hint { margin-top: 4px; font-size: 0.8125rem; }

.rc-baja__intro { color: var(--color-text-muted); margin-bottom: 12px; }
.rc-baja__warn  { margin-bottom: 12px; }
.rc-baja__label { display: block; font-weight: 600; margin: 14px 0 6px; color: var(--color-text); }
/* El candado que se abre. Ámbar como el cartel que reemplaza, para que se lea
   igual "está emitido" y no como una acción cualquiera de la fila. */
.rc-unlock { color: #b45309; border-color: #f5c86b; }
.rc-baja__error { color: var(--color-danger-bright); margin-top: 8px; }

/* Las pestañas van sobre el fondo gris de la ficha, no dentro de una tarjeta:
   así la tarjeta de la tabla queda debajo, como en el resto de las pantallas. */
.rc-tabs :deep(.ant-tabs-nav) { margin-bottom: 12px; }
/* El bloque de trabajo se separa del registro: son dos grupos de pestañas y sin
   aire entre ellos se leen como un solo juego de seis. */
.rc-tabs--work { margin-top: 6px; }
.rc-adjust__help { font-size: 0.8125rem; color: var(--color-text-muted); margin: 0 0 12px; }
.rc-adjust__error { margin-top: 12px; }
</style>
