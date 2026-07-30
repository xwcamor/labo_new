<script setup>
/**
 * Listado global de informes de ensayo — el "Listado de Nº de Reportes".
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ POR QUÉ ORDENA Y BUSCA EL SERVIDOR, Y NO EL NAVEGADOR                    │
 * └──────────────────────────────────────────────────────────────────────────┘
 * El sistema anterior armaba esta pantalla con DataTables: mandaba TODAS las
 * filas al navegador y él las ordenaba y filtraba. Se veía instantáneo con cien
 * registros y dejó de verse cuando un cliente pidió 130 pruebas: el estado de
 * cada muestra se recalculaba al LEER el listado, así que abrirlo disparaba un
 * recorrido completo con escrituras, cada vez que alguien entraba.
 *
 * Acá cada tecla y cada flecha de encabezado son una consulta que devuelve UNA
 * página. La pantalla no recorre nada, no ordena nada y no escribe nada. Cuesta
 * un viaje al servidor por búsqueda —de ahí el retardo de 400 ms al tipear— y a
 * cambio la pantalla se abre igual con diez informes que con cincuenta mil.
 *
 * Las columnas son las del sistema anterior, con sus mismas palabras: el
 * laboratorio las lee todos los días.
 */
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import {
    Button, Card, Dropdown, Input, Menu, MenuItem, Select, SelectOption, Space, Tag, Tooltip,
} from 'ant-design-vue';
import {
    ClearOutlined, DownloadOutlined, FilePdfOutlined, FileTextOutlined,
    LockOutlined, SearchOutlined, SolutionOutlined,
} from '@ant-design/icons-vue';

import AppLayout from '@/Layouts/AppLayout.vue';
import ResponsiveTable from '@/Components/Common/ResponsiveTable.vue';
import ReportAnalysisModal from '@/Components/Receptions/ReportAnalysisModal.vue';
import { useI18n } from '@/Plugins/i18n';

defineOptions({ layout: AppLayout });

const props = defineProps({
    reports:  { type: Object, required: true },
    filters:  { type: Object, default: () => ({}) },
    statuses: { type: Array,  default: () => [] },
    kinds:    { type: Array,  default: () => [] },
});

const { t, tc } = useI18n();

/**
 * Las columnas que aceptan casilla de búsqueda. La lista es la MISMA que la
 * constante `BUSCABLES` del controlador: si acá apareciera una que el servidor
 * no conoce, la casilla se dibujaría y no filtraría nada.
 */
const BUSCABLES = [
    'sample_code', 'code', 'service_order', 'customer_name',
    'equipment_serial', 'equipment_type', 'oil_type', 'sampling_reason',
];

const loading = ref(false);

// El estado de los filtros. Uno por columna buscable, más el buscador global y
// los dos desplegables (estado y tipo), que son listas cerradas y no texto.
const busqueda = reactive(Object.fromEntries(
    BUSCABLES.map((clave) => [clave, props.filters[clave] ?? '']),
));
const global = ref(props.filters.q ?? '');
const estado = ref(props.filters.status ?? null);
const tipo   = ref(props.filters.kind ?? null);

const hayFiltros = computed(() => !!(
    global.value || estado.value || tipo.value
    || BUSCABLES.some((clave) => busqueda[clave])
));

const consultar = (extra = {}) => {
    const parametros = {
        q:         global.value?.trim() || undefined,
        status:    estado.value || undefined,
        kind:      tipo.value || undefined,
        sort:      props.filters.sort || undefined,
        direction: props.filters.direction || undefined,
        per_page:  props.filters.per_page || undefined,
        ...extra,
    };

    BUSCABLES.forEach((clave) => {
        parametros[clave] = busqueda[clave]?.trim() || undefined;
    });

    router.get(route('lab_management.sample_reports.index'), parametros, {
        preserveScroll: true,
        preserveState:  true,
        replace:        true,
        onStart:  () => { loading.value = true; },
        onFinish: () => { loading.value = false; },
    });
};

// Los desplegables consultan al soltar; el texto espera a que se termine de
// escribir. Sin el retardo, "0114" son cuatro consultas con LIKE sobre el
// listado entero y las tres primeras se tiran.
watch([estado, tipo], () => consultar({ page: 1 }));

let reloj = null;
watch([global, () => ({ ...busqueda })], () => {
    clearTimeout(reloj);
    reloj = setTimeout(() => consultar({ page: 1 }), 400);
}, { deep: true });
onBeforeUnmount(() => clearTimeout(reloj));

const limpiar = () => {
    global.value = '';
    estado.value = null;
    tipo.value = null;
    BUSCABLES.forEach((clave) => { busqueda[clave] = ''; });
};

// ── Columnas ─────────────────────────────────────────────────────────────
/**
 * `sorter: true` le dice a la tabla que dibuje la flecha pero NO ordene: el
 * orden lo resuelve el motor y llega ya resuelto en la página. Marcar
 * `sortOrder` es lo que mantiene la flecha pintada después de recargar.
 */
const flecha = (clave) => (props.filters.sort === clave
    ? (props.filters.direction === 'asc' ? 'ascend' : 'descend')
    : null);

const columnas = computed(() => [
    { title: t('sample_reports.col_sample_code'), key: 'sample_code', dataIndex: 'sample_code', width: 140, sorter: true, sortOrder: flecha('sample_code'), mobile: { role: 'title' } },
    { title: t('sample_reports.status'), key: 'status', dataIndex: 'status', width: 120, sorter: true, sortOrder: flecha('status'), mobile: { role: 'status' } },
    { title: t('sample_reports.col_code'), key: 'code', dataIndex: 'code', width: 180, sorter: true, sortOrder: flecha('code'), mobile: { role: 'subtitle' } },
    { title: t('sample_reports.col_service_order'), key: 'service_order', dataIndex: 'service_order', width: 120, sorter: true, sortOrder: flecha('service_order') },
    { title: t('sample_reports.col_customer'), key: 'customer_name', dataIndex: 'customer_name', width: 220, sorter: true, sortOrder: flecha('customer_name'), mobile: { role: 'meta' } },
    { title: t('sample_reports.col_equipment_serial'), key: 'equipment_serial', dataIndex: 'equipment_serial', width: 180, sorter: true, sortOrder: flecha('equipment_serial'), mobile: { role: 'meta' } },
    { title: t('sample_reports.col_equipment_type'), key: 'equipment_type', dataIndex: 'equipment_type', width: 160, sorter: true, sortOrder: flecha('equipment_type') },
    { title: t('sample_reports.col_oil_type'), key: 'oil_type', dataIndex: 'oil_type', width: 150, sorter: true, sortOrder: flecha('oil_type') },
    { title: t('sample_reports.col_voltage'), key: 'voltage_kv', dataIndex: 'voltage_kv', width: 120, align: 'right', sorter: true, sortOrder: flecha('voltage_kv') },
    { title: t('sample_reports.col_power'), key: 'power_mva', dataIndex: 'power_mva', width: 130, align: 'right', sorter: true, sortOrder: flecha('power_mva') },
    { title: t('sample_reports.col_received_at'), key: 'received_at', dataIndex: 'received_at', width: 130, sorter: true, sortOrder: flecha('received_at') },
    { title: t('sample_reports.col_issued_at'), key: 'issued_at', dataIndex: 'issued_at', width: 130, sorter: true, sortOrder: flecha('issued_at'), mobile: { role: 'meta' } },
    { title: t('sample_reports.col_delivered_at'), key: 'delivered_at', dataIndex: 'delivered_at', width: 130, sorter: true, sortOrder: flecha('delivered_at') },
    { title: t('sample_reports.col_sampling_reason'), key: 'sampling_reason', dataIndex: 'sampling_reason', width: 180, sorter: true, sortOrder: flecha('sampling_reason') },
    { title: t('sample_reports.kind'), key: 'kind', dataIndex: 'kind', width: 120, sorter: true, sortOrder: flecha('kind') },
    { title: t('sample_reports.col_actions'), key: 'actions', width: 110, align: 'center', mobile: { role: 'actions' } },
    { title: t('sample_reports.col_download'), key: 'download', width: 120, align: 'center', mobile: { role: 'hidden' } },
]);

const buscable = (clave) => BUSCABLES.includes(clave);

// ── Paginación y orden ───────────────────────────────────────────────────
const paginacion = computed(() => ({
    current:  props.reports.current_page,
    pageSize: props.reports.per_page,
    total:    props.reports.total,
    showSizeChanger: true,
    pageSizeOptions: ['10', '25', '50', '100'],
    showTotal: (total) => tc('sample_reports.total_records', total, { count: total }),
}));

const alCambiar = (pagina, _filtros, orden) => {
    // Antd manda `order: undefined` en el tercer clic (quitar el orden). Ahí se
    // vuelve al orden natural del servidor, que es el informe más nuevo primero.
    const direccion = orden?.order === 'ascend' ? 'asc'
        : orden?.order === 'descend' ? 'desc' : undefined;

    consultar({
        page:      pagina.current,
        per_page:  pagina.pageSize,
        sort:      direccion ? orden.columnKey : undefined,
        direction: direccion,
    });
};

// ── Formato ──────────────────────────────────────────────────────────────
// Las fechas llegan como texto ISO y se muestran d-m-Y, que es como las escribe
// el laboratorio. Sin `Date`: partir el texto no arrastra la zona horaria, que
// es lo que corre una fecha un día para atrás.
const fecha = (valor) => {
    if (!valor) return '—';
    const [y, m, d] = String(valor).slice(0, 10).split('-');
    return d && m && y ? `${d}-${m}-${y}` : '—';
};

// Los decimales de la placa vienen como cadena ("220.00"): se imprime el número
// sin los ceros de relleno, que es lo que dice la placa.
const numero = (valor) => (valor === null || valor === undefined || valor === ''
    ? '—'
    : String(Number(valor)));

// ── El análisis del informe ──────────────────────────────────────────────
// El mismo modal de la ficha de la entrega, no una pantalla propia: el análisis
// se consulta desde donde se encontró el informe, y desde acá el informe se
// encuentra por su número. El modal pide sus datos solo al abrirse.
const analisisAbierto = ref(false);
const analisisInforme = ref(null);
const verAnalisis = (informe) => {
    analisisInforme.value = informe;
    analisisAbierto.value = true;
};
</script>

<template>
    <Head :title="$t('sample_reports.index_title')" />

    <div class="sap-index">
        <div class="mi-title">
            <div class="page-header__title">
                <div class="page-header__icon"><FileTextOutlined /></div>
                <div class="page-header__heading">
                    <h1>{{ $t('sample_reports.index_title') }}</h1>
                    <p>{{ $t('sample_reports.index_subtitle') }}</p>
                </div>
            </div>
        </div>

        <Card class="sr-filters" :body-style="{ padding: '14px 16px' }">
            <Space :size="10" wrap>
                <Input
                    v-model:value="global"
                    allow-clear
                    class="sr-filters__search"
                    :placeholder="$t('sample_reports.search_all')"
                >
                    <template #prefix><SearchOutlined /></template>
                </Input>

                <Select
                    v-model:value="estado"
                    allow-clear
                    style="min-width: 160px"
                    :placeholder="$t('sample_reports.status')"
                >
                    <SelectOption v-for="s in statuses" :key="s" :value="s">
                        {{ $t(`sample_reports.status_${s}`) }}
                    </SelectOption>
                </Select>

                <Select
                    v-model:value="tipo"
                    allow-clear
                    style="min-width: 160px"
                    :placeholder="$t('sample_reports.kind')"
                >
                    <SelectOption v-for="k in kinds" :key="k" :value="k">
                        {{ $t(`sample_reports.kind_${k}`) }}
                    </SelectOption>
                </Select>

                <Button v-if="hayFiltros" type="link" @click="limpiar">
                    <ClearOutlined /> {{ $t('global.clear_filters') }}
                </Button>
            </Space>
        </Card>

        <Card :body-style="{ padding: 0 }" class="grid-card">
            <ResponsiveTable
                :columns="columnas"
                :data-source="reports.data"
                :pagination="paginacion"
                :loading="loading"
                :scroll="{ x: 'max-content' }"
                row-key="id"
                @change="alCambiar"
            >
                <template #empty>
                    <div class="sr-empty">{{ $t('sample_reports.index_empty') }}</div>
                </template>

                <!-- La casilla "Buscar" debajo de cada rótulo, como la fila de
                     filtros del sistema anterior. Va DENTRO del encabezado y no
                     en una segunda fila para que se quede quieta con el thead
                     pegado y con las columnas fijas. -->
                <template #headerCell="{ column, title }">
                    <div class="sr-th">
                        <span class="sr-th__label">
                            {{ title }}
                            <Tooltip
                                v-if="column.key === 'voltage_kv' || column.key === 'power_mva'"
                                :title="$t('sample_reports.plate_max_hint')"
                            >
                                <span class="sr-th__hint">*</span>
                            </Tooltip>
                        </span>
                        <Input
                            v-if="buscable(column.key)"
                            v-model:value="busqueda[column.key]"
                            size="small"
                            allow-clear
                            class="sr-th__input"
                            :placeholder="$t('sample_reports.search_column')"
                            @click.stop
                        />
                    </div>
                </template>

                <template #bodyCell="{ column, record, text }">
                    <template v-if="column.key === 'sample_code'">
                        <span class="sr-code">{{ record.sample_code ?? '—' }}</span>
                    </template>

                    <template v-else-if="column.key === 'status'">
                        <Tag :color="record.status === 'issued' ? 'green' : 'default'" :bordered="false">
                            {{ $t(`sample_reports.status_${record.status}`) }}
                        </Tag>
                    </template>

                    <template v-else-if="column.key === 'code'">
                        <span class="sr-code">{{ record.code ?? '—' }}</span>
                    </template>

                    <template v-else-if="column.key === 'kind'">
                        {{ $t(`sample_reports.kind_${record.kind}`) }}
                    </template>

                    <template v-else-if="column.key === 'voltage_kv'">
                        {{ numero(record.voltage_kv) }}
                    </template>

                    <template v-else-if="column.key === 'power_mva'">
                        {{ numero(record.power_mva) }}
                    </template>

                    <template
                        v-else-if="['received_at', 'issued_at', 'delivered_at'].includes(column.key)"
                    >
                        {{ fecha(record[column.key]) }}
                    </template>

                    <!-- Las acciones. El borrador se edita desde la ficha de su
                         entrega (es donde está el formulario con la cabecera);
                         acá va lo que se puede hacer sobre el informe en sí:
                         mirar su análisis y, si está emitido, el candado que
                         dice por qué no hay nada más que hacerle. -->
                    <template v-else-if="column.key === 'actions'">
                        <Space :size="4">
                            <Tooltip :title="$t('sample_reports.analysis_tab')">
                                <Button size="small" @click="verAnalisis(record)">
                                    <SolutionOutlined />
                                </Button>
                            </Tooltip>
                            <Tooltip
                                v-if="record.status !== 'draft'"
                                :title="$t('sample_reports.issued_is_final')"
                            >
                                <Tag color="gold" :bordered="false" class="sr-lock">
                                    <LockOutlined />
                                </Tag>
                            </Tooltip>
                        </Space>
                    </template>

                    <!-- Un botón con las dos plantillas adentro, el mismo de la
                         ficha de la entrega. Sobre un borrador no se ofrece: no
                         tiene número emitido y bajarlo invitaría a mandarle al
                         cliente un papel sin correlativo. -->
                    <template v-else-if="column.key === 'download'">
                        <Dropdown v-if="record.status !== 'draft'" :trigger="['click']">
                            <Tooltip :title="$t('sample_reports.download')">
                                <Button size="small"><DownloadOutlined /></Button>
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
                        <Tooltip v-else :title="$t('sample_reports.download_locked')">
                            <span class="sr-muted">—</span>
                        </Tooltip>
                    </template>

                    <template v-else>{{ text ?? '—' }}</template>
                </template>
            </ResponsiveTable>
        </Card>

        <ReportAnalysisModal
            v-model:open="analisisAbierto"
            :report="analisisInforme"
        />
    </div>
</template>

<style scoped>
.sr-filters { margin-bottom: 12px; }
.sr-filters__search { min-width: 260px; }

/* El encabezado de dos pisos: rótulo arriba, casilla de búsqueda abajo. La
   casilla no hereda el `cursor: pointer` del th ordenable para que se vea que
   se escribe, no que se clickea. */
.sr-th { display: flex; flex-direction: column; gap: 4px; }
.sr-th__label { white-space: nowrap; }
.sr-th__hint { color: var(--color-text-muted); margin-left: 2px; cursor: help; }
.sr-th__input { cursor: text; font-weight: 400; }

.sr-code { font-weight: 600; white-space: nowrap; }
.sr-lock { margin-inline-end: 0; }
.sr-muted { color: var(--color-text-muted); }
.sr-empty { padding: 40px 16px; text-align: center; color: var(--color-text-muted); }
</style>
