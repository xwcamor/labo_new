<script setup>
/**
 * Editor de columnas de una prueba: la hoja de trabajo que ve el analista.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ REORDENAR ES SEGURO — Y ESO ES LO NUEVO                                  │
 * └──────────────────────────────────────────────────────────────────────────┘
 * En el sistema Rails viejo reordenar era la operación peligrosa: las fórmulas
 * referenciaban las columnas por su posición (`col1`, `col8`), el código de la
 * muestra se copiaba de la primera, la norma se leía de la segunda y el
 * resultado se tomaba de la última. Su README avisaba en mayúsculas que la
 * columna resultado tenía que ser SIEMPRE la última. Aquí las fórmulas usan
 * códigos y los roles están declarados, así que el orden es presentación.
 */
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Alert, Button, Modal, Tag, Tooltip } from 'ant-design-vue';
import {
    DeleteOutlined, DownOutlined, EditOutlined, HolderOutlined, PlusOutlined,
    SettingOutlined, FileDoneOutlined, UpOutlined,
} from '@ant-design/icons-vue';

import AppLayout from '@/Layouts/AppLayout.vue';
import SectionHeader from '@/Components/Common/SectionHeader.vue';
import TestFieldFormModal from '@/Components/TestFields/TestFieldFormModal.vue';

import { useAuth } from '@/Composables/useAuth';
import { useI18n } from '@/Plugins/i18n';

defineOptions({ layout: AppLayout });

const props = defineProps({
    definition: { type: Object, required: true },
    fields:     { type: Array,  default: () => [] },
    fieldTypes: { type: Object, default: () => ({}) },
    roles:      { type: Array,  default: () => [] },
    analytes:   { type: Array,  default: () => [] },
});

const { t } = useI18n();
const { can } = useAuth();

const canEdit = computed(() => can('test_definitions.edit'));

// ─── Orden local ─────────────────────────────────────────────────────────
// El orden se manipula en una copia y se confirma con un POST explícito. Sin
// eso, cada movimiento sería un viaje al servidor y una lista que salta.
const order = ref(props.fields.map((f) => f.id));
const savingOrder = ref(false);

const fieldById = computed(() => Object.fromEntries(props.fields.map((f) => [f.id, f])));

const orderedFields = computed(() => order.value.map((id) => fieldById.value[id]).filter(Boolean));

const orderChanged = computed(() =>
    order.value.some((id, i) => props.fields[i]?.id !== id));

const move = (index, delta) => {
    const target = index + delta;
    if (target < 0 || target >= order.value.length) return;

    const next = [...order.value];
    [next[index], next[target]] = [next[target], next[index]];
    order.value = next;
};

const resetOrder = () => { order.value = props.fields.map((f) => f.id); };

const saveOrder = () => {
    savingOrder.value = true;
    router.post(
        route('lab_management.test_definitions.fields.reorder', props.definition.slug),
        { order: order.value },
        {
            preserveScroll: true,
            onFinish: () => { savingOrder.value = false; },
        },
    );
};

// ─── Arrastrar (HTML5 nativo, sin dependencia) ───────────────────────────
// Las flechas hacen lo mismo: en pantalla táctil el arrastre nativo no existe,
// y dejar el reordenamiento solo al mouse sería dejarlo fuera de la tablet de
// bancada.
const draggingId = ref(null);

const onDragStart = (event, id) => {
    if (!canEdit.value) { event.preventDefault(); return; }
    draggingId.value = id;
    event.dataTransfer.effectAllowed = 'move';
    try { event.dataTransfer.setData('text/plain', String(id)); } catch (_) { /* Firefox */ }
};

const onDragOver = (event, id) => {
    event.preventDefault();
    if (draggingId.value === null || draggingId.value === id) return;

    const next = [...order.value];
    const from = next.indexOf(draggingId.value);
    const to = next.indexOf(id);
    if (from === -1 || to === -1 || from === to) return;

    next.splice(from, 1);
    next.splice(to, 0, draggingId.value);
    order.value = next;
};

const onDragEnd = () => { draggingId.value = null; };

// ─── Alta / edición ──────────────────────────────────────────────────────
const modalOpen = ref(false);
const editing = ref(null);

const openCreate = () => { editing.value = null; modalOpen.value = true; };
const openEdit = (field) => { editing.value = field; modalOpen.value = true; };

const nextOrder = computed(() => props.fields.length + 1);

/**
 * La baja se rechaza del lado del servidor si alguna fórmula usa la columna.
 * Aquí solo se confirma: el mensaje real, con los nombres de las fórmulas que
 * la referencian, lo devuelve el backend.
 */
const confirmDelete = (field) => {
    Modal.confirm({
        title: t('global.delete_confirm_title'),
        content: `${field.label} (${field.code})`,
        okText: t('global.delete'),
        okType: 'danger',
        cancelText: t('global.cancel'),
        onOk: () => router.delete(
            route('lab_management.test_definitions.fields.destroy', [props.definition.slug, field.id]),
            { preserveScroll: true },
        ),
    });
};

const analyteName = (id) => props.analytes.find((a) => a.id === id)?.name ?? null;
</script>

<template>
    <Head :title="`${definition.name} — ${$t('test_fields.title')}`" />

    <div class="form-page sap-form">
        <SectionHeader
            :back-href="route('lab_management.test_definitions.show', definition.slug)"
            :title="$t('test_fields.title')"
            :subtitle="definition.name"
        >
            <template #icon><FileDoneOutlined /></template>
            <template #actions>
                <Link :href="route('lab_management.test_definitions.constants.index', definition.slug)">
                    <Button><SettingOutlined /> {{ $t('test_fields.constants') }}</Button>
                </Link>
                <Button v-if="canEdit" type="primary" class="tfp-new" @click="openCreate">
                    <PlusOutlined /> {{ $t('test_fields.create') }}
                </Button>
            </template>
        </SectionHeader>

        <div class="form-body">
            <Alert type="info" show-icon :message="$t('test_fields.intro')" class="tfp-alert" />
            <Alert type="success" show-icon :message="$t('test_fields.reorder_safe')" class="tfp-alert" />

            <!-- Contenedor propio y no una Card de Ant Design: dentro de
                 `.sap-form` las Cards quedan transparentes y sin borde, y esta
                 tabla necesita su marco y su propio scroll horizontal. -->
            <div class="tfp-card">
                <div v-if="orderedFields.length === 0" class="tfp-empty">
                    {{ $t('test_fields.empty') }}
                </div>

                <div v-else class="tfp-scroll">
                    <table class="tfp-table">
                        <thead>
                            <tr>
                                <th class="tfp-th-order">{{ $t('test_fields.sort_order') }}</th>
                                <th>{{ $t('test_fields.label') }}</th>
                                <th>{{ $t('test_fields.code') }}</th>
                                <th>{{ $t('test_fields.type') }}</th>
                                <th>{{ $t('test_fields.role') }}</th>
                                <th>{{ $t('test_fields.unit') }}</th>
                                <th>{{ $t('test_fields.is_required') }}</th>
                                <th>{{ $t('test_fields.is_reusable') }}</th>
                                <th>{{ $t('test_fields.report_visible') }}</th>
                                <th>{{ $t('test_fields.replicates') }}</th>
                                <th v-if="canEdit" class="tfp-th-actions">{{ $t('global.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="(field, index) in orderedFields"
                                :key="field.id"
                                :class="{ 'tfp-row--dragging': draggingId === field.id }"
                                :draggable="canEdit"
                                @dragstart="onDragStart($event, field.id)"
                                @dragover="onDragOver($event, field.id)"
                                @dragend="onDragEnd"
                                @drop.prevent="onDragEnd"
                            >
                                <td class="tfp-order">
                                    <HolderOutlined v-if="canEdit" class="tfp-handle" />
                                    <span class="tfp-pos">{{ index + 1 }}</span>
                                    <span v-if="canEdit" class="tfp-moves">
                                        <Button size="small" :disabled="index === 0" @click="move(index, -1)">
                                            <UpOutlined />
                                        </Button>
                                        <Button size="small" :disabled="index === orderedFields.length - 1" @click="move(index, 1)">
                                            <DownOutlined />
                                        </Button>
                                    </span>
                                </td>

                                <td>
                                    <span class="tfp-label">{{ field.label }}</span>
                                    <div v-if="field.formula" class="tfp-formula">{{ field.formula }}</div>
                                </td>

                                <td><code class="tfp-code">{{ field.code }}</code></td>

                                <td>
                                    <Tooltip :title="$t(`test_fields.types_help.${field.type}`)">
                                        <Tag :bordered="false">{{ $t(`test_fields.types.${field.type}`) }}</Tag>
                                    </Tooltip>
                                </td>

                                <td>
                                    <Tooltip :title="$t(`test_fields.roles_help.${field.role ?? 'none'}`)">
                                        <Tag
                                            :bordered="false"
                                            :color="(field.role && field.role !== 'none') ? 'blue' : 'default'"
                                        >
                                            {{ $t(`test_fields.roles.${field.role ?? 'none'}`) }}
                                        </Tag>
                                    </Tooltip>
                                    <div v-if="field.output_analyte_id" class="tfp-sub">
                                        {{ analyteName(field.output_analyte_id) }}
                                    </div>
                                </td>

                                <td>{{ field.unit || '—' }}</td>
                                <td>{{ field.is_required ? $t('global.yes') : $t('global.no') }}</td>
                                <td>
                                    <span v-if="field.is_reusable">
                                        {{ $t('global.yes') }}
                                        <span v-if="field.default_value" class="tfp-sub">{{ field.default_value }}</span>
                                    </span>
                                    <span v-else>{{ $t('global.no') }}</span>
                                </td>
                                <td>{{ field.report_visible ? $t('global.yes') : $t('global.no') }}</td>
                                <td>{{ field.replicates ?? 1 }}</td>

                                <td v-if="canEdit" class="tfp-actions">
                                    <Tooltip :title="$t('test_fields.edit')">
                                        <Button size="small" @click="openEdit(field)"><EditOutlined /></Button>
                                    </Tooltip>
                                    <Tooltip :title="$t('global.delete')">
                                        <Button size="small" danger @click="confirmDelete(field)"><DeleteOutlined /></Button>
                                    </Tooltip>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div v-if="canEdit && orderChanged" class="tfp-orderbar">
                <span>{{ $t('test_fields.reorder_safe') }}</span>
                <span class="tfp-orderbar__actions">
                    <Button @click="resetOrder">{{ $t('global.reset_order') }}</Button>
                    <Button type="primary" :loading="savingOrder" @click="saveOrder">
                        {{ $t('global.save_changes') }}
                    </Button>
                </span>
            </div>
        </div>

        <TestFieldFormModal
            v-if="modalOpen"
            :key="editing?.id ?? 'new'"
            v-model:open="modalOpen"
            :definition-slug="definition.slug"
            :field="editing"
            :field-types="fieldTypes"
            :roles="roles"
            :analytes="analytes"
            :next-order="nextOrder"
        />
    </div>
</template>

<style scoped>
.tfp-alert { margin-bottom: 12px; }
.tfp-card  {
    border-radius: 8px;
    border: 1px solid var(--color-border);
    background: var(--color-surface);
    overflow: hidden;
}
.tfp-new   { margin-left: 8px; }

/* La tabla es ancha: scrollea dentro de su propio contenedor para que el cuerpo
   de la página nunca scrollee en horizontal. */
.tfp-scroll { overflow-x: auto; }

.tfp-table {
    width: 100%;
    min-width: 1080px;
    border-collapse: collapse;
    font-size: 0.85rem;
}
.tfp-table th {
    text-align: left;
    padding: 10px 12px;
    font-size: 0.66rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--color-text-muted);
    background: var(--color-surface);
    border-bottom: 1px solid var(--color-border);
    white-space: nowrap;
}
.tfp-table td {
    padding: 10px 12px;
    border-bottom: 1px solid var(--color-border-soft);
    color: var(--color-text);
    vertical-align: top;
}
.tfp-table tbody tr:last-child td { border-bottom: none; }
.tfp-table tbody tr:hover td { background: var(--color-surface-hover); }

.tfp-row--dragging td { opacity: 0.45; }

.tfp-th-order   { width: 150px; }
.tfp-th-actions { width: 110px; }

.tfp-order { display: flex; align-items: center; gap: 8px; white-space: nowrap; }
.tfp-handle { color: var(--color-text-dim); cursor: grab; }
.tfp-pos { font-variant-numeric: tabular-nums; color: var(--color-text-muted); min-width: 14px; }
.tfp-moves { display: inline-flex; gap: 2px; }

.tfp-label { font-weight: 600; }
.tfp-formula {
    margin-top: 3px;
    font-family: ui-monospace, Consolas, monospace;
    font-size: 0.72rem;
    color: var(--color-text-muted);
    max-width: 320px;
    overflow-wrap: anywhere;
}
.tfp-code {
    font-family: ui-monospace, Consolas, monospace;
    font-size: 0.78rem;
    background: var(--color-surface-alt);
    padding: 2px 6px;
    border-radius: 3px;
}
.tfp-sub { display: block; font-size: 0.72rem; color: var(--color-text-muted); }
.tfp-actions { display: flex; gap: 6px; }

.tfp-empty {
    padding: 40px 16px;
    text-align: center;
    color: var(--color-text-muted);
    font-size: 0.875rem;
}

/* Barra de confirmación del orden: el reordenamiento no se guarda solo, para
   que arrastrar sin querer no reescriba la plantilla. */
.tfp-orderbar {
    position: sticky;
    bottom: 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
    flex-wrap: wrap;
    margin-top: 14px;
    padding: 12px 16px;
    border-radius: 8px;
    border: 1px solid var(--color-border);
    background: var(--color-surface);
    font-size: 0.8125rem;
    color: var(--color-text-muted);
}
.tfp-orderbar__actions { display: inline-flex; gap: 8px; }

@media (max-width: 768px) {
    .tfp-orderbar { flex-direction: column; align-items: stretch; }
    .tfp-orderbar__actions { justify-content: flex-end; }
}
</style>
