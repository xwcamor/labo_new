<script setup>
/**
 * Reportes de Lab. — los 7 Excel del sistema antiguo, en una sola pantalla.
 *
 * En el viejo eran 7 ítems de menú, cada uno con la misma pantalla de filtros
 * repetida (dos fechas sobre la recepción). Acá el rango se elige UNA vez y
 * cada tarjeta descarga su Excel con ese rango. La descarga es un GET simple
 * (el navegador baja el archivo; no pasa por Inertia).
 */
import { computed, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import { Button, Card, DatePicker } from 'ant-design-vue';
import { DownloadOutlined, FileExcelOutlined } from '@ant-design/icons-vue';
import dayjs from 'dayjs';
import AppLayout from '@/Layouts/AppLayout.vue';
import SectionHeader from '@/Components/Common/SectionHeader.vue';
import { useI18n } from '@/Plugins/i18n';

defineOptions({ layout: AppLayout });

const props = defineProps({
    reports:     { type: Array, required: true },
    defaultFrom: { type: String, required: true },
});

const { t } = useI18n();

const desde = ref(dayjs(props.defaultFrom));
const hasta = ref(null);

// "Hasta" nunca antes que "desde" (la misma regla del formulario del informe).
const antesDeDesde = (fecha) => desde.value && fecha.isBefore(desde.value, 'day');

const urlDe = computed(() => (reporte) => {
    const params = { from: desde.value?.format('YYYY-MM-DD') };
    if (hasta.value) params.to = hasta.value.format('YYYY-MM-DD');
    return route('lab_management.lab_reports.download', { report: reporte, ...params });
});
</script>

<template>
    <Head :title="$t('lab_reports.title')" />

    <div class="show-page sap-show lr-page">
        <SectionHeader :title="$t('lab_reports.title')">
            <template #icon><FileExcelOutlined /></template>
            <template #subtitle><span class="lr-sub">{{ $t('lab_reports.subtitle') }}</span></template>
        </SectionHeader>

        <Card class="lr-filters" :body-style="{ padding: '14px 18px' }">
            <div class="lr-filters__row">
                <div class="lr-field">
                    <label>{{ $t('lab_reports.from') }}</label>
                    <DatePicker v-model:value="desde" :allow-clear="false" format="DD-MM-YYYY" />
                </div>
                <div class="lr-field">
                    <label>{{ $t('lab_reports.to') }}</label>
                    <DatePicker v-model:value="hasta" :disabled-date="antesDeDesde" format="DD-MM-YYYY" />
                </div>
            </div>
        </Card>

        <div class="lr-grid">
            <Card v-for="reporte in reports" :key="reporte" class="lr-card" :body-style="{ padding: '16px 18px' }">
                <div class="lr-card__head">
                    <FileExcelOutlined class="lr-card__icon" />
                    <span class="lr-card__name">{{ t(`lab_reports.reports.${reporte}.name`) }}</span>
                </div>
                <p class="lr-card__desc">{{ t(`lab_reports.reports.${reporte}.desc`) }}</p>
                <a :href="urlDe(reporte)">
                    <Button type="primary">
                        <template #icon><DownloadOutlined /></template>
                        {{ $t('lab_reports.download') }}
                    </Button>
                </a>
            </Card>
        </div>
    </div>
</template>

<style scoped>
.lr-sub { color: var(--color-text-muted); font-size: 0.8rem; }
.lr-filters { margin-bottom: 16px; }
.lr-filters__row { display: flex; gap: 24px; flex-wrap: wrap; }
.lr-field { display: flex; flex-direction: column; gap: 4px; }
.lr-field label { font-size: 0.78rem; color: var(--color-text-muted); }
.lr-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 16px;
}
.lr-card__head { display: flex; align-items: center; gap: 10px; margin-bottom: 8px; }
.lr-card__icon { font-size: 20px; color: #1D7044; }
.lr-card__name { font-weight: 600; }
.lr-card__desc {
    color: var(--color-text-muted);
    font-size: 0.82rem;
    min-height: 56px;
    margin-bottom: 12px;
}
</style>
