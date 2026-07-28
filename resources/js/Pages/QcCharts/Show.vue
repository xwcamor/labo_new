<script setup>
/**
 * La ficha de una carta de control ES el gráfico.
 *
 * Lo que agrega respecto del sistema viejo: allá se dibujaban las cinco líneas
 * y ahí terminaba —el analista tenía que darse cuenta mirando si el patrón se
 * había salido de control—. Aquí cada punto trae su veredicto y, cuando se
 * violó una regla de Westgard, cuál y qué significa.
 *
 * El veredicto NO se recalcula en el navegador: viene guardado con el punto,
 * congelado contra los límites que regían el día de la medición.
 */
import { computed, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { Button, Card, Tag, Tooltip } from 'ant-design-vue';
import { EditOutlined, LineChartOutlined } from '@ant-design/icons-vue';

import AppLayout from '@/Layouts/AppLayout.vue';
import SectionHeader from '@/Components/Common/SectionHeader.vue';
import LeveyJenningsChart from '@/Components/QcCharts/LeveyJenningsChart.vue';
import QcPointsTable from '@/Components/QcCharts/QcPointsTable.vue';
import QcDuplicatesTable from '@/Components/QcCharts/QcDuplicatesTable.vue';
import QcPointExcludeModal from '@/Components/QcCharts/QcPointExcludeModal.vue';
import { LIMIT_KEYS, fmtNumber } from '@/Components/QcCharts/limits';

import { useAuth } from '@/Composables/useAuth';
import { useDateFormat } from '@/Composables/useDateFormat';
import { useI18n } from '@/Plugins/i18n';

defineOptions({ layout: AppLayout });

const props = defineProps({
    chart:      { type: Object, required: true },
    limits:     { type: Object, default: () => ({}) },
    points:     { type: Array,  default: () => [] },
    rules:      { type: Object, default: () => ({}) },
    duplicates: { type: Array,  default: () => [] },
});

const { t } = useI18n();
const { can } = useAuth();
const { formatDate } = useDateFormat();

const canEdit = computed(() => can('qc_charts.edit'));

/** La unidad sale de la columna controlada, del parámetro o de la prueba. */
const unit = computed(() =>
    props.chart.field?.unit
    || props.chart.analyte?.unit
    || props.chart.definition?.chart_unit
    || '');

const controlled = computed(() =>
    props.chart.field?.label
    || props.chart.analyte?.name
    || props.chart.label
    || '');

const validity = computed(() => {
    const from = props.chart.effective_from ? formatDate(props.chart.effective_from) : null;
    const to = props.chart.effective_to ? formatDate(props.chart.effective_to) : null;

    if (!from && !to) return '—';
    if (from && to) return `${t('global.from')} ${from} ${t('global.to')} ${to}`;
    if (from) return `${t('global.from')} ${from}`;
    return `${t('global.to')} ${to}`;
});

/** Reparto del veredicto de los puntos que SÍ cuentan, para la cabecera. */
const tally = computed(() => {
    const counts = { ok: 0, warn: 0, out: 0, excluded: 0 };

    props.points.forEach((p) => {
        if (p.is_excluded) { counts.excluded += 1; return; }
        const flag = p.flag ?? 'ok';
        if (flag in counts) counts[flag] += 1;
    });

    return counts;
});

// ─── Exclusión de un punto ───────────────────────────────────────────────
const excludeOpen = ref(false);
const excludeTarget = ref(null);

const openExclusion = (point) => {
    excludeTarget.value = point;
    excludeOpen.value = true;
};
</script>

<template>
    <Head :title="$t('qc_charts.show')" />

    <div class="show-page sap-show">
        <SectionHeader
            :back-href="route('lab_management.qc_charts.index')"
            :title="chart.label || chart.definition?.name || $t('qc_charts.singular')"
        >
            <template #icon><LineChartOutlined /></template>
            <template #subtitle>
                <p class="qcs-subtitle">
                    {{ chart.definition?.name }}
                    <template v-if="controlled"> · {{ controlled }}</template>
                    <template v-if="unit"> ({{ unit }})</template>
                </p>
            </template>
            <template #actions>
                <Tooltip v-if="canEdit" :title="$t('global.edit_hint')">
                    <Link :href="route('lab_management.qc_charts.edit', chart.slug)">
                        <Button><EditOutlined /> {{ $t('global.edit') }}</Button>
                    </Link>
                </Tooltip>
            </template>
        </SectionHeader>

        <!-- Ficha de la carta: lote, vigencia, límites y reparto de veredictos. -->
        <Card :bodyStyle="{ padding: 18 }" class="qcs-card">
            <div class="spec-grid qcs-specs">
                <div class="spec-cell">
                    <span class="spec-cell__label">{{ $t('qc_charts.control_lot') }}</span>
                    <span class="spec-cell__value">{{ chart.control_lot || '—' }}</span>
                </div>
                <div class="spec-cell">
                    <span class="spec-cell__label">{{ $t('qc_charts.effective_from') }}</span>
                    <span class="spec-cell__value">{{ validity }}</span>
                </div>
                <div class="spec-cell">
                    <span class="spec-cell__label">{{ $t('qc_charts.repeatability_limit') }}</span>
                    <span class="spec-cell__value">
                        {{ fmtNumber(chart.repeatability_limit) }}
                        <span v-if="chart.repeatability_limit !== null" class="qcs-dim">
                            · {{ $t(`qc_charts.mode.${chart.repeatability_mode ?? 'absolute'}`) }}
                        </span>
                    </span>
                </div>
                <div class="spec-cell">
                    <span class="spec-cell__label">{{ $t('qc_charts.is_active') }}</span>
                    <span class="spec-cell__value">
                        <Tag :color="chart.is_active ? 'success' : 'default'" :bordered="false">
                            {{ chart.is_active ? $t('global.active') : $t('global.inactive') }}
                        </Tag>
                    </span>
                </div>
            </div>

            <div class="qcs-limits">
                <span v-for="key in LIMIT_KEYS" :key="key" class="qcs-limit" :class="`qcs-limit--${key}`">
                    <span class="qcs-limit__tag">{{ $t(`qc_charts.limits.${key}`) }}</span>
                    <span class="qcs-limit__val">{{ fmtNumber(limits[key]) }}</span>
                </span>
            </div>

            <p v-if="chart.comment" class="qcs-comment">{{ chart.comment }}</p>
        </Card>

        <!-- ── La carta ────────────────────────────────────────────────── -->
        <Card :bodyStyle="{ padding: 18 }" class="qcs-card">
            <template #title><LineChartOutlined /> {{ $t('qc_charts.show') }}</template>
            <template #extra>
                <span class="qcs-tally">
                    <span class="qcs-tally__item">{{ $t('qc_charts.flags.ok') }}: <b>{{ tally.ok }}</b></span>
                    <span class="qcs-tally__item">{{ $t('qc_charts.flags.warn') }}: <b>{{ tally.warn }}</b></span>
                    <span class="qcs-tally__item">{{ $t('qc_charts.flags.out') }}: <b>{{ tally.out }}</b></span>
                    <span class="qcs-tally__item">{{ $t('qc_charts.exclude') }}: <b>{{ tally.excluded }}</b></span>
                </span>
            </template>

            <LeveyJenningsChart :points="points" :limits="limits" :rules="rules" :unit="unit" />
        </Card>

        <!-- ── Las mediciones ──────────────────────────────────────────── -->
        <Card :bodyStyle="{ padding: 0 }" class="qcs-card">
            <template #title>{{ $t('qc_charts.points') }}</template>
            <QcPointsTable
                :points="points"
                :rules="rules"
                :unit="unit"
                :can-edit="canEdit"
                @toggle-exclusion="openExclusion"
            />
        </Card>

        <!-- ── Los duplicados ──────────────────────────────────────────── -->
        <Card :bodyStyle="{ padding: 0 }" class="qcs-card">
            <template #title>{{ $t('qc_charts.duplicates') }}</template>
            <QcDuplicatesTable :duplicates="duplicates" :unit="unit" />
        </Card>

        <QcPointExcludeModal
            v-model:open="excludeOpen"
            :chart-slug="chart.slug"
            :point="excludeTarget"
        />
    </div>
</template>

<style scoped>
.qcs-card { margin-bottom: 16px; border-radius: 8px; }
.qcs-subtitle { margin: 2px 0 0; font-size: 0.8125rem; color: var(--color-text-muted); }
.qcs-dim { color: var(--color-text-muted); font-size: 0.8125rem; }
.qcs-comment {
    margin: 14px 0 0;
    padding-top: 12px;
    border-top: 1px solid var(--color-border);
    font-size: 0.875rem;
    color: var(--color-text-muted);
    white-space: pre-wrap;
}

.qcs-specs { margin-bottom: 14px; }

/* Los cinco límites con su nombre completo: es la lectura que el analista
   necesita al lado del gráfico. */
.qcs-limits {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}
.qcs-limit {
    display: flex;
    flex-direction: column;
    gap: 2px;
    padding: 8px 12px;
    border-radius: 8px;
    border: 1px solid var(--color-border);
    background: var(--color-surface-alt);
    min-width: 160px;
    flex: 1 1 160px;
}
.qcs-limit__tag { font-size: 0.7rem; color: var(--color-text-muted); }
.qcs-limit__val {
    font-size: 1rem;
    font-weight: 600;
    font-variant-numeric: tabular-nums;
    color: var(--color-text);
}
.qcs-limit--lcs, .qcs-limit--lci { border-left: 3px solid var(--color-danger-bright); }
.qcs-limit--las, .qcs-limit--lai { border-left: 3px solid var(--color-warning); }
.qcs-limit--lc                   { border-left: 3px solid var(--color-primary); }

.qcs-tally {
    display: inline-flex;
    flex-wrap: wrap;
    gap: 14px;
    font-size: 0.78rem;
    color: var(--color-text-muted);
}
.qcs-tally__item b { color: var(--color-text); }

@media (max-width: 768px) {
    .qcs-limit { min-width: 0; flex: 1 1 130px; }
    .qcs-tally { gap: 8px; }
}
</style>
