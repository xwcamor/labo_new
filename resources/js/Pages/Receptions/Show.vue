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
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Alert, Button, Card, Space, Tag, Tooltip } from 'ant-design-vue';
import { EditOutlined, ExperimentOutlined, InboxOutlined, ThunderboltFilled } from '@ant-design/icons-vue';

import AppLayout from '@/Layouts/AppLayout.vue';
import SectionHeader from '@/Components/Common/SectionHeader.vue';
import ResponsiveTable from '@/Components/Common/ResponsiveTable.vue';
import ReceptionStatusTag from '@/Components/Receptions/ReceptionStatusTag.vue';
import ReceptionHeader from '@/Components/Receptions/ReceptionHeader.vue';
import ConfirmSamplesCard from '@/Components/Receptions/ConfirmSamplesCard.vue';
import SampleEquipmentSelect from '@/Components/Receptions/SampleEquipmentSelect.vue';
import SampleProgress from '@/Components/Receptions/SampleProgress.vue';
import AssignTestsModal from '@/Components/Receptions/AssignTestsModal.vue';
import { useAuth } from '@/Composables/useAuth';
import { useI18n } from '@/Plugins/i18n';
import { plainDate, testStatusColor } from './config/format';

defineOptions({ layout: AppLayout });

const props = defineProps({
    reception:  { type: Object, required: true },
    samples:    { type: Array,  default: () => [] },
    // Indexado por sample_id: { pedidas, pendientes, en_proceso, validadas, informadas }.
    progress:   { type: [Object, Array], default: () => ({}) },
    // 'equipment' y/o 'tests'. Vacío = la recepción está completa.
    missing:    { type: Array,  default: () => [] },
    tests:      { type: Array,  default: () => [] },
    equipment:  { type: Array,  default: () => [] },
    oilTypes:   { type: Array,  default: () => [] },
    nextNumber: { type: [String, null], default: null },
});

const { t } = useI18n();
const { can } = useAuth();
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
        width: 160,
        align: 'right',
    },
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

        <!-- Borrador: todavía no hay muestras porque todavía no hay números. -->
        <ConfirmSamplesCard
            v-if="isDraft"
            :reception="reception"
            :next-number="nextNumber"
            :disabled="!canEdit"
        />

        <Card v-else :body-style="{ padding: 0 }" class="rc-samples grid-card">
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
                        <Button
                            v-if="canEdit"
                            size="small"
                            @click="openForSample(record)"
                        >
                            {{ $t('receptions.assign_tests') }}
                        </Button>
                    </template>
                </template>
            </ResponsiveTable>
        </Card>

        <AssignTestsModal
            v-model:open="modalOpen"
            :reception="reception"
            :sample="modalSample"
            :tests="tests"
        />
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
</style>
