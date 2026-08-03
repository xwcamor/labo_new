<script setup>
/**
 * Tendencias: cómo evoluciona el aceite de un equipo del cliente en el tiempo.
 *
 * Se elige el equipo y después qué parámetros mirar. El selector de parámetros
 * ofrece SOLO los que ese equipo tiene medidos —de 60 del catálogo, un
 * transformador con cromatografía tiene nueve— y dice cuántos puntos tiene cada
 * uno: una tendencia de un solo punto no es una tendencia.
 */
import { computed, ref, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { Button, Card, Select, SelectOption, Empty, Tag } from 'ant-design-vue';
import { LineChartOutlined } from '@ant-design/icons-vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import TrendChart from '@/Components/Trends/TrendChart.vue';
import { useI18n } from '@/Plugins/i18n';

defineOptions({ layout: AppLayout });

const props = defineProps({
    equipment: { type: Array,  default: () => [] },
    selected:  { type: Object, default: null },
    analytes:  { type: Array,  default: () => [] },
    series:    { type: Array,  default: () => [] },
    filters:   { type: Object, default: () => ({}) },
});

const { t } = useI18n();

const equipo   = ref(props.filters.equipment ?? undefined);
const elegidos = ref(props.filters.analytes ?? []);

const recargar = () => {
    router.get(route('lab_management.trends.index'), {
        equipment: equipo.value,
        analytes:  elegidos.value,
    }, { preserveState: true, preserveScroll: true, replace: true });
};

// Al cambiar de equipo se limpian los parámetros: los del equipo anterior
// pueden no existir en el nuevo, y un gráfico vacío no explica por qué.
watch(equipo, () => {
    elegidos.value = [];
    recargar();
});

const buscar = (texto) => {
    router.get(route('lab_management.trends.index'), {
        equipment: equipo.value,
        analytes:  elegidos.value,
        q: texto,
    }, { preserveState: true, preserveScroll: true, replace: true, only: ['equipment'] });
};

const etiquetaEquipo = (e) => [e.name, e.serial, e.customer].filter(Boolean).join(' · ');

// Agrupados por familia para que los nueve gases no queden mezclados con los
// fisicoquímicos en una lista de sesenta.
const porGrupo = computed(() => {
    const mapa = {};
    props.analytes.forEach((a) => {
        (mapa[a.group] ??= []).push(a);
    });
    return mapa;
});
</script>

<template>
    <Head :title="$t('trends.title')" />

    <div class="sap-index tr-page">
        <div class="mi-title">
            <div class="page-header__title">
                <div class="page-header__icon"><LineChartOutlined /></div>
                <div class="page-header__heading">
                    <h1>{{ $t('trends.title') }}</h1>
                    <p>{{ $t('trends.subtitle') }}</p>
                </div>
            </div>
        </div>

        <Card class="tr-picker" :body-style="{ padding: '14px 16px' }">
            <div class="tr-picker__row">
                <div class="tr-field">
                    <label>{{ $t('trends.equipment') }}</label>
                    <Select
                        v-model:value="equipo"
                        show-search
                        :filter-option="false"
                        :placeholder="$t('trends.pick_equipment')"
                        style="min-width: 340px"
                        @search="buscar"
                    >
                        <SelectOption v-for="e in equipment" :key="e.slug" :value="e.slug">
                            {{ etiquetaEquipo(e) }}
                        </SelectOption>
                    </Select>
                </div>

                <div v-if="selected" class="tr-field tr-field--grow">
                    <label>{{ $t('trends.parameters') }}</label>
                    <Select
                        v-model:value="elegidos"
                        mode="multiple"
                        :placeholder="$t('trends.pick_parameters')"
                        style="width: 100%"
                        option-filter-prop="label"
                        @change="recargar"
                    >
                        <Select.OptGroup v-for="(items, grupo) in porGrupo" :key="grupo" :label="grupo">
                            <SelectOption
                                v-for="a in items"
                                :key="a.code"
                                :value="a.code"
                                :label="a.name"
                            >
                                {{ a.name }}
                                <span class="tr-opt__n">{{ $tc('trends.points_n', a.points) }}</span>
                            </SelectOption>
                        </Select.OptGroup>
                    </Select>
                </div>
            </div>

            <div v-if="selected" class="tr-picked">
                <Tag :bordered="false">{{ selected.serial || selected.name }}</Tag>
                <span v-if="selected.tag" class="tr-picked__tag">{{ selected.tag }}</span>
            </div>
        </Card>

        <Empty v-if="!selected" :description="$t('trends.pick_equipment')" class="tr-empty" />
        <Empty v-else-if="series.length === 0" :description="$t('trends.pick_parameters')" class="tr-empty" />

        <div v-else class="tr-grid">
            <TrendChart v-for="s in series" :key="s.code" :serie="s" />
        </div>
    </div>
</template>

<style scoped>
.tr-picker { margin-bottom: 16px; }
.tr-picker__row { display: flex; gap: 20px; flex-wrap: wrap; align-items: flex-end; }
.tr-field { display: flex; flex-direction: column; gap: 4px; }
.tr-field--grow { flex: 1; min-width: 320px; }
.tr-field label { font-size: 0.78rem; color: var(--color-text-muted); }
.tr-picked { margin-top: 10px; display: flex; align-items: center; gap: 8px; }
.tr-picked__tag { font-size: 0.78rem; color: var(--color-text-muted); }
.tr-opt__n { margin-left: 8px; font-size: 0.72rem; color: var(--color-text-muted); }
.tr-empty { margin-top: 40px; }
.tr-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(460px, 1fr));
    gap: 16px;
}
</style>
