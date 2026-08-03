<script setup>
/**
 * La evolución de un parámetro del aceite de un equipo, en el tiempo.
 *
 * Dos cuidados heredados del sistema anterior, donde los valores se
 * interpolaban crudos dentro del JavaScript del gráfico:
 *
 *  1. Todo valor pasa por `Number.isFinite` antes de entrar a la serie. Esa
 *     base tiene guardado el texto "NaN" de fórmulas fallidas, y un solo NaN
 *     dejaba el gráfico ENTERO en blanco, sin error visible.
 *  2. El límite de norma se dibuja POR PUNTO (como su propia serie escalonada)
 *     y no como una línea constante: si la norma cambió entre dos muestras, la
 *     línea lo muestra en vez de mentir con el límite de hoy hacia atrás.
 */
import { computed } from 'vue';
import VChart from 'vue-echarts';
import { use } from 'echarts/core';
import { LineChart } from 'echarts/charts';
import { GridComponent, TooltipComponent, LegendComponent, DataZoomComponent } from 'echarts/components';
import { CanvasRenderer } from 'echarts/renderers';
import { useI18n } from '@/Plugins/i18n';

use([LineChart, GridComponent, TooltipComponent, LegendComponent, DataZoomComponent, CanvasRenderer]);

const props = defineProps({
    /** { code, name, unit, decimals, points: [{date, value, min, max, status, sample, censored}] } */
    serie: { type: Object, required: true },
});

const { t } = useI18n();

const numero = (v) => (Number.isFinite(Number(v)) ? Number(v) : null);

const fechas = computed(() => props.serie.points.map((p) => p.date));

const valores = computed(() => props.serie.points.map((p) => ({
    value: numero(p.value),
    // Fuera de norma en rojo: es el punto que hay que mirar.
    itemStyle: p.status === 'out_of_spec'
        ? { color: '#C8281D' }
        : (p.censored ? { color: '#E9A23B' } : undefined),
    symbolSize: p.status === 'out_of_spec' ? 9 : 6,
})));

const limiteMax = computed(() => props.serie.points.map((p) => numero(p.max)));
const limiteMin = computed(() => props.serie.points.map((p) => numero(p.min)));
const hayMax = computed(() => limiteMax.value.some((v) => v !== null));
const hayMin = computed(() => limiteMin.value.some((v) => v !== null));

const option = computed(() => ({
    grid: { left: 56, right: 18, top: 34, bottom: 46 },
    tooltip: {
        trigger: 'axis',
        formatter: (params) => {
            const i = params[0]?.dataIndex ?? 0;
            const p = props.serie.points[i];
            if (!p) return '';
            // El código de muestra se escapa: lo compone el sistema, pero el
            // tooltip de echarts se renderiza como HTML.
            const muestra = String(p.sample ?? '—').replace(/[<>&]/g, '');
            const valor = p.value?.toFixed(props.serie.decimals ?? 2);
            const fuera = p.status === 'out_of_spec' ? ` · ${t('trends.out_of_spec')}` : '';
            return `${p.date}<br><b>${valor} ${props.serie.unit ?? ''}</b>${fuera}<br>${muestra}`;
        },
    },
    // La leyenda solo nombra las series que existen: listar "Límite mínimo"
    // cuando el parámetro no tiene mínimo deja un ítem gris que parece apagado
    // por el usuario, y hace dudar de si el límite está oculto o no existe.
    legend: {
        top: 4,
        data: [
            props.serie.name,
            ...(hayMax.value ? [t('trends.limit_max')] : []),
            ...(hayMin.value ? [t('trends.limit_min')] : []),
        ],
    },
    xAxis: { type: 'category', data: fechas.value, axisLabel: { fontSize: 10 } },
    yAxis: {
        type: 'value',
        name: props.serie.unit || '',
        nameTextStyle: { fontSize: 10 },
        scale: true,
        axisLabel: { fontSize: 10 },
    },
    dataZoom: props.serie.points.length > 12
        ? [{ type: 'inside' }, { type: 'slider', height: 16, bottom: 6 }]
        : [],
    series: [
        {
            name: props.serie.name,
            type: 'line',
            data: valores.value,
            smooth: false,
            connectNulls: false,
            lineStyle: { width: 2, color: '#0A6ED1' },
            itemStyle: { color: '#0A6ED1' },
        },
        ...(hayMax.value ? [{
            name: t('trends.limit_max'),
            type: 'line',
            step: 'end',
            data: limiteMax.value,
            symbol: 'none',
            lineStyle: { type: 'dashed', width: 1, color: '#C8281D' },
        }] : []),
        ...(hayMin.value ? [{
            name: t('trends.limit_min'),
            type: 'line',
            step: 'end',
            data: limiteMin.value,
            symbol: 'none',
            lineStyle: { type: 'dashed', width: 1, color: '#E9A23B' },
        }] : []),
    ],
}));
</script>

<template>
    <div class="tc">
        <div class="tc__head">
            <span class="tc__name">{{ serie.name }}</span>
            <span v-if="serie.unit" class="tc__unit">{{ serie.unit }}</span>
            <span class="tc__count">{{ $tc('trends.points_n', serie.points.length) }}</span>
        </div>
        <VChart :option="option" autoresize class="tc__chart" />
    </div>
</template>

<style scoped>
.tc { border: 1px solid var(--color-border); border-radius: 10px; padding: 10px 12px; background: var(--color-surface); }
.tc__head { display: flex; align-items: baseline; gap: 8px; margin-bottom: 4px; }
.tc__name { font-weight: 600; }
.tc__unit, .tc__count { font-size: 0.75rem; color: var(--color-text-muted); }
.tc__count { margin-left: auto; }
.tc__chart { height: 260px; width: 100%; }
</style>
