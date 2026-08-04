<script setup>
/**
 * La papelera de hojas de trabajo.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ NO HAY "ELIMINAR PARA SIEMPRE", Y ES A PROPÓSITO                         │
 * └──────────────────────────────────────────────────────────────────────────┘
 * El resto de las papeleras del sistema ofrece el borrado definitivo. Acá no.
 * Una hoja de trabajo es la constancia de un ensayo que el laboratorio corrió,
 * y sus valores crudos son lo que respalda un informe que ya salió firmado.
 * Dar de baja la saca de circulación —sus resultados dejan de informarse— y
 * con eso alcanza; un botón que destruya la fila sería un botón para borrar
 * evidencia.
 *
 * Restaurar tampoco es traerla de vuelta al listado: revierte TODO lo que la
 * baja se llevó (los resultados a la capa consultable, los puntos de la carta
 * de control, el estado de los ensayos). Lo hace el servidor.
 */
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { Alert, Button, Card, Input, Popconfirm, Space, Tooltip } from 'ant-design-vue';
import { DeleteOutlined, SearchOutlined, UndoOutlined } from '@ant-design/icons-vue';

import AppLayout from '@/Layouts/AppLayout.vue';
import ResponsiveTable from '@/Components/Common/ResponsiveTable.vue';
import SectionHeader from '@/Components/Common/SectionHeader.vue';
import { useDateFormat } from '@/Composables/useDateFormat';
import { useI18n } from '@/Plugins/i18n';
import { plainDate } from './config/format';

defineOptions({ layout: AppLayout });

const props = defineProps({
    worksheets: { type: Object, required: true },
    filters:    { type: Object, default: () => ({}) },
});

const { t } = useI18n();
const { formatDateTime } = useDateFormat();

const page = usePage();
const isSuper = page.props.auth?.user?.roles?.includes('super');
if (!isSuper) {
    router.visit(route('lab_management.worksheets.index'));
}

// Se busca por el NOMBRE DE LA PRUEBA: una hoja no tiene código propio, así
// que es lo único que la identifica de un vistazo.
const searchTerm = ref(props.filters.search ?? '');
let searchTimer = null;
watch(searchTerm, (valor) => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        router.reload({
            only: ['worksheets', 'filters'],
            data: { search: valor || undefined, page: 1 },
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    }, 300);
});
onBeforeUnmount(() => clearTimeout(searchTimer));

const restoring = ref(null);
const restore = (registro) => {
    restoring.value = registro.id;
    router.post(route('lab_management.worksheets.restore', registro.slug), {}, {
        preserveScroll: true,
        onFinish: () => { restoring.value = null; },
    });
};

const columns = computed(() => [
    { title: t('worksheets.run_date'),        dataIndex: 'run_date',    key: 'run_date',   width: 130, mobile: { role: 'subtitle' } },
    { title: t('worksheets.test_definition'), dataIndex: ['definition', 'name'], key: 'definition', ellipsis: true, mobile: { role: 'title' } },
    { title: t('worksheets.analyst'),         dataIndex: ['analyst', 'name'],    key: 'analyst',    width: 180, mobile: { role: 'meta' } },
    { title: t('worksheets.samples_count'),   dataIndex: 'samples_count', key: 'samples_count', width: 110, align: 'right', mobile: { role: 'meta' } },
    { title: t('worksheets.deleted_by'),      dataIndex: ['deleter', 'name'], key: 'deleter', width: 180, mobile: { role: 'meta' } },
    { title: t('worksheets.deleted_at'),      dataIndex: 'deleted_at',  key: 'deleted_at', width: 180, mobile: { role: 'meta' } },
    { title: t('worksheets.void_reason'),     dataIndex: 'void_reason', key: 'void_reason', ellipsis: true, mobile: { role: 'meta' } },
    { title: t('global.actions'),             key: 'actions', width: 140, fixed: 'right', mobile: { role: 'actions' } },
]);

const pagination = computed(() => ({
    current:  props.worksheets.current_page,
    pageSize: props.worksheets.per_page,
    total:    props.worksheets.total,
    showSizeChanger: true,
    pageSizeOptions: ['10', '25', '50', '100'],
    showTotal: (total, rango) => `${rango[0]}-${rango[1]} ${t('global.of')} ${total}`,
}));

const onTableChange = (pag) => {
    router.reload({
        only: ['worksheets', 'filters'],
        data: { page: pag.current, per_page: pag.pageSize, search: searchTerm.value || undefined },
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const subtitle = computed(() => {
    const palabra = props.worksheets.total === 1 ? t('global.record') : t('global.records');

    return `${props.worksheets.total} ${palabra} · ${t('global.super_only')}`;
});
</script>

<template>
    <Head :title="$t('worksheets.trash_title')" />

    <div v-if="isSuper" class="sap-form trash-page">
        <SectionHeader
            :back-href="route('lab_management.worksheets.index')"
            :title="$t('worksheets.trash_title')"
            :subtitle="subtitle"
            icon-bg="var(--color-danger)"
        >
            <template #icon><DeleteOutlined /></template>
        </SectionHeader>

        <Alert type="info" show-icon class="trash-note" :message="$t('worksheets.trash_intro')" />

        <div class="trash-toolbar">
            <Input
                v-model:value="searchTerm"
                :placeholder="$t('worksheets.trash_search')"
                allow-clear
                class="trash-search"
            >
                <template #prefix><SearchOutlined /></template>
            </Input>
        </div>

        <Card :body-style="{ padding: 0 }" class="grid-card">
            <!-- Siempre tabla, nunca tarjetas: la papelera se lee para comparar
                 motivos y fechas, y en tarjetas eso no se compara. -->
            <ResponsiveTable
                :data-source="worksheets.data"
                :view="'table'"
                :scroll="{ x: 'max-content' }"
                :columns="columns"
                :pagination="pagination"
                row-key="id"
                @change="onTableChange"
            >
                <template #bodyCell="{ column, record }">
                    <template v-if="column.key === 'run_date'">
                        {{ plainDate(record.run_date) }}
                    </template>

                    <template v-else-if="column.key === 'definition'">
                        {{ record.definition?.name ?? '—' }}
                    </template>

                    <template v-else-if="column.key === 'analyst'">
                        {{ record.analyst?.name ?? '—' }}
                    </template>

                    <template v-else-if="column.key === 'deleter'">
                        <span v-if="record.deleter">{{ record.deleter.name }}</span>
                        <span v-else class="text-muted">—</span>
                    </template>

                    <template v-else-if="column.key === 'deleted_at'">
                        {{ formatDateTime(record.deleted_at) }}
                    </template>

                    <template v-else-if="column.key === 'void_reason'">
                        <Tooltip v-if="record.void_reason" :title="record.void_reason">
                            <span class="reason-cell">{{ record.void_reason }}</span>
                        </Tooltip>
                        <span v-else class="text-muted">{{ $t('global.no_reason') }}</span>
                    </template>

                    <template v-else-if="column.key === 'actions'">
                        <Space :size="4">
                            <Popconfirm
                                :title="$t('global.restore') + '?'"
                                :description="$t('worksheets.restored')"
                                :ok-text="$t('global.restore')"
                                :cancel-text="$t('global.cancel')"
                                placement="topRight"
                                @confirm="restore(record)"
                            >
                                <Button size="small" type="text" :loading="restoring === record.id">
                                    <UndoOutlined /> {{ $t('global.restore') }}
                                </Button>
                            </Popconfirm>
                        </Space>
                    </template>

                    <template v-else>{{ record[column.dataIndex] ?? '—' }}</template>
                </template>

                <!-- Un solo vacío: el de la tabla. Antes había además un
                     `Empty` debajo y la papelera vacía mostraba dos. -->
                <template #empty>
                    <div class="trash-empty">{{ $t('worksheets.trash_empty') }}</div>
                </template>
            </ResponsiveTable>
        </Card>
    </div>
</template>

<style scoped>
.trash-note { margin-bottom: 12px; }
.trash-empty { padding: 40px 16px; text-align: center; color: var(--color-text-muted); }
.grid-card :deep(.ant-table-thead > tr > th) {
    background: var(--color-surface-alt);
    color: var(--color-text-strong);
    font-weight: 600;
    font-size: 0.8125rem;
}
.text-muted { color: var(--color-text-dim); font-style: italic; }
.reason-cell {
    color: var(--color-text-muted);
    font-size: 0.8125rem;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    display: inline-block;
    max-width: 100%;
}
</style>
