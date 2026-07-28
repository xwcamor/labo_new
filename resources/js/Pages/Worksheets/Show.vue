<script setup>
/**
 * La hoja de trabajo abierta: la pantalla de bancada.
 *
 * Todo lo que gobierna esta pantalla lo decide el servidor y llega como dato:
 * si se puede escribir (`can.edit`), qué le falta a la hoja para admitir
 * muestras (`missing`) y qué control dibuja cada columna (`fieldTypes`). Acá no
 * se decide nada de eso, y es a propósito: en el sistema viejo las cuatro
 * reglas de la bancada —el cálculo, los obligatorios, el bloqueo y el orden de
 * patrón y duplicado— vivían en el HTML, y un envío directo las salteaba a las
 * cuatro.
 */
import { computed } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import { Alert, Card, Space } from 'ant-design-vue';
import { ExperimentOutlined } from '@ant-design/icons-vue';

import AppLayout from '@/Layouts/AppLayout.vue';
import SectionHeader from '@/Components/Common/SectionHeader.vue';
import WorksheetStatusTag from '@/Components/Worksheets/WorksheetStatusTag.vue';
import WorksheetHeader from '@/Components/Worksheets/WorksheetHeader.vue';
import WorksheetGrid from '@/Components/Worksheets/WorksheetGrid.vue';
import WorksheetActionsBar from '@/Components/Worksheets/WorksheetActionsBar.vue';
import { useI18n } from '@/Plugins/i18n';
import { plainDate } from './config/format';

defineOptions({ layout: AppLayout });

const props = defineProps({
    worksheet:   { type: Object, required: true },
    fields:      { type: Array,  default: () => [] },
    fieldTypes:  { type: Object, default: () => ({}) },
    instruments: { type: Array,  default: () => [] },
    can:         { type: Object, default: () => ({}) },
    missing:     { type: Array,  default: () => [] },
});

const { t } = useI18n();
const page = usePage();

const readonly = computed(() => !props.can.edit);

/**
 * Por qué la grilla está cerrada. Se dice, no se deduce: una pantalla
 * deshabilitada sin explicación es la que hace que el analista llame por
 * teléfono a preguntar si el sistema se rompió.
 */
const readonlyReason = computed(() => {
    if (props.can.edit) return '';
    if (props.worksheet.locked_at) return t('worksheets.errors.locked');
    if (props.worksheet.status !== 'draft') return t('worksheets.errors.not_draft');

    return '';
});

/**
 * Qué le falta a la hoja para admitir muestras. El servidor manda los tipos que
 * faltan como dato y el mensaje se arma acá con sus nombres traducidos: el
 * sistema viejo se limitaba a sacar la opción "Muestra" del select, sin decir
 * por qué faltaba.
 */
const missingMessage = computed(() => (props.missing.length === 0 ? '' : t(
    'worksheets.errors.missing_prerequisites',
    { kinds: props.missing.map((kind) => t(`worksheets.kind.${kind}`)).join(', ') },
)));

/** Lo que rebotó del servidor (obligatorios que faltan, hoja no editable…). */
const serverErrors = computed(() => Object.values(page.props.errors ?? {}).filter(Boolean));

const showActions = computed(
    () => !!(props.can.close || props.can.validate || props.can.void),
);
</script>

<template>
    <Head :title="`${worksheet.definition?.name ?? $t('worksheets.show')} — ${plainDate(worksheet.run_date)}`" />

    <div class="show-page sap-show">
        <SectionHeader
            :back-href="route('lab_management.worksheets.index')"
            :title="worksheet.definition?.name ?? $t('worksheets.show')"
        >
            <template #icon><ExperimentOutlined /></template>
            <template #subtitle>
                <Space :size="8" wrap>
                    <WorksheetStatusTag :status="worksheet.status" />
                    <span class="ws-sub">{{ plainDate(worksheet.run_date) }}</span>
                    <span v-if="worksheet.analyst" class="ws-sub">{{ worksheet.analyst.name }}</span>
                </Space>
            </template>
        </SectionHeader>

        <Alert
            v-for="(error, index) in serverErrors"
            :key="index"
            type="error"
            show-icon
            class="ws-alert"
            :message="error"
        />

        <!-- Prerrequisitos de control de calidad: se explica, no se esconde. -->
        <Alert
            v-if="missingMessage"
            type="warning"
            show-icon
            class="ws-alert"
            :message="missingMessage"
        />

        <Alert
            v-if="readonlyReason"
            type="info"
            show-icon
            class="ws-alert"
            :message="readonlyReason"
        />

        <WorksheetHeader :worksheet="worksheet" />

        <Card :body-style="{ padding: '14px 16px' }">
            <WorksheetGrid
                :worksheet="worksheet"
                :fields="fields"
                :field-types="fieldTypes"
                :instruments="instruments"
                :missing="missing"
                :readonly="readonly"
            />
        </Card>

        <WorksheetActionsBar v-if="showActions" :worksheet="worksheet" :can="can" />
    </div>
</template>

<style scoped>
.ws-alert { margin-bottom: 12px; }
.ws-sub { color: var(--color-text-muted); font-size: 0.8125rem; }
</style>
