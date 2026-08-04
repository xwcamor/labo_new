<script setup>
/**
 * Etiquetas de muestra: el menú desde el que se imprime.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ POR QUÉ ES UN MENÚ Y NO UN BOTÓN DE LA ENTREGA                           │
 * └──────────────────────────────────────────────────────────────────────────┘
 * Antes esto vivía como dos botones dentro de la ficha de la entrega. Se sacó
 * acá porque así trabaja el laboratorio: quien imprime etiquetas va a imprimir
 * etiquetas y nada más — no está en medio de registrar una entrega. En el
 * sistema anterior era "Control de Stickers", con su propia entrada de menú.
 *
 * La hoja imprimible se abre en otra pestaña: es papel que va a la impresora,
 * no una pantalla de la que se vuelve. Y va por POST porque la selección puede
 * ser de doscientos códigos, que en una dirección no entran.
 */
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { Button, Card, DatePicker, Input, Space, Tooltip } from 'ant-design-vue';
import { PrinterOutlined, SearchOutlined, TagsOutlined } from '@ant-design/icons-vue';

import AppLayout from '@/Layouts/AppLayout.vue';
import ResponsiveTable from '@/Components/Common/ResponsiveTable.vue';
import { useI18n } from '@/Plugins/i18n';

defineOptions({ layout: AppLayout });

const props = defineProps({
    samples: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
});

const { t } = useI18n();

const buscado = ref(props.filters.search ?? '');
const comentario = ref('');
const rango = ref(
    props.filters.from || props.filters.to
        ? [props.filters.from ?? null, props.filters.to ?? null]
        : null,
);

const recargar = (extra = {}) => {
    router.reload({
        only: ['samples', 'filters'],
        data: {
            search: buscado.value || undefined,
            from:   rango.value?.[0] || undefined,
            to:     rango.value?.[1] || undefined,
            page:   1,
            ...extra,
        },
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

let temporizador = null;
watch([buscado, rango], () => {
    clearTimeout(temporizador);
    temporizador = setTimeout(() => recargar(), 300);
});
onBeforeUnmount(() => clearTimeout(temporizador));

// ── Selección ────────────────────────────────────────────────────────────
const seleccionadas = ref([]);
const rowSelection = computed(() => ({
    selectedRowKeys: seleccionadas.value,
    onChange: (claves) => { seleccionadas.value = claves; },
}));

/**
 * Manda la tanda a la hoja imprimible.
 *
 * Se arma un formulario y se envía por POST a otra pestaña: doscientos códigos
 * en una dirección superarían lo que un navegador acepta, y el listado de qué
 * se imprimió no tiene por qué quedar en el historial del navegador.
 */
const imprimir = (codigos) => {
    if (!codigos.length) return;

    const form = document.createElement('form');
    form.method = 'POST';
    form.action = route('lab_management.sample_labels.print');
    form.target = '_blank';

    const agregar = (nombre, valor) => {
        const campo = document.createElement('input');
        campo.type = 'hidden';
        campo.name = nombre;
        campo.value = valor;
        form.appendChild(campo);
    };

    agregar('_token', document.querySelector('meta[name="csrf-token"]')?.content ?? '');
    codigos.forEach((codigo) => agregar('codes[]', codigo));
    if (comentario.value.trim()) agregar('comment', comentario.value.trim());

    document.body.appendChild(form);
    form.submit();
    form.remove();
};

const imprimirSeleccionadas = () => imprimir(
    props.samples.data.filter((m) => seleccionadas.value.includes(m.id)).map((m) => m.code),
);

const columns = computed(() => [
    { title: t('worksheets.sample_code'), dataIndex: 'code', key: 'code', width: 150, mobile: { role: 'title' } },
    { title: t('labels.reception'),  dataIndex: ['reception', 'code'], key: 'reception', width: 150, mobile: { role: 'meta' } },
    { title: t('labels.customer'),   dataIndex: ['reception', 'customer', 'name'], key: 'customer', ellipsis: true, mobile: { role: 'subtitle' } },
    { title: t('labels.equipment'),  dataIndex: ['equipment', 'tag'], key: 'equipment', width: 160, mobile: { role: 'meta' } },
    { title: t('labels.received'),   dataIndex: ['reception', 'received_at'], key: 'received', width: 140, mobile: { role: 'meta' } },
    { title: t('global.actions'),    key: 'actions', width: 90, fixed: 'right', align: 'center', mobile: { role: 'actions' } },
]);

const pagination = computed(() => ({
    current:  props.samples.current_page,
    pageSize: props.samples.per_page,
    total:    props.samples.total,
    showSizeChanger: true,
    pageSizeOptions: ['10', '25', '50', '100'],
}));

const onTableChange = (pag) => recargar({ page: pag.current, per_page: pag.pageSize });

const fecha = (valor) => (valor ? String(valor).slice(0, 10).split('-').reverse().join('-') : '—');
</script>

<template>
    <Head :title="$t('labels.title')" />

    <div class="sap-index">
        <div class="mi-title">
            <div class="page-header__title">
                <div class="page-header__icon"><TagsOutlined /></div>
                <div class="page-header__heading">
                    <h1>{{ $t('labels.title') }}</h1>
                    <p>{{ $t('labels.intro') }}</p>
                </div>
            </div>
        </div>

        <Card class="lbl-filters" :body-style="{ padding: '14px 16px' }">
            <Space :size="10" wrap>
                <Input
                    v-model:value="buscado"
                    allow-clear
                    class="lbl-filters__search"
                    :placeholder="$t('labels.search')"
                >
                    <template #prefix><SearchOutlined /></template>
                </Input>

                <DatePicker.RangePicker
                    v-model:value="rango"
                    value-format="YYYY-MM-DD"
                    style="min-width: 250px"
                    :placeholder="[$t('global.from'), $t('global.to')]"
                />

                <!-- El comentario del sistema anterior: una línea que sale en
                     TODAS las etiquetas de la tanda. -->
                <Tooltip :title="$t('labels.comment_hint')">
                    <Input
                        v-model:value="comentario"
                        allow-clear
                        class="lbl-filters__comment"
                        :maxlength="120"
                        :placeholder="$t('labels.comment_field')"
                    />
                </Tooltip>

                <Button
                    v-if="seleccionadas.length > 0"
                    type="primary"
                    @click="imprimirSeleccionadas"
                >
                    <PrinterOutlined />
                    {{ $t('labels.print_selected') }} ({{ seleccionadas.length }})
                </Button>
            </Space>
        </Card>

        <Card :body-style="{ padding: 0 }" class="grid-card">
            <ResponsiveTable
                :columns="columns"
                :data-source="samples.data"
                :pagination="pagination"
                :row-selection="rowSelection"
                :scroll="{ x: 'max-content' }"
                :view="'table'"
                row-key="id"
                @change="onTableChange"
            >
                <template #empty>
                    <div class="lbl-empty">{{ $t('labels.empty') }}</div>
                </template>

                <template #bodyCell="{ column, record }">
                    <template v-if="column.key === 'code'">
                        <strong>{{ record.code }}</strong>
                    </template>

                    <template v-else-if="column.key === 'reception'">
                        {{ record.reception?.code ?? '—' }}
                    </template>

                    <template v-else-if="column.key === 'customer'">
                        {{ record.reception?.customer?.name ?? '—' }}
                    </template>

                    <template v-else-if="column.key === 'equipment'">
                        {{ record.equipment?.tag || record.equipment?.name || '—' }}
                    </template>

                    <template v-else-if="column.key === 'received'">
                        {{ fecha(record.reception?.received_at) }}
                    </template>

                    <template v-else-if="column.key === 'actions'">
                        <Tooltip :title="$t('labels.print_one', { code: record.code })">
                            <Button size="small" type="text" @click.stop="imprimir([record.code])">
                                <PrinterOutlined />
                            </Button>
                        </Tooltip>
                    </template>

                    <template v-else>—</template>
                </template>
            </ResponsiveTable>
        </Card>
    </div>
</template>

<style scoped>
.lbl-filters { margin-bottom: 12px; }
.lbl-filters__search  { min-width: 220px; }
.lbl-filters__comment { min-width: 240px; }
.lbl-empty { padding: 40px 16px; text-align: center; color: var(--color-text-muted); }
</style>
