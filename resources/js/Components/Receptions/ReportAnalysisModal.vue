<script setup>
/**
 * Los valores detectados y el análisis de resultados del informe.
 *
 * Es la pantalla "Análisis de Resultado de Resultados" del sistema anterior:
 * una hoja por familia de ensayo con los valores medidos y su valor de
 * orientación, y debajo el párrafo que va impreso en el informe.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ EL TEXTO LO PROPONE EL SISTEMA, PERO LO FIRMA UNA PERSONA                │
 * └──────────────────────────────────────────────────────────────────────────┘
 * El motor compone el párrafo a partir de qué parámetros quedaron dentro y
 * fuera de norma, para el aceite y el tipo de equipo de ESTA muestra. El
 * analista lo corrige si el caso lo pide, y lo corregido no se vuelve a pisar
 * solo: solo el botón "Diagnóstico automático" lo reemplaza, y avisa que lo va
 * a hacer.
 *
 * La diferencia con el sistema anterior: si ninguna plantilla cubre la
 * combinación, el motor NO inventa una frase genérica. Deja el campo vacío y
 * dice por qué, porque una frase de relleno se firma sin leerla.
 */
import { computed, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { Alert, Button, Modal, Spin, Tag, Textarea } from 'ant-design-vue';
import { ExperimentOutlined, ThunderboltOutlined } from '@ant-design/icons-vue';

import { useI18n } from '@/Plugins/i18n';

const props = defineProps({
    open:   { type: Boolean, default: false },
    report: { type: Object, default: null },
});

const emit = defineEmits(['update:open']);

const { t } = useI18n();

const loading = ref(false);
const saving  = ref(false);
const data    = ref(null);
const bodies  = ref({});
const active  = ref(null);

const close = () => emit('update:open', false);

const cargar = async () => {
    loading.value = true;
    try {
        const res = await fetch(
            route('lab_management.sample_reports.analysis', props.report.slug),
            { headers: { Accept: 'application/json' } },
        );
        const json = await res.json();
        data.value = json;

        // El texto se indexa por FAMILIA, que es el mismo corte con el que el
        // informe arma sus páginas: la pantalla y el papel dicen lo mismo.
        bodies.value = Object.fromEntries(
            (json.analysis ?? []).map((a) => [a.family ?? a.code, a.body ?? '']),
        );
        active.value = json.sections?.[0]?.family ?? null;
    } finally {
        loading.value = false;
    }
};

watch(() => props.open, (abierto) => { if (abierto && props.report) cargar(); });

const seccionActiva = computed(
    () => (data.value?.sections ?? []).find((s) => s.family === active.value) ?? null,
);

const analisisDe = (familia) => (data.value?.analysis ?? []).find(
    (a) => (a.family ?? a.code) === familia,
);

/** Verde si la familia ya tiene su párrafo; rojo si le falta, como el viejo. */
const tieneTexto = (familia) => !!(bodies.value[familia] ?? '').trim();

const autodiagnosticar = () => Modal.confirm({
    title:      t('sample_reports.autodiagnose'),
    content:    t('sample_reports.autodiagnose_confirm'),
    okText:     t('sample_reports.autodiagnose'),
    cancelText: t('global.cancel'),
    onOk: () => router.post(
        route('lab_management.sample_reports.autodiagnose', props.report.slug),
        {},
        { preserveScroll: true, onSuccess: () => cargar() },
    ),
});

const guardar = () => {
    saving.value = true;
    router.put(
        route('lab_management.sample_reports.analysis.save', props.report.slug),
        { bodies: bodies.value },
        {
            preserveScroll: true,
            onSuccess: () => { close(); },
            onFinish:  () => { saving.value = false; },
        },
    );
};
</script>

<template>
    <Modal
        :open="open"
        width="100%"
        wrap-class-name="rfm-fullscreen"
        :title="null"
        :footer="null"
        :destroy-on-close="true"
        @update:open="(v) => emit('update:open', v)"
    >
        <div class="ran">
            <div class="ran__head">
                <h2 class="ran__title">
                    <ExperimentOutlined /> {{ $t('sample_reports.analysis_title') }}
                </h2>
                <span v-if="data" class="ran__sub">{{ data.code }} · {{ data.sample }}</span>
            </div>

            <Spin :spinning="loading">
                <div v-if="data" class="ran__body">
                    <Alert
                        v-if="!data.editable"
                        type="info"
                        show-icon
                        class="ran__note"
                        :message="$t('sample_reports.analysis_readonly')"
                    />
                    <Alert v-else type="info" show-icon class="ran__note"
                           :message="$t('sample_reports.analysis_intro')" />

                    <p v-if="!data.sections.length" class="ran__empty">
                        {{ $t('sample_reports.no_sections') }}
                    </p>

                    <div v-else class="ran__cols">
                        <!-- El índice de familias, con su semáforo: de un
                             vistazo se ve cuál todavía no tiene párrafo. -->
                        <nav class="ran__nav">
                            <button
                                v-for="s in data.sections"
                                :key="s.family"
                                type="button"
                                class="ran__navitem"
                                :class="{ 'ran__navitem--on': s.family === active }"
                                @click="active = s.family"
                            >
                                <span
                                    class="ran__dot"
                                    :class="tieneTexto(s.family) ? 'ran__dot--ok' : 'ran__dot--no'"
                                />
                                {{ s.test }}
                            </button>
                        </nav>

                        <div v-if="seccionActiva" class="ran__panel">
                            <div class="rfm__band">{{ seccionActiva.test }}</div>

                            <!-- Los valores DETECTADOS y contra qué se los
                                 juzgó. Salen del mismo payload que imprime el
                                 informe, así que no pueden discrepar. -->
                            <table class="ran__grid">
                                <thead>
                                    <tr>
                                        <th class="ran__num">{{ $t('sample_reports.col_item') }}</th>
                                        <th>{{ $t('sample_reports.col_test') }}</th>
                                        <th>{{ $t('sample_reports.col_unit') }}</th>
                                        <th>{{ $t('sample_reports.col_limit') }}</th>
                                        <th class="ran__num">{{ $t('sample_reports.col_result') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="row in seccionActiva.rows" :key="row.item">
                                        <td class="ran__num">{{ row.item }}</td>
                                        <td>{{ row.analyte }}</td>
                                        <td>{{ row.unit ?? '—' }}</td>
                                        <td>{{ row.limit ?? '—' }}</td>
                                        <td class="ran__num">
                                            <span :class="{ 'ran__out': row.status === 'out_of_spec' }">
                                                {{ row.value ?? '—' }}
                                            </span>
                                            <!-- Un valor que nadie comparó no
                                                 es conforme: se dice, no se
                                                 deja en blanco. -->
                                            <Tag
                                                v-if="row.status === null"
                                                color="default"
                                                :bordered="false"
                                                class="ran__tag"
                                            >{{ $t('sample_reports.no_criteria') }}</Tag>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>

                            <label class="ran__label">
                                {{ $t('sample_reports.analysis_title') }}
                                <Tag
                                    v-if="analisisDe(active)?.edited"
                                    color="orange"
                                    :bordered="false"
                                >{{ $t('sample_reports.analysis_edited') }}</Tag>
                            </label>
                            <Textarea
                                v-model:value="bodies[active]"
                                :rows="6"
                                :maxlength="4000"
                                :disabled="!data.editable"
                                :placeholder="$t('sample_reports.analysis_empty')"
                                show-count
                            />
                        </div>
                    </div>
                </div>
            </Spin>

            <div class="ran__foot">
                <Button @click="close">{{ $t('global.cancel') }}</Button>
                <Button v-if="data?.editable" :loading="loading" @click="autodiagnosticar">
                    <ThunderboltOutlined /> {{ $t('sample_reports.autodiagnose') }}
                </Button>
                <Button v-if="data?.editable" type="primary" :loading="saving" @click="guardar">
                    {{ $t('global.save_changes') }}
                </Button>
            </div>
        </div>
    </Modal>
</template>

<style scoped>
.ran { display: flex; flex-direction: column; min-height: calc(100vh - 120px); }

.ran__head { padding: 0 0 12px; border-bottom: 1px solid var(--color-border); }
.ran__title {
    display: inline-flex; align-items: center; gap: 8px;
    margin: 0; font-size: 1.05rem; font-weight: 600; color: var(--color-text-strong, #32363a);
}
.ran__sub { display: block; margin-top: 2px; font-size: 0.8125rem; color: var(--color-text-muted); }

.ran__body { flex: 1; padding: 12px 0 24px; }
.ran__note { margin-bottom: 12px; }
.ran__empty { color: var(--color-text-muted); font-size: 0.8125rem; }

.ran__cols { display: grid; grid-template-columns: 220px 1fr; gap: 20px; }
@media (max-width: 767px) { .ran__cols { grid-template-columns: 1fr; } }

.ran__nav { display: flex; flex-direction: column; gap: 2px; }
.ran__navitem {
    display: flex; align-items: center; gap: 8px;
    padding: 8px 10px; border: 0; border-radius: 6px;
    background: transparent; text-align: left; cursor: pointer;
    font-size: 0.8125rem; color: var(--color-text);
}
.ran__navitem:hover   { background: var(--color-surface-alt, #f0f2f5); }
.ran__navitem--on     { background: var(--color-surface-alt, #f0f2f5); font-weight: 600; }
.ran__dot { width: 8px; height: 8px; border-radius: 50%; flex: none; }
.ran__dot--ok { background: #52c41a; }
.ran__dot--no { background: #e0182d; }

.ran__grid { width: 100%; border-collapse: collapse; font-size: 0.8125rem; margin-bottom: 18px; }
.ran__grid th, .ran__grid td {
    text-align: left; padding: 6px 10px; border-bottom: 1px solid var(--color-border);
}
.ran__grid th { font-size: 0.72rem; text-transform: uppercase; color: var(--color-text-muted); }
.ran__num { text-align: right !important; font-variant-numeric: tabular-nums; }
.ran__out { color: #c8281d; font-weight: 700; }
.ran__tag { margin-left: 6px; font-size: 0.68rem; }

.ran__label {
    display: flex; align-items: center; gap: 8px;
    font-weight: 600; margin-bottom: 6px; color: var(--color-text);
}

.ran__foot {
    position: sticky; bottom: 0;
    display: flex; justify-content: flex-end; gap: 10px;
    padding: 12px 0;
    background: var(--color-surface, #fff);
    border-top: 1px solid var(--color-border);
}
</style>
