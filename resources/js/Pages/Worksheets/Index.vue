<script setup>
/**
 * Listado de hojas de trabajo.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ EL FILTRO DE FECHA SE VE                                                 │
 * └──────────────────────────────────────────────────────────────────────────┘
 * El sistema viejo aplicaba en silencio un "últimos tres meses" cuando no se
 * mandaba fecha. Los ensayos más viejos no aparecían y nada en la pantalla lo
 * decía: quien buscaba una hoja del año pasado concluía que se había perdido.
 *
 * Acá el rango vacío significa TODO, y la pantalla lo dice con todas las
 * letras junto al filtro. Un filtro que no se ve es un filtro que miente.
 */
import { computed, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import {
    Button, Card, DatePicker, Select, SelectOptGroup, SelectOption, Space, Tag, Tooltip,
} from 'ant-design-vue';
import {
    ClearOutlined, ProfileOutlined, InfoCircleOutlined, PlusOutlined,
} from '@ant-design/icons-vue';

import AppLayout from '@/Layouts/AppLayout.vue';
import ResponsiveTable from '@/Components/Common/ResponsiveTable.vue';
import WorksheetStatusTag from '@/Components/Worksheets/WorksheetStatusTag.vue';
import { useAuth } from '@/Composables/useAuth';
import { useI18n } from '@/Plugins/i18n';
import { groupTests, isGrouped } from '@/Utils/testGroups';
import { plainDate } from './config/format';
import { worksheetsTableColumns } from './config/columns';

defineOptions({ layout: AppLayout });

const props = defineProps({
    worksheets: { type: Object, required: true },
    tests:      { type: Array,  default: () => [] },
    statuses:   { type: Array,  default: () => [] },
    filters:    { type: Object, default: () => ({}) },
});

const { t } = useI18n();
const { can } = useAuth();

const columns = computed(() => worksheetsTableColumns(t));

/**
 * Las pruebas del filtro, por grupo (Físico Químico · Cromatografías · Otros).
 * Son 29 en una lista plana: sin los encabezados hay que leerlas todas para
 * encontrar una. El orden de los grupos y el de las pruebas dentro de cada uno
 * salen del dato, no de una lista escrita acá.
 */
const testGroups = computed(() => groupTests(props.tests, t('worksheets.group_none')));

/** Sin grupos que separar se dibuja la lista de siempre, sin encabezados. */
const showGroups = computed(() => isGrouped(testGroups.value));

// ── Filtros ──────────────────────────────────────────────────────────────
const testFilter   = ref(props.filters.test_definition ?? null);
const statusFilter = ref(props.filters.status ?? null);
const dateRange    = ref(
    props.filters.from || props.filters.to
        ? [props.filters.from ?? null, props.filters.to ?? null]
        : null,
);

const loading = ref(false);

/** ¿Hay algún filtro puesto? Se usa para ofrecer "limpiar" solo cuando sirve. */
const hasFilters = computed(
    () => !!(testFilter.value || statusFilter.value || dateRange.value?.[0] || dateRange.value?.[1]),
);

/** Sin rango no se manda fecha, y sin fecha el servidor no acota nada. */
const noDateFilter = computed(() => !dateRange.value?.[0] && !dateRange.value?.[1]);

const apply = (extra = {}) => {
    router.get(
        route('lab_management.worksheets.index'),
        {
            test_definition: testFilter.value || undefined,
            status:          statusFilter.value || undefined,
            from:            dateRange.value?.[0] || undefined,
            to:              dateRange.value?.[1] || undefined,
            sort:            props.filters.sort,
            direction:       props.filters.direction,
            per_page:        props.filters.per_page,
            ...extra,
        },
        {
            preserveScroll: true,
            preserveState:  true,
            onStart:  () => { loading.value = true; },
            onFinish: () => { loading.value = false; },
        },
    );
};

watch([testFilter, statusFilter, dateRange], () => apply({ page: 1 }));

const clear = () => {
    testFilter.value = null;
    statusFilter.value = null;
    dateRange.value = null;
};

// ── Paginación y orden ───────────────────────────────────────────────────
const pagination = computed(() => ({
    current:  props.worksheets.current_page,
    pageSize: props.worksheets.per_page,
    total:    props.worksheets.total,
    showSizeChanger: true,
    pageSizeOptions: ['10', '25', '50', '100'],
}));

const onTableChange = (page, _filters, sorter) => {
    const sort = sorter?.field || props.filters.sort;
    const direction = sorter?.order === 'ascend' ? 'asc'
        : sorter?.order === 'descend' ? 'desc'
            : props.filters.direction;

    apply({ page: page.current, per_page: page.pageSize, sort, direction });
};
</script>

<template>
    <Head :title="$t('worksheets.title')" />

    <div class="sap-index">
        <div class="mi-title">
            <div class="page-header__title">
                <div class="page-header__icon"><ProfileOutlined /></div>
                <div class="page-header__heading">
                    <h1>{{ $t('worksheets.title') }}</h1>
                    <p>{{ $t('worksheets.intro') }}</p>
                </div>
            </div>
            <div class="mi-title__actions">
                <Tooltip v-if="can('worksheets.create')" :title="$t('worksheets.create')">
                    <Link :href="route('lab_management.worksheets.create')">
                        <Button type="primary">
                            <PlusOutlined /> {{ $t('worksheets.create') }}
                        </Button>
                    </Link>
                </Tooltip>
            </div>
        </div>

        <Card class="ws-filters" :body-style="{ padding: '14px 16px' }">
            <Space :size="10" wrap>
                <Select
                    v-model:value="testFilter"
                    allow-clear
                    show-search
                    option-filter-prop="label"
                    style="min-width: 220px"
                    :placeholder="$t('worksheets.test_definition')"
                >
                    <!-- Agrupadas por familia de ensayo. La búsqueda sigue
                         filtrando por el nombre de la prueba: el encabezado
                         ordena la lectura, no obliga a elegir grupo primero. -->
                    <template v-if="showGroups">
                        <SelectOptGroup v-for="group in testGroups" :key="group.key" :label="group.label">
                            <SelectOption
                                v-for="test in group.tests"
                                :key="test.slug"
                                :value="test.slug"
                                :label="test.name"
                            >
                                {{ test.name }}
                            </SelectOption>
                        </SelectOptGroup>
                    </template>

                    <template v-else>
                        <SelectOption
                            v-for="test in tests"
                            :key="test.slug"
                            :value="test.slug"
                            :label="test.name"
                        >
                            {{ test.name }}
                        </SelectOption>
                    </template>
                </Select>

                <Select
                    v-model:value="statusFilter"
                    allow-clear
                    style="min-width: 180px"
                    :placeholder="$t('worksheets.status')"
                >
                    <SelectOption v-for="status in statuses" :key="status" :value="status">
                        {{ $t(`worksheets.state.${status}`) }}
                    </SelectOption>
                </Select>

                <DatePicker.RangePicker
                    v-model:value="dateRange"
                    value-format="YYYY-MM-DD"
                    style="min-width: 260px"
                    :placeholder="[$t('global.from'), $t('global.to')]"
                />

                <!-- El estado del filtro de fecha, escrito. Sin rango se listan
                     TODAS las fechas: no hay ningún recorte por detrás. -->
                <Tag :bordered="false" class="ws-filters__scope">
                    <InfoCircleOutlined />
                    {{ $t('worksheets.run_date') }}:
                    <strong v-if="noDateFilter">{{ $t('global.all') }}</strong>
                    <strong v-else>
                        {{ plainDate(dateRange[0]) || $t('global.all') }}
                        —
                        {{ plainDate(dateRange[1]) || $t('global.all') }}
                    </strong>
                </Tag>

                <Button v-if="hasFilters" type="link" @click="clear">
                    <ClearOutlined /> {{ $t('global.clear_filters') }}
                </Button>
            </Space>
        </Card>

        <Card :body-style="{ padding: 0 }" class="grid-card">
            <ResponsiveTable
                :columns="columns"
                :data-source="worksheets.data"
                :pagination="pagination"
                :loading="loading"
                :scroll="{ x: 'max-content' }"
                row-key="id"
                @change="onTableChange"
            >
                <template #empty>
                    <div class="ws-empty">{{ $t('worksheets.empty') }}</div>
                </template>

                <template #bodyCell="{ column, record, text }">
                    <template v-if="column.key === 'run_date'">
                        <Link :href="route('lab_management.worksheets.show', record.slug)" class="ws-link">
                            {{ plainDate(record.run_date) }}
                        </Link>
                    </template>

                    <template v-else-if="column.key === 'definition'">
                        {{ record.definition?.name ?? '—' }}
                    </template>

                    <template v-else-if="column.key === 'analyst'">
                        {{ record.analyst?.name ?? '—' }}
                    </template>

                    <template v-else-if="column.key === 'status'">
                        <WorksheetStatusTag :status="record.status" />
                    </template>

                    <template v-else-if="column.key === 'validator'">
                        {{ record.validator?.name ?? '—' }}
                    </template>

                    <template v-else>{{ text ?? '—' }}</template>
                </template>
            </ResponsiveTable>
        </Card>
    </div>
</template>

<style scoped>
.ws-filters { margin-bottom: 12px; }
.ws-filters__scope { color: var(--color-text-muted); }
.ws-filters__scope strong { color: var(--color-text); }
.ws-link { font-weight: 600; }
.ws-empty { padding: 40px 16px; text-align: center; color: var(--color-text-muted); }
</style>
