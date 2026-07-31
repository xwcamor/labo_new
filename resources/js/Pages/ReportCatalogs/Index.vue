<script setup>
/**
 * Las cuatro listas chicas del formulario del informe.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ POR QUÉ ESTA PANTALLA EXISTE                                             │
 * └──────────────────────────────────────────────────────────────────────────┘
 * Motivo del análisis, punto de muestreo, marca de aceite y unidad de volumen
 * eran cuatro campos de TEXTO LIBRE. Por eso la base del sistema anterior
 * terminó con «2500 gal», «2500 galones» y «2500Gal» para la misma unidad, y con
 * «Inferior», «inferior» y «Valvula inferior» para el mismo punto: después no se
 * puede filtrar, ni agrupar, ni comparar dos informes.
 *
 * Las cuatro van juntas y no en cuatro módulos porque tienen la misma forma
 * —nombre, activo, orden— y se corrigen en la misma sesión.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ DAR DE BAJA NO TOCA NINGÚN INFORME                                       │
 * └──────────────────────────────────────────────────────────────────────────┘
 * Lo que se guarda en la muestra es el TEXTO, no el id. Desactivar una fila la
 * saca del desplegable de las muestras nuevas y deja intacto todo lo emitido,
 * que es lo que corresponde: un informe firmado no cambia porque alguien
 * ordenó el catálogo tres años después.
 */
import { computed, ref } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import {
    Alert, Button, Card, Empty, Input, InputNumber, Popconfirm, Space, Switch,
    Table, Tabs, TabPane, Tag, Tooltip,
} from 'ant-design-vue';
import {
    UnorderedListOutlined, PlusOutlined, DeleteOutlined, SaveOutlined,
} from '@ant-design/icons-vue';

import AppLayout from '@/Layouts/AppLayout.vue';
import SectionHeader from '@/Components/Common/SectionHeader.vue';
import { useI18n } from '@/Plugins/i18n';

defineOptions({ layout: AppLayout });

const props = defineProps({
    kinds: { type: Array,  default: () => [] },
    items: { type: Object, default: () => ({}) },
    tab:   { type: String, default: '' },
});

const { t } = useI18n();
const page = usePage();

const activa = ref(props.tab || props.kinds[0]);

const errores = computed(() => Object.values(page.props.errors ?? {}).filter(Boolean));

/** El alta: una fila en blanco al pie de la solapa activa. */
const nueva = ref({ name: '', sort_order: null });
const guardandoNueva = ref(false);

const agregar = () => {
    if (!nueva.value.name.trim()) return;

    guardandoNueva.value = true;
    router.post(
        route('lab_management.report_catalogs.store'),
        {
            kind: activa.value,
            name: nueva.value.name.trim(),
            sort_order: nueva.value.sort_order ?? 0,
            is_active: true,
        },
        {
            preserveScroll: true,
            onSuccess: () => { nueva.value = { name: '', sort_order: null }; },
            onFinish:  () => { guardandoNueva.value = false; },
        },
    );
};

/**
 * El borrador de la fila que se está editando.
 *
 * Una sola por vez: son listas de seis filas y editar en línea sin guardar es
 * justo lo que deja a alguien creyendo que guardó.
 */
const editando = ref(null);
const borrador = ref({ name: '', sort_order: 0, is_active: true });

const abrirEdicion = (fila) => {
    editando.value = fila.slug;
    borrador.value = {
        name: fila.name,
        sort_order: fila.sort_order,
        is_active: fila.is_active,
    };
};

const cancelar = () => { editando.value = null; };

const guardar = (fila) => {
    router.put(
        route('lab_management.report_catalogs.update', fila.slug),
        { ...borrador.value },
        {
            preserveScroll: true,
            onSuccess: () => { editando.value = null; },
        },
    );
};

/** El interruptor de la lista: activa o desactiva sin abrir la edición. */
const alternar = (fila, valor) => {
    router.put(
        route('lab_management.report_catalogs.update', fila.slug),
        { name: fila.name, sort_order: fila.sort_order, is_active: valor },
        { preserveScroll: true },
    );
};

const borrar = (fila) => {
    router.delete(
        route('lab_management.report_catalogs.destroy', fila.slug),
        { preserveScroll: true },
    );
};

const columnas = computed(() => [
    { title: t('report_catalogs.name'),   key: 'name',   dataIndex: 'name' },
    { title: t('report_catalogs.order'),  key: 'order',  width: 110, align: 'center' },
    { title: t('report_catalogs.active'), key: 'active', width: 110, align: 'center' },
    { title: '',                          key: 'acts',   width: 190, align: 'right' },
]);
</script>

<template>
    <Head :title="$t('report_catalogs.title')" />

    <div class="form-page sap-form">
        <SectionHeader
            :title="$t('report_catalogs.title')"
            :subtitle="$t('report_catalogs.intro')"
        >
            <template #icon><UnorderedListOutlined /></template>
        </SectionHeader>

        <div class="form-body">
            <Alert
                v-for="(error, i) in errores"
                :key="i"
                type="error"
                show-icon
                class="rc-alert"
                :message="error"
            />

            <Alert
                type="info"
                show-icon
                class="rc-alert"
                :message="$t('report_catalogs.frozen_note')"
            />

            <Card :bordered="false">
                <Tabs v-model:activeKey="activa">
                    <TabPane v-for="kind in kinds" :key="kind">
                        <template #tab>
                            {{ $t(`report_catalogs.kind.${kind}`) }}
                            <Tag class="rc-count">{{ (items[kind] ?? []).length }}</Tag>
                        </template>

                        <p class="rc-hint">{{ $t(`report_catalogs.hint.${kind}`) }}</p>

                        <Table
                            v-if="(items[kind] ?? []).length > 0"
                            :columns="columnas"
                            :data-source="items[kind]"
                            :pagination="false"
                            row-key="slug"
                            size="small"
                        >
                            <template #bodyCell="{ column, record }">
                                <template v-if="column.key === 'name'">
                                    <Input
                                        v-if="editando === record.slug"
                                        v-model:value="borrador.name"
                                        :maxlength="120"
                                        @press-enter="guardar(record)"
                                    />
                                    <span v-else :class="{ 'rc-off': !record.is_active }">{{ record.name }}</span>
                                </template>

                                <template v-else-if="column.key === 'order'">
                                    <InputNumber
                                        v-if="editando === record.slug"
                                        v-model:value="borrador.sort_order"
                                        :min="0"
                                        :max="9999"
                                        size="small"
                                        style="width:80px"
                                    />
                                    <span v-else class="rc-muted">{{ record.sort_order }}</span>
                                </template>

                                <template v-else-if="column.key === 'active'">
                                    <!-- Desactivar la saca del desplegable y no
                                         toca ni un informe emitido: lo que se
                                         guardó en la muestra es el texto. -->
                                    <Tooltip :title="$t('report_catalogs.active_help')">
                                        <Switch
                                            :checked="editando === record.slug ? borrador.is_active : record.is_active"
                                            size="small"
                                            @change="(v) => editando === record.slug ? (borrador.is_active = v) : alternar(record, v)"
                                        />
                                    </Tooltip>
                                </template>

                                <template v-else-if="column.key === 'acts'">
                                    <Space v-if="editando === record.slug" :size="6">
                                        <Button type="primary" size="small" @click="guardar(record)">
                                            <template #icon><SaveOutlined /></template>
                                            {{ $t('global.save') }}
                                        </Button>
                                        <Button size="small" @click="cancelar">{{ $t('global.cancel') }}</Button>
                                    </Space>
                                    <Space v-else :size="6">
                                        <Button size="small" @click="abrirEdicion(record)">
                                            {{ $t('global.edit') }}
                                        </Button>
                                        <Popconfirm
                                            :title="$t('report_catalogs.delete_confirm')"
                                            :ok-text="$t('global.delete')"
                                            :cancel-text="$t('global.cancel')"
                                            @confirm="borrar(record)"
                                        >
                                            <Button size="small" danger>
                                                <template #icon><DeleteOutlined /></template>
                                            </Button>
                                        </Popconfirm>
                                    </Space>
                                </template>
                            </template>
                        </Table>

                        <Empty v-else :description="$t('report_catalogs.empty')" />

                        <!-- El alta al pie y no en un modal: se cargan varias
                             seguidas y abrir una ventana por cada una es el
                             camino largo para escribir seis palabras. -->
                        <div class="rc-new">
                            <Input
                                v-model:value="nueva.name"
                                :maxlength="120"
                                :placeholder="$t('report_catalogs.new_placeholder')"
                                @press-enter="agregar"
                            />
                            <InputNumber
                                v-model:value="nueva.sort_order"
                                :min="0"
                                :max="9999"
                                :placeholder="$t('report_catalogs.order')"
                                style="width:110px"
                            />
                            <Button
                                type="primary"
                                :loading="guardandoNueva"
                                :disabled="!nueva.name.trim()"
                                @click="agregar"
                            >
                                <template #icon><PlusOutlined /></template>
                                {{ $t('report_catalogs.add') }}
                            </Button>
                        </div>
                    </TabPane>
                </Tabs>
            </Card>
        </div>
    </div>
</template>

<style scoped>
.rc-alert { margin-bottom: 12px; }
.rc-hint { font-size: 0.8125rem; color: var(--color-text-muted); margin: 0 0 12px; }
.rc-count { margin-left: 6px; }
.rc-muted { color: var(--color-text-muted); }
/* La fila desactivada se ve, pero se ve apagada: sigue existiendo en los
   informes viejos y esconderla haría creer que se borró. */
.rc-off { color: var(--color-text-muted); text-decoration: line-through; }

.rc-new {
    display: flex; gap: 8px; align-items: center;
    margin-top: 14px; padding-top: 12px;
    border-top: 1px solid var(--color-border);
}
.rc-new :deep(.ant-input) { flex: 1 1 auto; }

@media (max-width: 640px) {
    .rc-new { flex-wrap: wrap; }
    .rc-new :deep(.ant-input) { flex: 1 1 100%; }
}
</style>
