<script setup>
/**
 * Carta de Levey-Jennings: la serie del patrón control con sus cinco límites.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ POR QUÉ TANTO CUIDADO CON LOS VALORES                                    │
 * └──────────────────────────────────────────────────────────────────────────┘
 * En el sistema Rails viejo los valores se interpolaban sin escapar dentro del
 * bloque de JavaScript del gráfico. Un valor vacío, o el texto "NaN" que esa
 * base tiene guardado por las fórmulas fallidas, rompía el gráfico ENTERO: no
 * se veía ninguna línea y tampoco ningún error. Aquí cada valor pasa por
 * `toNumber()` antes de entrar a la serie y lo que no es un número finito
 * queda como hueco, que echarts dibuja sin quejarse.
 *
 * Por el mismo motivo el texto del tooltip se escapa: el motivo de exclusión lo
 * escribe el analista y el tooltip de echarts se renderiza como HTML.
 */
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import VChart from 'vue-echarts';
import { use } from 'echarts/core';
import { LineChart, ScatterChart } from 'echarts/charts';
import {
    GridComponent, TooltipComponent, MarkLineComponent, DataZoomComponent,
} from 'echarts/components';
import { CanvasRenderer } from 'echarts/renderers';

import { useI18n } from '@/Plugins/i18n';
import { useDateFormat } from '@/Composables/useDateFormat';
import { fmtNumber, toNumber } from '@/Components/QcCharts/limits';

use([
    LineChart, ScatterChart,
    GridComponent, TooltipComponent, MarkLineComponent, DataZoomComponent,
    CanvasRenderer,
]);

const props = defineProps({
    /** Puntos en orden cronológico, tal como los manda el controlador. */
    points: { type: Array, default: () => [] },
    /** { lci, lai, lc, las, lcs } — cualquiera puede venir en nulo. */
    limits: { type: Object, default: () => ({}) },
    /** Regla de Westgard => 'out' | 'warn'. Decide el color del punto marcado. */
    rules:  { type: Object, default: () => ({}) },
    /** Rótulo del eje vertical (unidad del parámetro). */
    unit:   { type: String, default: '' },
});

const { t } = useI18n();
const { formatDateTime } = useDateFormat();

// ─── Colores ─────────────────────────────────────────────────────────────
// echarts necesita valores concretos, no `var(--...)`, así que se leen del
// elemento raíz del componente. Las variables se declaran en el <style> de
// abajo con su versión oscura, de modo que el gráfico siga el tema sin tener
// ningún hex escrito en el JavaScript.
const root = ref(null);
const themeTick = ref(0);
let observer = null;

onMounted(() => {
    // El tema se cambia poniendo `data-theme` en <html>; sin observarlo el
    // gráfico se quedaría con los colores del tema con el que se montó.
    observer = new MutationObserver(() => { themeTick.value += 1; });
    observer.observe(document.documentElement, {
        attributes: true,
        attributeFilter: ['data-theme', 'data-scheme'],
    });
    themeTick.value += 1;
});

onBeforeUnmount(() => observer?.disconnect());

const palette = computed(() => {
    // themeTick fuerza la relectura al cambiar el tema.
    void themeTick.value;
    const fallback = {
        ok: '#1D7044', warn: '#E9A23B', out: '#C8281D',
        center: '#0A6ED1', line: '#8a9099',
        axis: '#6A6D70', grid: '#e5e7eb',
        surface: '#ffffff', excluded: '#9aa0a6',
    };

    if (!root.value || typeof getComputedStyle === 'undefined') return fallback;

    const styles = getComputedStyle(root.value);
    const read = (name, def) => styles.getPropertyValue(name).trim() || def;

    return {
        ok:       read('--lj-ok', fallback.ok),
        warn:     read('--lj-warn', fallback.warn),
        out:      read('--lj-out', fallback.out),
        center:   read('--lj-center', fallback.center),
        line:     read('--lj-line', fallback.line),
        axis:     read('--lj-axis', fallback.axis),
        grid:     read('--lj-grid', fallback.grid),
        surface:  read('--lj-surface', fallback.surface),
        excluded: read('--lj-excluded', fallback.excluded),
    };
});

// ─── Saneamiento de los datos ────────────────────────────────────────────
// `toNumber` (limits.js) es el filtro: lo que no es un número finito no entra
// a la serie. Es lo que impide que un vacío o un "NaN" de la base vieja se lleve
// puesto el gráfico completo.

function esc(value) {
    return String(value ?? '').replace(/[&<>"']/g, (c) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    }[c]));
}

const series = computed(() => props.points.map((p, i) => ({
    index: i,
    point: p,
    value: toNumber(p?.value),
    excluded: !!p?.is_excluded,
})));

const labels = computed(() => series.value.map((s) => formatDateTime(s.point?.measured_at)));

/** Color del punto: manda la regla de Westgard cuando hay una. */
function colorFor(entry) {
    const c = palette.value;
    const rule = entry.point?.westgard_rule;
    const flag = (rule && props.rules?.[rule]) || entry.point?.flag || 'ok';
    if (flag === 'out') return c.out;
    if (flag === 'warn') return c.warn;
    return c.ok;
}

const activeLimits = computed(() => (
    [
        ['lcs', props.limits?.lcs],
        ['las', props.limits?.las],
        ['lc',  props.limits?.lc],
        ['lai', props.limits?.lai],
        ['lci', props.limits?.lci],
    ]
        .map(([key, raw]) => [key, toNumber(raw)])
        .filter(([, v]) => v !== null)
));

/**
 * El eje se calcula a mano en vez de dejar `scale: true`: las cinco líneas son
 * markLines y no cuentan para el rango automático, así que un patrón muy
 * estable dejaba el LCS y el LCI dibujados fuera del cuadro — justo lo que hay
 * que ver.
 */
const axisRange = computed(() => {
    const values = series.value.map((s) => s.value).filter((v) => v !== null);
    const all = values.concat(activeLimits.value.map(([, v]) => v));
    if (all.length === 0) return { min: null, max: null };

    const lo = Math.min(...all);
    const hi = Math.max(...all);
    const pad = hi === lo ? Math.abs(hi || 1) * 0.1 : (hi - lo) * 0.12;

    return { min: lo - pad, max: hi + pad };
});

const hasZoom = computed(() => series.value.length > 40);

const option = computed(() => {
    const c = palette.value;

    return {
        animation: false,
        grid: { left: 12, right: 20, top: 18, bottom: hasZoom.value ? 56 : 10, containLabel: true },
        tooltip: {
            trigger: 'item',
            confine: true,
            backgroundColor: c.surface,
            borderColor: c.grid,
            textStyle: { color: c.axis },
            formatter: (params) => tooltipHtml(params.dataIndex),
        },
        xAxis: {
            type: 'category',
            data: labels.value,
            boundaryGap: false,
            axisLabel: { color: c.axis, fontSize: 11, hideOverlap: true },
            axisLine: { lineStyle: { color: c.grid } },
            axisTick: { show: false },
        },
        yAxis: {
            type: 'value',
            name: props.unit || '',
            nameTextStyle: { color: c.axis, fontSize: 11, align: 'left' },
            min: axisRange.value.min,
            max: axisRange.value.max,
            axisLabel: { color: c.axis, fontSize: 11 },
            splitLine: { lineStyle: { color: c.grid, type: 'dashed' } },
        },
        dataZoom: hasZoom.value
            ? [
                { type: 'inside' },
                { type: 'slider', height: 18, bottom: 12, borderColor: c.grid, textStyle: { color: c.axis } },
            ]
            : undefined,
        series: [
            {
                type: 'line',
                name: t('qc_charts.points'),
                // Los excluidos se sacan de la línea (no participan del cálculo)
                // pero la línea no se corta: `connectNulls` la cierra sobre el
                // punto siguiente para que la tendencia se siga leyendo.
                connectNulls: true,
                showSymbol: true,
                symbolSize: 9,
                lineStyle: { color: c.line, width: 1.5 },
                data: series.value.map((s) => (
                    s.excluded || s.value === null
                        ? null
                        : { value: s.value, itemStyle: { color: colorFor(s) } }
                )),
                markLine: {
                    silent: true,
                    symbol: 'none',
                    data: activeLimits.value.map(([key, value]) => ({
                        yAxis: value,
                        lineStyle: {
                            color: key === 'lc' ? c.center : (key === 'lcs' || key === 'lci' ? c.out : c.warn),
                            type: key === 'lc' ? 'solid' : 'dashed',
                            width: key === 'lc' ? 2 : 1,
                        },
                        label: {
                            position: 'insideEndTop',
                            color: c.axis,
                            fontSize: 10,
                            formatter: `${t(`qc_charts.limits_short.${key}`)} ${fmtNumber(value)}`,
                        },
                    })),
                },
            },
            {
                // Serie aparte para los excluidos: se dibujan huecos y no se
                // ocultan. Que la carta muestre el punto descartado Y su motivo
                // es lo que permite al laboratorio probar que lo detectó.
                type: 'scatter',
                name: t('qc_charts.exclude'),
                symbolSize: 11,
                data: series.value.map((s) => (
                    s.excluded && s.value !== null
                        ? {
                            value: s.value,
                            itemStyle: {
                                color: c.surface,
                                borderColor: colorFor(s),
                                borderWidth: 2,
                                borderType: 'dashed',
                                opacity: 0.85,
                            },
                        }
                        : null
                )),
            },
        ],
    };
});

function tooltipHtml(index) {
    const entry = series.value[index];
    if (!entry) return '';

    const p = entry.point ?? {};
    const rows = [];

    rows.push(`<div style="font-weight:600">${esc(labels.value[index])}</div>`);
    rows.push(`${esc(t('qc_charts.value'))}: <b>${esc(fmtNumber(entry.value))}</b>${props.unit ? ` ${esc(props.unit)}` : ''}`);

    const z = toNumber(p.z_score);
    if (z !== null) {
        rows.push(`${esc(t('qc_charts.z_score'))}: ${esc(fmtNumber(z))}`);
    }

    const flag = p.flag ?? 'ok';
    rows.push(`${esc(t('qc_charts.flag'))}: ${esc(t(`qc_charts.flags.${flag}`))}`);

    if (p.westgard_rule) {
        rows.push(`${esc(t(`qc_charts.westgard_rules.${p.westgard_rule}`))}`);
        rows.push(`<i>${esc(t(`qc_charts.westgard_meaning.${p.westgard_rule}`))}</i>`);
    }

    if (p.is_excluded) {
        rows.push(`${esc(t('qc_charts.exclusion_reason'))}: ${esc(p.exclusion_reason || t('global.no_reason'))}`);
    }

    return `<div style="max-width:280px;white-space:normal;line-height:1.5">${rows.join('<br>')}</div>`;
}
</script>

<template>
    <div ref="root" class="lj">
        <VChart v-if="points.length > 0" class="lj__canvas" :option="option" autoresize />

        <p v-else class="lj__empty">{{ $t('qc_charts.empty_points') }}</p>

        <ul v-if="points.length > 0" class="lj__legend">
            <li><span class="lj__dot lj__dot--ok" />{{ $t('qc_charts.flags.ok') }}</li>
            <li><span class="lj__dot lj__dot--warn" />{{ $t('qc_charts.flags.warn') }}</li>
            <li><span class="lj__dot lj__dot--out" />{{ $t('qc_charts.flags.out') }}</li>
            <li><span class="lj__dot lj__dot--excluded" />{{ $t('qc_charts.exclude') }}</li>
        </ul>
    </div>
</template>

<style scoped>
/* Los colores del gráfico viven acá y no en el script: así el tema oscuro se
   resuelve con CSS —una sola fuente de verdad— y el JavaScript solo los lee. */
.lj {
    --lj-ok:       #1D7044;
    --lj-warn:     var(--color-warning);
    --lj-out:      var(--color-danger-bright);
    --lj-center:   var(--color-primary);
    --lj-line:     var(--color-icon-soft);
    --lj-axis:     var(--color-text-muted);
    --lj-grid:     var(--color-border);
    --lj-surface:  var(--color-surface);
    --lj-excluded: var(--color-text-dim);
}

html[data-theme="dark"] .lj {
    /* El verde y el rojo de fábrica quedan turbios sobre fondo oscuro. */
    --lj-ok:  #2FA36B;
    --lj-out: #E5534B;
}

.lj__canvas {
    width: 100%;
    height: 380px;
}

.lj__empty {
    margin: 0;
    padding: 48px 16px;
    text-align: center;
    color: var(--color-text-muted);
    font-size: 0.875rem;
}

.lj__legend {
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
    margin: 8px 0 0;
    padding: 0;
    list-style: none;
    font-size: 0.78rem;
    color: var(--color-text-muted);
}
.lj__legend li { display: inline-flex; align-items: center; gap: 6px; }

.lj__dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    flex-shrink: 0;
}
.lj__dot--ok       { background: var(--lj-ok); }
.lj__dot--warn     { background: var(--lj-warn); }
.lj__dot--out      { background: var(--lj-out); }
.lj__dot--excluded {
    background: var(--lj-surface);
    border: 2px dashed var(--lj-excluded);
}

@media (max-width: 768px) {
    .lj__canvas { height: 300px; }
}
</style>
