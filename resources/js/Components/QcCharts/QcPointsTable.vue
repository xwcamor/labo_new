<script setup>
/**
 * Las mediciones del patrón, en la misma tabla que alimenta el gráfico.
 *
 * Los puntos excluidos NO se filtran: se atenúan. Una carta de la que se
 * borraron los puntos incómodos queda impecable y no prueba nada; el valor de
 * la evidencia está en mostrar que el laboratorio detectó el desvío y qué hizo.
 */
import { computed } from 'vue';
import { Button, Tag, Tooltip } from 'ant-design-vue';
import { StopOutlined, UndoOutlined } from '@ant-design/icons-vue';

import ResponsiveTable from '@/Components/Common/ResponsiveTable.vue';
import { useI18n } from '@/Plugins/i18n';
import { useDateFormat } from '@/Composables/useDateFormat';
import { fmtNumber } from '@/Components/QcCharts/limits';

const props = defineProps({
    points:  { type: Array, default: () => [] },
    /** Regla de Westgard => 'out' | 'warn'. */
    rules:   { type: Object, default: () => ({}) },
    unit:    { type: String, default: '' },
    canEdit: { type: Boolean, default: false },
});

defineEmits(['toggle-exclusion']);

const { t } = useI18n();
const { formatDateTime } = useDateFormat();

// Mismo criterio que el gráfico: lo que no es número finito se muestra como
// hueco, nunca como "NaN".
const fmt = (raw) => fmtNumber(raw);

const flagColor = (flag) => (flag === 'out' ? 'error' : flag === 'warn' ? 'warning' : 'success');

const columns = computed(() => {
    const cols = [
        { title: t('qc_charts.measured_at'), key: 'measured_at', dataIndex: 'measured_at', width: 170, mobile: { role: 'title' } },
        { title: t('qc_charts.value'), key: 'value', dataIndex: 'value', width: 130, mobile: { role: 'subtitle' } },
        { title: t('qc_charts.z_score'), key: 'z_score', dataIndex: 'z_score', width: 130, mobile: { role: 'meta' } },
        { title: t('qc_charts.flag'), key: 'flag', dataIndex: 'flag', width: 130, mobile: { role: 'status' } },
        { title: t('qc_charts.westgard'), key: 'westgard_rule', dataIndex: 'westgard_rule', width: 300, mobile: { role: 'meta' } },
        { title: t('qc_charts.exclusion_reason'), key: 'exclusion_reason', dataIndex: 'exclusion_reason', width: 240, mobile: { role: 'meta' } },
    ];

    if (props.canEdit) {
        cols.push({ title: t('global.actions'), key: 'actions', width: 120, align: 'right', mobile: { role: 'actions' } });
    }

    return cols;
});

/** Fila atenuada para el punto que ya no pesa en el cálculo. */
const rowClassName = (record) => (record.is_excluded ? 'qcp-row--excluded' : '');
</script>

<template>
    <ResponsiveTable
        :columns="columns"
        :data-source="points"
        :pagination="false"
        :scroll="{ x: 'max-content' }"
        view="table"
        row-key="id"
        :row-class-name="rowClassName"
    >
        <template #empty>
            <p class="qcp-empty">{{ $t('qc_charts.empty_points') }}</p>
        </template>

        <template #bodyCell="{ column, record }">
            <template v-if="column.key === 'measured_at'">
                {{ formatDateTime(record.measured_at) }}
            </template>

            <template v-else-if="column.key === 'value'">
                <span class="qcp-value">{{ fmt(record.value) }}</span>
                <span v-if="unit" class="qcp-unit"> {{ unit }}</span>
            </template>

            <template v-else-if="column.key === 'z_score'">
                {{ fmt(record.z_score) }}
            </template>

            <template v-else-if="column.key === 'flag'">
                <Tag :color="flagColor(record.flag)" :bordered="false">
                    {{ $t(`qc_charts.flags.${record.flag ?? 'ok'}`) }}
                </Tag>
            </template>

            <template v-else-if="column.key === 'westgard_rule'">
                <Tooltip v-if="record.westgard_rule" :title="$t(`qc_charts.westgard_meaning.${record.westgard_rule}`)">
                    <Tag :color="flagColor(rules[record.westgard_rule])" :bordered="false">
                        {{ $t(`qc_charts.westgard_rules.${record.westgard_rule}`) }}
                    </Tag>
                </Tooltip>
                <span v-else class="qcp-muted">—</span>
            </template>

            <template v-else-if="column.key === 'exclusion_reason'">
                <span v-if="record.is_excluded" class="qcp-reason">
                    {{ record.exclusion_reason || $t('global.no_reason') }}
                </span>
                <span v-else class="qcp-muted">—</span>
            </template>

            <template v-else-if="column.key === 'actions'">
                <Tooltip :title="record.is_excluded ? $t('global.restore_hint') : $t('qc_charts.exclude')">
                    <Button size="small" @click="$emit('toggle-exclusion', record)">
                        <UndoOutlined v-if="record.is_excluded" />
                        <StopOutlined v-else />
                    </Button>
                </Tooltip>
            </template>
        </template>
    </ResponsiveTable>
</template>

<style scoped>
.qcp-value { font-variant-numeric: tabular-nums; font-weight: 600; }
.qcp-unit  { color: var(--color-text-muted); font-size: 0.8125rem; }
.qcp-muted { color: var(--color-text-muted); }
.qcp-reason { font-size: 0.8125rem; color: var(--color-text-muted); }
.qcp-empty {
    margin: 0;
    padding: 32px 16px;
    text-align: center;
    color: var(--color-text-muted);
    font-size: 0.875rem;
}
</style>

<style>
/* Sin scope: la clase la pone Ant Design en el <tr>, fuera del alcance del
   scoped de este componente. */
.qcp-row--excluded > td {
    opacity: 0.6;
    font-style: italic;
}
.qcp-row--excluded > td:first-child {
    box-shadow: inset 3px 0 0 var(--color-text-dim);
}
</style>
