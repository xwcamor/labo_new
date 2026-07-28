<script setup>
/**
 * Los duplicados: la misma muestra medida dos veces, contra el criterio de
 * repetibilidad de la carta.
 *
 * `within_limit` en nulo NO es "todavía no se evaluó": es que la carta no
 * declara criterio, y sin criterio no hay nada que dictaminar. Por eso ahí se
 * informa la diferencia y se dice que no hay criterio, en vez de pintar un
 * tilde o una cruz que serían evidencia inventada.
 */
import { computed } from 'vue';
import { Tag, Tooltip } from 'ant-design-vue';

import ResponsiveTable from '@/Components/Common/ResponsiveTable.vue';
import { useI18n } from '@/Plugins/i18n';
import { useDateFormat } from '@/Composables/useDateFormat';
import { fmtNumber, toNumber } from '@/Components/QcCharts/limits';

defineProps({
    duplicates: { type: Array, default: () => [] },
    unit:       { type: String, default: '' },
});

const { t } = useI18n();
const { formatDateTime } = useDateFormat();

const fmt = (raw) => fmtNumber(raw);

const fmtPercent = (raw) => {
    const n = toNumber(raw);
    return n === null ? '—' : `${parseFloat(n.toFixed(4))} %`;
};

const columns = computed(() => [
    { title: t('qc_charts.measured_at'), key: 'measured_at', dataIndex: 'measured_at', width: 170, mobile: { role: 'title' } },
    { title: `${t('qc_charts.value')} A`, key: 'value_a', dataIndex: 'value_a', width: 130, mobile: { role: 'meta' } },
    { title: `${t('qc_charts.value')} B`, key: 'value_b', dataIndex: 'value_b', width: 130, mobile: { role: 'meta' } },
    { title: t('qc_charts.difference'), key: 'difference', dataIndex: 'difference', width: 140, mobile: { role: 'meta' } },
    { title: t('qc_charts.relative_difference'), key: 'relative_difference', dataIndex: 'relative_difference', width: 170, mobile: { role: 'meta' } },
    { title: t('qc_charts.within_limit'), key: 'within_limit', dataIndex: 'within_limit', width: 220, mobile: { role: 'status' } },
]);
</script>

<template>
    <ResponsiveTable
        :columns="columns"
        :data-source="duplicates"
        :pagination="false"
        :scroll="{ x: 'max-content' }"
        view="table"
        row-key="id"
    >
        <template #empty>
            <p class="qcd-empty">{{ $t('global.no_records') }}</p>
        </template>

        <template #bodyCell="{ column, record }">
            <template v-if="column.key === 'measured_at'">
                {{ formatDateTime(record.measured_at) }}
            </template>

            <template v-else-if="column.key === 'value_a' || column.key === 'value_b' || column.key === 'difference'">
                <span class="qcd-num">{{ fmt(record[column.dataIndex]) }}</span>
                <span v-if="unit" class="qcd-unit"> {{ unit }}</span>
            </template>

            <template v-else-if="column.key === 'relative_difference'">
                <span class="qcd-num">{{ fmtPercent(record.relative_difference) }}</span>
            </template>

            <template v-else-if="column.key === 'within_limit'">
                <Tag v-if="record.within_limit === true" color="success" :bordered="false">
                    {{ $t('global.yes') }}
                </Tag>
                <Tag v-else-if="record.within_limit === false" color="error" :bordered="false">
                    {{ $t('global.no') }}
                </Tag>
                <Tooltip v-else :title="$t('qc_charts.no_criterion')">
                    <span class="qcd-nocriterion">{{ $t('qc_charts.no_criterion') }}</span>
                </Tooltip>
            </template>
        </template>
    </ResponsiveTable>
</template>

<style scoped>
.qcd-num  { font-variant-numeric: tabular-nums; }
.qcd-unit { color: var(--color-text-muted); font-size: 0.8125rem; }
.qcd-nocriterion {
    font-size: 0.8125rem;
    color: var(--color-text-muted);
    display: inline-block;
    max-width: 260px;
    white-space: normal;
    line-height: 1.4;
}
.qcd-empty {
    margin: 0;
    padding: 32px 16px;
    text-align: center;
    color: var(--color-text-muted);
    font-size: 0.875rem;
}
</style>
