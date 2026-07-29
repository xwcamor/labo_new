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
import { Alert, Button, Card, Modal, Space, Tabs, TabPane, Tag, Textarea, Tooltip } from 'ant-design-vue';
import {
    DeleteOutlined, EditOutlined, ExperimentOutlined, FileTextOutlined,
    FilePdfOutlined, HistoryOutlined, InboxOutlined, PlusOutlined, SolutionOutlined,
    ThunderboltFilled,
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
import RecordHistory from '@/Components/Common/RecordHistory.vue';
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
    // El historial del registro, igual que en el resto de las fichas.
    activity:    { type: Array,  default: () => [] },
    recordAudit: { type: Object, default: null },
});

const { t } = useI18n();
const { can, canSeeAudit } = useAuth();
const page = usePage();

const isDraft = computed(() => props.reception.status === 'draft');
const canEdit = computed(() => can('receptions.edit'));

const title = computed(
    () => props.reception.code || `${t('receptions.singular')} #${props.reception.id}`,
);

/** Lo que rebotó del servidor (equipo de otro cliente, prueba inexistente…). */
const serverErrors = computed(() => Object.values(page.props.errors ?? {}).filter(Boolean));

const statsOf = (sample) => props.progress?.[sample.id] ?? null;

/** Lo que se le pide a la muestra. Lo dado de baja no se lista como pedido. */
const requestedOf = (sample) => (sample.tests ?? []).filter((test) => test.status !== 'cancelled');

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
    {
        title: t('receptions.requested_tests'),
        dataIndex: 'tests',
        key: 'tests',
        width: 320,
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

const reportColumns = computed(() => [
    { title: t('sample_reports.code'), key: 'code', width: 190 },
    { title: t('sample_reports.sample'), key: 'sample', width: 130 },
    { title: t('sample_reports.kind'), key: 'kind', width: 110 },
    { title: t('sample_reports.status'), key: 'status', width: 110 },
    { title: t('sample_reports.issued_at'), key: 'issued_at', width: 130 },
    { title: t('sample_reports.tests_count'), key: 'tests_count', width: 120, align: 'right' },
    { title: t('global.actions'), key: 'actions', width: 230, align: 'right' },
]);
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
                <Link v-if="canEdit" :href="route('lab_management.receptions.edit', reception.slug)">
                    <Button>
                        <EditOutlined /> {{ $t('global.edit') }}
                    </Button>
                </Link>
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

        <ReceptionHeader :reception="reception" />

        <!-- Muestras · Informes, como en el sistema anterior: lo que ENTRÓ y lo
             que SALIÓ. Son dos trabajos distintos sobre la misma entrega
             —cargar muestras y emitir informes— y mezclarlos en una sola tabla
             obliga a mirar veinte filas para encontrar los tres papeles que se
             entregaron. La tercera, Historial, es la misma de todas las fichas.

             Las pestañas se dibujan también en BORRADOR: antes el bloque entero
             colgaba de un `v-else` y la entrega recién registrada —justo cuando
             uno quiere ver quién la cargó— era la única ficha del sistema sin
             historial. -->
        <Tabs class="rc-tabs">
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

                    <template v-else-if="column.key === 'tests'">
                        <div v-if="requestedOf(record).length > 0" class="rc-tests">
                            <Tag
                                v-for="test in requestedOf(record)"
                                :key="test.id"
                                :color="testStatusColor(test.status)"
                                :bordered="false"
                            >
                                {{ test.definition?.name ?? test.definition?.code ?? '—' }}
                            </Tag>
                        </div>
                        <span v-else class="rc-muted">{{ $t('receptions.no_tests') }}</span>
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
                            <!-- El informe se ofrece solo cuando hay algo
                                 firmado que informar. Un botón que abre un
                                 informe vacío se lee como que el ensayo dio
                                 cero. -->
                            <Tooltip v-if="hasValidated(record)" :title="$t('receptions.report_help')">
                                <Button
                                    size="small"
                                    :href="route('lab_management.samples.report', record.slug)"
                                    target="_blank"
                                >
                                    <FilePdfOutlined /> {{ $t('sample_reports.preview') }}
                                </Button>
                            </Tooltip>
                            <Tooltip v-if="canEdit && hasValidated(record)" :title="$t('sample_reports.new')">
                                <Button size="small" type="primary" @click="nuevoInforme(record)">
                                    <PlusOutlined /> {{ $t('sample_reports.singular') }}
                                </Button>
                            </Tooltip>
                            <!-- Con un informe emitido no se ofrece el botón, y
                                 el tooltip dice por qué: el papel ya está en
                                 manos del cliente. -->
                            <Tooltip
                                v-if="canEdit"
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
                            {{ record.sample?.code ?? '—' }}
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

                        <template v-else-if="column.key === 'issued_at'">
                            {{ plainDate(record.issued_at) }}
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
                                <Button
                                    v-if="canEdit && record.status === 'draft'"
                                    size="small"
                                    type="primary"
                                    @click="emitir(record)"
                                >
                                    {{ $t('sample_reports.issue') }}
                                </Button>
                                <Tooltip :title="$t('sample_reports.download')">
                                    <Button
                                        size="small"
                                        :href="route('lab_management.sample_reports.pdf', record.slug)"
                                        target="_blank"
                                    >
                                        <FilePdfOutlined />
                                    </Button>
                                </Tooltip>
                            </Space>
                        </template>
                    </template>
                </ResponsiveTable>
            </Card>
        </TabPane>

        <TabPane key="history">
            <template #tab>
                <span><HistoryOutlined /> {{ $t('global.history') }}</span>
            </template>
            <RecordHistory :record-audit="recordAudit" :activity="activity" :can-see-activity="canSeeAudit" />
        </TabPane>
        </Tabs>

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
        />

        <ReportAnalysisModal
            v-model:open="analysisOpen"
            :report="analysisReport"
        />

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

            <label class="rc-baja__label">{{ $t('receptions.delete_sample_reason') }}</label>
            <Textarea v-model:value="bajaReason" :rows="3" :maxlength="1000" show-count />

            <p v-if="bajaError" class="rc-baja__error">{{ bajaError }}</p>
        </Modal>
    </div>
</template>

<style scoped>
.rc-alert { margin-bottom: 12px; }
.rc-sub { color: var(--color-text-muted); font-size: 0.8125rem; }

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
.rc-tests { display: flex; flex-wrap: wrap; gap: 4px; max-width: 320px; }
.rc-muted { color: var(--color-text-muted); font-size: 0.8125rem; }
.rc-empty { padding: 40px 16px; text-align: center; color: var(--color-text-muted); }
.rc-empty__hint { margin-top: 4px; font-size: 0.8125rem; }

.rc-baja__intro { color: var(--color-text-muted); margin-bottom: 12px; }
.rc-baja__warn  { margin-bottom: 12px; }
.rc-baja__label { display: block; font-weight: 600; margin-bottom: 6px; color: var(--color-text); }
.rc-baja__error { color: var(--color-danger-bright); margin-top: 8px; }

/* Las pestañas van sobre el fondo gris de la ficha, no dentro de una tarjeta:
   así la tarjeta de la tabla queda debajo, como en el resto de las pantallas. */
.rc-tabs :deep(.ant-tabs-nav) { margin-bottom: 12px; }
</style>
