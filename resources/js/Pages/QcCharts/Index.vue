<script setup>
/**
 * Listado de cartas de control.
 *
 * En el sistema viejo esto eran dos submenús ("Límite de Tendencias" y
 * "Tendencias") que hablaban de lo mismo sin ninguna clave foránea entre ellos.
 * Aquí una carta es un registro: el listado la identifica por prueba + columna
 * controlada, y la ficha es su gráfico.
 */
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Button, Card, Select, SelectOption, Tag, Tooltip } from 'ant-design-vue';
import {
    PlusOutlined, EditOutlined, LineChartOutlined, FilterOutlined, ClearOutlined,
} from '@ant-design/icons-vue';

import AppLayout from '@/Layouts/AppLayout.vue';
import ResponsiveTable from '@/Components/Common/ResponsiveTable.vue';
import QcChartsPageHeader from '@/Components/QcCharts/QcChartsPageHeader.vue';
import { LIMIT_KEYS, fmtNumber, resolveLimits } from '@/Components/QcCharts/limits';

import { useAuth } from '@/Composables/useAuth';
import { useDateFormat } from '@/Composables/useDateFormat';
import { useI18n } from '@/Plugins/i18n';

defineOptions({ layout: AppLayout });

const props = defineProps({
    charts:  { type: Object, required: true },
    tests:   { type: Array,  default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

const { t } = useI18n();
const { can } = useAuth();
const { formatDate } = useDateFormat();

// ─── Filtros ─────────────────────────────────────────────────────────────
// El controlador solo acepta dos: prueba y estado. No hay builder avanzado
// porque no hay esquema de filtros del lado del servidor para esta pantalla.
const testFilter = ref(props.filters?.test_definition ?? undefined);
const activeFilter = ref(props.filters?.is_active ?? undefined);
const isFetching = ref(false);

const hasFilters = computed(() => !!testFilter.value || activeFilter.value !== undefined);

const reload = () => {
    const data = {};
    if (testFilter.value) data.test_definition = testFilter.value;
    if (activeFilter.value !== undefined) data.is_active = activeFilter.value;

    router.get(route('lab_management.qc_charts.index'), data, {
        preserveScroll: true,
        preserveState: true,
        only: ['charts', 'filters'],
        onStart: () => { isFetching.value = true; },
        onFinish: () => { isFetching.value = false; },
    });
};

const clearFilters = () => {
    testFilter.value = undefined;
    activeFilter.value = undefined;
    reload();
};

// ─── Columnas ────────────────────────────────────────────────────────────
// Dos cabeceras se componen con las siglas y con los dos extremos de la
// vigencia: una celda que resume cinco límites, o un intervalo, no tiene un
// rótulo propio en el archivo de idioma y no se inventa uno aquí.
const limitsTitle = computed(() => LIMIT_KEYS.map((k) => t(`qc_charts.limits_short.${k}`)).join(' · '));
const validityTitle = computed(() => `${t('qc_charts.effective_from')} · ${t('qc_charts.effective_to')}`);

const columns = computed(() => [
    { title: t('qc_charts.test_definition'), key: 'test', width: 220, mobile: { role: 'title' } },
    { title: t('qc_charts.test_field'), key: 'controlled', width: 220, mobile: { role: 'subtitle' } },
    { title: t('qc_charts.control_lot'), key: 'control_lot', dataIndex: 'control_lot', width: 150, mobile: { role: 'meta' } },
    { title: limitsTitle.value, key: 'limits', width: 340, mobile: { role: 'meta' } },
    { title: validityTitle.value, key: 'validity', width: 230, mobile: { role: 'meta' } },
    { title: t('qc_charts.points'), key: 'points_count', dataIndex: 'points_count', width: 130, align: 'right', mobile: { role: 'meta' } },
    { title: t('qc_charts.is_active'), key: 'is_active', width: 120, mobile: { role: 'status' } },
    { title: t('global.actions'), key: 'actions', width: 120, align: 'right', mobile: { role: 'actions' } },
]);

const tablePagination = computed(() => ({
    current:  props.charts.current_page,
    pageSize: props.charts.per_page,
    total:    props.charts.total,
    showSizeChanger: false,
}));

const onTableChange = (pag) => {
    const data = { page: pag.current };
    if (testFilter.value) data.test_definition = testFilter.value;
    if (activeFilter.value !== undefined) data.is_active = activeFilter.value;
    router.get(route('lab_management.qc_charts.index'), data, { preserveScroll: true, preserveState: true });
};

/**
 * Qué controla la carta. `test_field` es la columna de la hoja; `analyte`, el
 * parámetro. Puede tener una, la otra o las dos, así que se muestra lo que haya
 * en vez de asumir.
 */
const controlledLabel = (record) => (
    record.field?.label || record.analyte?.name || record.label || '—'
);

const controlledUnit = (record) => (
    record.field?.unit || record.analyte?.unit || ''
);

/** Vigencia como intervalo; un extremo vacío es un intervalo abierto. */
const validityLabel = (record) => {
    const from = record.effective_from ? formatDate(record.effective_from) : null;
    const to = record.effective_to ? formatDate(record.effective_to) : null;

    if (!from && !to) return '—';
    if (from && to) return `${t('global.from')} ${from} ${t('global.to')} ${to}`;
    if (from) return `${t('global.from')} ${from}`;
    return `${t('global.to')} ${to}`;
};
</script>

<template>
    <Head :title="$t('qc_charts.title')" />

    <div class="sap-index">
        <div class="mi-title">
            <QcChartsPageHeader :title="$t('qc_charts.title')" :subtitle="$t('qc_charts.intro')" />
        </div>

        <div class="qci-toolbar">
            <div class="qci-toolbar__filters">
                <FilterOutlined class="qci-toolbar__icon" />
                <Select
                    v-model:value="testFilter"
                    class="qci-select"
                    allow-clear
                    show-search
                    option-filter-prop="label"
                    :placeholder="$t('qc_charts.test_definition')"
                    @change="reload"
                >
                    <SelectOption v-for="test in tests" :key="test.slug" :value="test.slug" :label="test.name">
                        {{ test.name }}
                    </SelectOption>
                </Select>

                <Select
                    v-model:value="activeFilter"
                    class="qci-select qci-select--short"
                    allow-clear
                    :placeholder="$t('qc_charts.is_active')"
                    @change="reload"
                >
                    <SelectOption value="1">{{ $t('global.active') }}</SelectOption>
                    <SelectOption value="0">{{ $t('global.inactive') }}</SelectOption>
                </Select>

                <Button v-if="hasFilters" type="link" @click="clearFilters">
                    <ClearOutlined /> {{ $t('global.clear_filters') }}
                </Button>
            </div>

            <Tooltip v-if="can('qc_charts.create')" :title="$t('qc_charts.create')">
                <Link :href="route('lab_management.qc_charts.create')">
                    <Button type="primary"><PlusOutlined /> {{ $t('qc_charts.create') }}</Button>
                </Link>
            </Tooltip>
        </div>

        <Card :bodyStyle="{ padding: 0 }" class="grid-card">
            <ResponsiveTable
                :loading="isFetching"
                :columns="columns"
                :data-source="charts.data"
                :pagination="tablePagination"
                :scroll="{ x: 'max-content' }"
                row-key="id"
                @change="onTableChange"
            >
                <template #empty>
                    <p class="qci-empty">{{ $t('qc_charts.empty') }}</p>
                </template>

                <template #bodyCell="{ column, record }">
                    <template v-if="column.key === 'test'">
                        <Link :href="route('lab_management.qc_charts.show', record.slug)" class="qci-link">
                            {{ record.definition?.name ?? '—' }}
                        </Link>
                        <div v-if="record.definition?.code" class="qci-sub">{{ record.definition.code }}</div>
                    </template>

                    <template v-else-if="column.key === 'controlled'">
                        <span>{{ controlledLabel(record) }}</span>
                        <span v-if="controlledUnit(record)" class="qci-sub"> ({{ controlledUnit(record) }})</span>
                        <div v-if="record.analyte && record.field" class="qci-sub">{{ record.analyte.name }}</div>
                    </template>

                    <template v-else-if="column.key === 'control_lot'">
                        <span v-if="record.control_lot" class="qci-mono">{{ record.control_lot }}</span>
                        <span v-else class="qci-sub">—</span>
                    </template>

                    <!-- Los cinco límites resumidos. Si la carta los deriva, se
                         muestra el número derivado y no la columna cruda: son los
                         que van a regir. -->
                    <template v-else-if="column.key === 'limits'">
                        <div class="qci-limits">
                            <span v-for="key in LIMIT_KEYS" :key="key" class="qci-limit" :class="`qci-limit--${key}`">
                                <span class="qci-limit__tag">{{ $t(`qc_charts.limits_short.${key}`) }}</span>
                                <span class="qci-limit__val">{{ fmtNumber(resolveLimits(record)[key]) }}</span>
                            </span>
                        </div>
                        <div v-if="record.is_derived" class="qci-sub">{{ $t('qc_charts.is_derived') }}</div>
                    </template>

                    <template v-else-if="column.key === 'validity'">
                        {{ validityLabel(record) }}
                    </template>

                    <template v-else-if="column.key === 'points_count'">
                        <span class="qci-count">{{ record.points_count ?? 0 }}</span>
                    </template>

                    <template v-else-if="column.key === 'is_active'">
                        <Tag :color="record.is_active ? 'success' : 'default'" :bordered="false">
                            {{ record.is_active ? $t('global.active') : $t('global.inactive') }}
                        </Tag>
                    </template>

                    <template v-else-if="column.key === 'actions'">
                        <Tooltip :title="$t('qc_charts.show')">
                            <Link :href="route('lab_management.qc_charts.show', record.slug)">
                                <Button size="small"><LineChartOutlined /></Button>
                            </Link>
                        </Tooltip>
                        <Tooltip v-if="can('qc_charts.edit')" :title="$t('global.edit_hint')">
                            <Link :href="route('lab_management.qc_charts.edit', record.slug)">
                                <Button size="small" class="qci-action"><EditOutlined /></Button>
                            </Link>
                        </Tooltip>
                    </template>
                </template>
            </ResponsiveTable>
        </Card>
    </div>
</template>

<style scoped>
.qci-toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 12px;
}
.qci-toolbar__filters {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    min-width: 0;
}
.qci-toolbar__icon { color: var(--color-text-muted); }

.qci-select { min-width: 220px; }
.qci-select--short { min-width: 150px; }

.grid-card {
    border-radius: 12px;
    border: 1px solid var(--color-border);
}

.qci-link { font-weight: 600; color: var(--color-primary); }
.qci-sub  { font-size: 0.78rem; color: var(--color-text-muted); }
.qci-mono { font-family: ui-monospace, Consolas, monospace; font-size: 0.8125rem; }
.qci-count { font-variant-numeric: tabular-nums; }
.qci-action { margin-left: 6px; }

/* Los cinco límites en una sola celda: sigla arriba, número abajo, para que se
   lean de un vistazo sin abrir la carta. */
.qci-limits { display: flex; gap: 10px; flex-wrap: wrap; }
.qci-limit {
    display: inline-flex;
    flex-direction: column;
    line-height: 1.25;
    min-width: 52px;
}
.qci-limit__tag {
    font-size: 0.62rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    color: var(--color-text-muted);
}
.qci-limit__val {
    font-size: 0.8125rem;
    font-variant-numeric: tabular-nums;
    color: var(--color-text);
}
.qci-limit--lcs .qci-limit__tag,
.qci-limit--lci .qci-limit__tag { color: var(--color-danger-bright); }
.qci-limit--las .qci-limit__tag,
.qci-limit--lai .qci-limit__tag { color: var(--color-warning); }
.qci-limit--lc  .qci-limit__tag { color: var(--color-primary); }

.qci-empty {
    margin: 0;
    padding: 40px 16px;
    text-align: center;
    color: var(--color-text-muted);
    font-size: 0.875rem;
}

@media (max-width: 768px) {
    .qci-toolbar { flex-direction: column; align-items: stretch; }
    .qci-select, .qci-select--short { min-width: 0; width: 100%; }
}
</style>
