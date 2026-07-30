<script setup>
/**
 * El editor de las plantillas del análisis de resultados.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ POR QUÉ ESTA PANTALLA EXISTE                                             │
 * └──────────────────────────────────────────────────────────────────────────┘
 * El párrafo que el informe imprime por cada familia de ensayo es la redacción
 * del laboratorio y sale en el papel del cliente. En el sistema anterior cada
 * frase era un `if` dentro de una vista, repetido en tres archivos; después pasó
 * a un archivo del repositorio, que seguía exigiendo un despliegue para cambiar
 * una coma. Acá se edita y se guarda.
 *
 * Se agrupa por FAMILIA porque así se lee el informe y así se decide: al revisar
 * la redacción de fisicoquímico hay que ver juntos los cuatro casos (sin nada
 * fuera de norma, uno, varios) para que no se contradigan entre ellos.
 */
import { computed, ref, watch } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import {
    Alert, Button, Card, Collapse, CollapsePanel, Empty, Input, InputNumber,
    Popconfirm, Space, Tag, Textarea, Tooltip,
} from 'ant-design-vue';
import {
    FileTextOutlined, PlusOutlined, DeleteOutlined, UndoOutlined, SaveOutlined,
} from '@ant-design/icons-vue';

import AppLayout from '@/Layouts/AppLayout.vue';
import SectionHeader from '@/Components/Common/SectionHeader.vue';
import { useI18n } from '@/Plugins/i18n';

defineOptions({ layout: AppLayout });

const props = defineProps({
    templates: { type: Array,  default: () => [] },
    families:  { type: Array,  default: () => [] },
    cases:     { type: Array,  default: () => [] },
    can:       { type: Object, default: () => ({}) },
});

const { t } = useI18n();
const page = usePage();

/** El borrador de cada plantilla, indexado por su slug. */
const borrador = ref({});

const cargarBorradores = () => {
    const nuevo = {};
    for (const p of props.templates) {
        nuevo[p.slug] = {
            body: p.body ?? '',
            // Copia profunda: editar una banda no puede mutar la prop.
            bands: (p.bands ?? []).map((b) => ({ ...b })),
            notes: p.notes ?? '',
            guardando: false,
        };
    }
    borrador.value = nuevo;
};

cargarBorradores();
watch(() => props.templates, cargarBorradores, { deep: false });

/** Las plantillas agrupadas por familia, en el orden que llegó del servidor. */
const porFamilia = computed(() => {
    const mapa = new Map();
    for (const p of props.templates) {
        if (!mapa.has(p.family)) mapa.set(p.family, []);
        mapa.get(p.family).push(p);
    }

    return [...mapa.entries()].map(([family, items]) => ({ family, items }));
});

/** Cuántas familias tienen al menos una plantilla personalizada. */
const personalizadas = computed(() => props.templates.filter((p) => p.is_overridden).length);

const errores = computed(() => Object.values(page.props.errors ?? {}).filter(Boolean));

const etiquetaCaso = (caso) => t(`diagnosis_templates.case.${caso}`);

const agregarBanda = (slug) => {
    borrador.value[slug].bands.push({ min: null, max: null, body: '' });
};

const quitarBanda = (slug, indice) => {
    borrador.value[slug].bands.splice(indice, 1);
};

const guardar = (plantilla) => {
    const b = borrador.value[plantilla.slug];
    b.guardando = true;

    router.put(
        route('lab_management.diagnosis_templates.update', plantilla.slug),
        { body: b.body, bands: b.bands, notes: b.notes },
        {
            preserveScroll: true,
            onFinish: () => { b.guardando = false; },
        },
    );
};

const restaurar = (plantilla) => {
    router.post(
        route('lab_management.diagnosis_templates.restore', plantilla.slug),
        {},
        { preserveScroll: true },
    );
};
</script>

<template>
    <Head :title="$t('diagnosis_templates.title')" />

    <div class="form-page sap-form">
        <SectionHeader
            :title="$t('diagnosis_templates.title')"
            :subtitle="$t('diagnosis_templates.intro')"
        >
            <template #icon><FileTextOutlined /></template>
        </SectionHeader>

        <div class="form-body">
            <Alert
                v-for="(error, i) in errores"
                :key="i"
                type="error"
                show-icon
                class="dt-alert"
                :message="error"
            />

            <!-- Quién edita qué. Se dice antes de que alguien escriba, no
                 después: el super cambia el estándar de TODOS los laboratorios
                 y el admin solo su copia, y confundirlos tiene consecuencias. -->
            <Alert
                type="info"
                show-icon
                class="dt-alert"
                :message="can.edits_factory
                    ? $t('diagnosis_templates.scope_super')
                    : $t('diagnosis_templates.scope_admin')"
            />

            <Alert
                v-if="personalizadas > 0 && !can.edits_factory"
                type="warning"
                show-icon
                class="dt-alert"
                :message="$t('diagnosis_templates.overridden_count', { count: personalizadas })"
            />

            <!-- Los marcadores. Sin esta referencia el texto se edita a ciegas:
                 escribir "{ok}" mal no rompe nada visible hasta que el informe
                 sale con la llave impresa. -->
            <Card class="dt-help" :body-style="{ padding: '10px 14px' }">
                <div class="dt-help__title">{{ $t('diagnosis_templates.markers_title') }}</div>
                <div class="dt-help__grid">
                    <div><code>{ok}</code> {{ $t('diagnosis_templates.marker_ok') }}</div>
                    <div><code>{failed}</code> {{ $t('diagnosis_templates.marker_failed') }}</div>
                    <div><code>{norm}</code> {{ $t('diagnosis_templates.marker_norm') }}</div>
                    <div><code>{value}</code> {{ $t('diagnosis_templates.marker_value') }}</div>
                </div>
            </Card>

            <Empty v-if="porFamilia.length === 0" :description="$t('diagnosis_templates.empty')" />

            <Collapse v-else :bordered="false" class="dt-fams">
                <CollapsePanel
                    v-for="grupo in porFamilia"
                    :key="grupo.family"
                    :header="`${$t('diagnosis_templates.family.' + grupo.family)} (${grupo.items.length})`"
                >
                    <div v-for="p in grupo.items" :key="p.slug" class="dt-tpl">
                        <div class="dt-tpl__head">
                            <Space :size="6" wrap>
                                <Tag :bordered="false" color="blue">{{ etiquetaCaso(p.case) }}</Tag>
                                <Tag v-if="p.analyte" :bordered="false">
                                    {{ $t('diagnosis_templates.by_analyte', { analyte: p.analyte }) }}
                                </Tag>
                                <Tooltip
                                    v-if="p.is_overridden"
                                    :title="$t('diagnosis_templates.overridden_hint')"
                                >
                                    <Tag color="gold" :bordered="false">
                                        {{ $t('diagnosis_templates.overridden') }}
                                    </Tag>
                                </Tooltip>
                                <Tag v-else :bordered="false">{{ $t('diagnosis_templates.factory') }}</Tag>
                            </Space>

                            <Space :size="8">
                                <!-- Restaurar solo aparece en la copia del
                                     laboratorio: borrar la de fábrica dejaría
                                     la familia sin redacción. -->
                                <Popconfirm
                                    v-if="p.is_overridden"
                                    :title="$t('diagnosis_templates.restore_confirm')"
                                    :ok-text="$t('diagnosis_templates.restore')"
                                    :cancel-text="$t('global.cancel')"
                                    @confirm="restaurar(p)"
                                >
                                    <Button size="small">
                                        <UndoOutlined /> {{ $t('diagnosis_templates.restore') }}
                                    </Button>
                                </Popconfirm>

                                <Button
                                    v-if="can.edit"
                                    size="small"
                                    type="primary"
                                    :loading="borrador[p.slug]?.guardando"
                                    @click="guardar(p)"
                                >
                                    <SaveOutlined /> {{ $t('global.save') }}
                                </Button>
                            </Space>
                        </div>

                        <!-- El texto de la plantilla. Las graduadas pueden no
                             tener cuerpo: su redacción vive en las bandas. -->
                        <Textarea
                            v-if="borrador[p.slug]"
                            v-model:value="borrador[p.slug].body"
                            :rows="3"
                            :maxlength="4000"
                            :disabled="!can.edit"
                            :placeholder="$t('diagnosis_templates.body_placeholder')"
                        />

                        <!-- Las BANDAS: un texto por tramo de valor. Es lo que
                             en el sistema anterior eran cuatro `if` seguidos con
                             los cortes escritos en el código. -->
                        <div v-if="borrador[p.slug] && (borrador[p.slug].bands.length || p.analyte)" class="dt-bands">
                            <div class="dt-bands__title">
                                {{ $t('diagnosis_templates.bands_title') }}
                                <span class="dt-bands__hint">{{ $t('diagnosis_templates.bands_hint') }}</span>
                            </div>

                            <div
                                v-for="(banda, i) in borrador[p.slug].bands"
                                :key="i"
                                class="dt-band"
                            >
                                <InputNumber
                                    v-model:value="banda.min"
                                    size="small"
                                    :disabled="!can.edit"
                                    :placeholder="$t('diagnosis_templates.band_min')"
                                    class="dt-band__num"
                                />
                                <InputNumber
                                    v-model:value="banda.max"
                                    size="small"
                                    :disabled="!can.edit"
                                    :placeholder="$t('diagnosis_templates.band_max')"
                                    class="dt-band__num"
                                />
                                <Input
                                    v-model:value="banda.body"
                                    size="small"
                                    :disabled="!can.edit"
                                    :maxlength="2000"
                                    :placeholder="$t('diagnosis_templates.band_body')"
                                />
                                <Button
                                    v-if="can.edit"
                                    size="small"
                                    danger
                                    type="text"
                                    @click="quitarBanda(p.slug, i)"
                                >
                                    <DeleteOutlined />
                                </Button>
                            </div>

                            <Button v-if="can.edit" size="small" type="dashed" @click="agregarBanda(p.slug)">
                                <PlusOutlined /> {{ $t('diagnosis_templates.add_band') }}
                            </Button>
                        </div>

                        <!-- La procedencia: de qué vista del sistema anterior
                             salió esta redacción. Sirve para discutir un cambio
                             sabiendo qué decía el papel de antes. -->
                        <div v-if="p.origin" class="dt-origin">
                            {{ $t('diagnosis_templates.origin') }}: <code>{{ p.origin }}</code>
                        </div>
                        <div v-if="p.notes" class="dt-origin">{{ p.notes }}</div>
                    </div>
                </CollapsePanel>
            </Collapse>
        </div>
    </div>
</template>

<style scoped>
.dt-alert { margin-bottom: 12px; }
.dt-help { margin-bottom: 14px; }
.dt-help__title { font-weight: 600; font-size: 0.8125rem; margin-bottom: 6px; }
.dt-help__grid { display: flex; flex-wrap: wrap; gap: 6px 22px; font-size: 0.78rem; color: var(--color-text-muted); }
.dt-help__grid code { font-weight: 600; color: var(--color-text); }

.dt-fams :deep(.ant-collapse-header) { font-weight: 600; }

.dt-tpl {
    padding: 12px 0 14px;
    border-bottom: 1px solid var(--color-border-subtle, #f2f3f5);
}
.dt-tpl:last-child { border-bottom: 0; }
.dt-tpl__head {
    display: flex; align-items: center; justify-content: space-between;
    gap: 10px; flex-wrap: wrap; margin-bottom: 7px;
}

.dt-bands { margin-top: 10px; }
.dt-bands__title { font-size: 0.78rem; font-weight: 600; margin-bottom: 5px; }
.dt-bands__hint { font-weight: 400; color: var(--color-text-muted); margin-left: 6px; }
.dt-band { display: flex; gap: 6px; align-items: center; margin-bottom: 5px; }
.dt-band__num { width: 92px; flex: 0 0 auto; }
.dt-band :deep(.ant-input) { flex: 1 1 auto; }

.dt-origin { margin-top: 6px; font-size: 0.72rem; color: var(--color-text-muted); }
</style>
