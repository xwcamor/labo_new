<script setup>
/**
 * El alta y la edición del informe, a pantalla completa.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ POR QUÉ A PANTALLA COMPLETA Y NO EN UNA PÁGINA APARTE                    │
 * └──────────────────────────────────────────────────────────────────────────┘
 * Son treinta campos y casi todos llegan pre-cargados: quien emite un informe
 * revisa y corrige media docena. Mandarlo a otra URL le hace perder de vista la
 * entrega —qué otras muestras faltan, cuáles ya se informaron— y lo obliga a
 * volver. El modal a pantalla completa da el ancho de una página sin sacarlo de
 * la ficha, que es exactamente lo que hacía el sistema anterior
 * (`modal-fullscreen`) y era lo correcto.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ LOS DATOS NO SE COPIAN DENTRO DEL INFORME                                │
 * └──────────────────────────────────────────────────────────────────────────┘
 * Cada campo se guarda donde vive: el contacto en la recepción, el punto de
 * muestreo en la muestra, el TAG en el equipo. En el sistema anterior los
 * cuarenta campos se copiaban en la fila del reporte, así que corregir un dato
 * del transformador había que corregirlo informe por informe. La fotocopia se
 * saca una sola vez, al emitir.
 */
import { computed, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import {
    Alert, Button, Checkbox, DatePicker, Input, InputNumber, Modal, Spin, Textarea, Tooltip,
} from 'ant-design-vue';
import { FileTextOutlined } from '@ant-design/icons-vue';
import dayjs from 'dayjs';

import { useI18n } from '@/Plugins/i18n';

const props = defineProps({
    open:   { type: Boolean, default: false },
    /** La muestra a la que se le crea el informe (alta). */
    sample: { type: Object, default: null },
    /** El informe que se edita, si es edición. */
    report: { type: Object, default: null },
});

const emit = defineEmits(['update:open', 'saved']);

const { t } = useI18n();

const loading = ref(false);
const saving  = ref(false);
const data    = ref(null);
const form    = ref({});

const isEdit = computed(() => !!props.report);

/** El correlativo todavía no existe en el alta: lo emite el servidor al guardar. */
const code = computed(() => data.value?.report?.code ?? t('sample_reports.new'));

const close = () => emit('update:open', false);

/**
 * Los campos del formulario, ya repartidos.
 *
 * `readonly` es lo que identifica al registro y no se toca desde acá: el código
 * de muestra, el cliente y la serie del equipo. Se muestran para que el
 * operador confirme que está sobre la muestra correcta, que es para lo que
 * servían los recuadros grises del sistema anterior.
 */
const cargar = async () => {
    const url = isEdit.value
        ? route('lab_management.sample_reports.edit', props.report.slug)
        : route('lab_management.sample_reports.create', props.sample.slug);

    loading.value = true;
    try {
        const res = await fetch(url, { headers: { Accept: 'application/json' } });
        const json = await res.json();
        data.value = json;

        form.value = {
            ...json.header,
            issued_at:    json.report?.issued_at ?? dayjs().format('YYYY-MM-DD'),
            delivered_at: json.report?.delivered_at ?? null,
            notes:        json.report?.notes ?? null,
            tests: json.tests.filter((x) => x.is_visible).map((x) => x.id),
        };
    } finally {
        loading.value = false;
    }
};

watch(() => props.open, (abierto) => { if (abierto) cargar(); });

const toggleTest = (id, checked) => {
    const set = new Set(form.value.tests ?? []);
    if (checked) set.add(id); else set.delete(id);
    form.value.tests = [...set];
};

/** Las fechas viajan como texto: el servidor no tiene por qué saber de dayjs. */
const asDate = (v) => (v ? dayjs(v) : null);
const setDate = (campo, v) => { form.value[campo] = v ? dayjs(v).format('YYYY-MM-DD') : null; };

const submit = () => {
    saving.value = true;

    const url = isEdit.value
        ? route('lab_management.sample_reports.update', props.report.slug)
        : route('lab_management.sample_reports.store', props.sample.slug);

    const method = isEdit.value ? 'put' : 'post';

    router[method](url, form.value, {
        preserveScroll: true,
        onSuccess: () => { close(); emit('saved'); },
        onFinish:  () => { saving.value = false; },
    });
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
        <div class="rfm">
            <div class="rfm__head">
                <h2 class="rfm__title">
                    <FileTextOutlined /> {{ code }}
                </h2>
                <span v-if="data?.readonly" class="rfm__sub">
                    {{ data.readonly.sample_code }} · {{ data.readonly.customer_name ?? '—' }}
                </span>
            </div>

            <Spin :spinning="loading">
                <div v-if="data" class="rfm__body">
                    <Alert type="info" show-icon class="rfm__note" :message="$t('sample_reports.header_note')" />
                    <Alert
                        v-if="!data.header.has_equipment"
                        type="warning"
                        show-icon
                        class="rfm__note"
                        :message="$t('sample_reports.no_equipment')"
                    />

                    <!-- ── Referencia ──────────────────────────────────── -->
                    <div class="rfm__band">{{ $t('sample_reports.section_reference') }}</div>
                    <div class="rfm__grid">
                        <label class="rfm__f">
                            <span>{{ $t('sample_reports.service_order') }}</span>
                            <Input v-model:value="form.service_order" :maxlength="60" />
                        </label>
                        <label class="rfm__f">
                            <span>{{ $t('sample_reports.sampling_reason') }}</span>
                            <Input v-model:value="form.sampling_reason" :maxlength="80" />
                        </label>
                        <label class="rfm__f rfm__f--wide">
                            <span>{{ $t('sample_reports.contact_info') }}</span>
                            <Textarea v-model:value="form.contact_info" :rows="2" :maxlength="1000" />
                        </label>
                        <label class="rfm__f">
                            <span>{{ $t('sample_reports.received_at') }}</span>
                            <Input :value="form.received_at ?? '—'" disabled />
                        </label>
                        <label class="rfm__f rfm__f--wide">
                            <span>{{ $t('sample_reports.description') }}</span>
                            <Textarea v-model:value="form.description" :rows="2" :maxlength="1000" />
                        </label>
                        <label class="rfm__f">
                            <span>{{ $t('sample_reports.end_user') }}</span>
                            <Tooltip :title="$t('sample_reports.end_user_help')">
                                <Input v-model:value="form.end_user" :maxlength="255" />
                            </Tooltip>
                        </label>
                        <label class="rfm__f">
                            <span>{{ $t('sample_reports.issued_at') }}</span>
                            <DatePicker
                                :value="asDate(form.issued_at)"
                                style="width:100%"
                                @update:value="(v) => setDate('issued_at', v)"
                            />
                        </label>
                        <label class="rfm__f">
                            <span>{{ $t('sample_reports.sampler') }}</span>
                            <Input :value="form.sampler ?? '—'" disabled />
                        </label>
                        <label class="rfm__f">
                            <span>{{ $t('sample_reports.delivered_at') }}</span>
                            <DatePicker
                                :value="asDate(form.delivered_at)"
                                style="width:100%"
                                @update:value="(v) => setDate('delivered_at', v)"
                            />
                        </label>
                    </div>

                    <!-- ── Datos del equipo ────────────────────────────── -->
                    <div class="rfm__band">{{ $t('sample_reports.section_equipment') }}</div>
                    <div class="rfm__grid">
                        <label class="rfm__f">
                            <span>{{ $t('sample_reports.customer') }}</span>
                            <Input :value="data.readonly.customer_name ?? '—'" disabled />
                        </label>
                        <label class="rfm__f">
                            <span>{{ $t('sample_reports.serial') }}</span>
                            <Input :value="data.readonly.serial ?? '—'" disabled />
                        </label>
                        <label class="rfm__f">
                            <span>{{ $t('sample_reports.location') }}</span>
                            <Input :value="form.location ?? '—'" disabled />
                        </label>
                        <label class="rfm__f">
                            <span>{{ $t('sample_reports.brand') }}</span>
                            <Input :value="form.brand ?? '—'" disabled />
                        </label>
                        <label class="rfm__f">
                            <span>{{ $t('sample_reports.voltage') }}</span>
                            <Input
                                :value="form.voltage_label || '—'"
                                disabled
                            />
                        </label>
                        <label class="rfm__f">
                            <span>{{ $t('sample_reports.power') }}</span>
                            <Input :value="form.power_label || '—'" disabled />
                        </label>
                        <label class="rfm__f">
                            <span>{{ $t('sample_reports.equipment_type') }}</span>
                            <Input :value="form.equipment_type ?? '—'" disabled />
                        </label>
                        <label class="rfm__f">
                            <span>{{ $t('sample_reports.oil_type') }}</span>
                            <Input :value="form.oil_type ?? '—'" disabled />
                        </label>
                        <!-- Estos tres SÍ se editan acá: son datos que el
                             cliente declara con la muestra y que muchas veces
                             llegan recién con el informe. Se guardan en el
                             equipo, así que la próxima muestra del mismo
                             transformador ya viene con ellos. -->
                        <label class="rfm__f">
                            <span>{{ $t('sample_reports.oil_brand') }}</span>
                            <Input v-model:value="form.oil_brand" :maxlength="120" />
                        </label>
                        <label class="rfm__f">
                            <span>{{ $t('sample_reports.manufacture_year') }}</span>
                            <InputNumber
                                v-model:value="form.manufacture_year"
                                :min="1900" :max="new Date().getFullYear() + 1"
                                style="width:100%"
                            />
                        </label>
                        <label class="rfm__f">
                            <span>{{ $t('sample_reports.oil_volume') }}</span>
                            <InputNumber v-model:value="form.oil_volume" :min="0" style="width:100%" />
                        </label>
                        <label class="rfm__f">
                            <span>{{ $t('sample_reports.tap_changer') }}</span>
                            <Input :value="form.tap_changer ?? '—'" disabled />
                        </label>
                        <label class="rfm__f">
                            <span>{{ $t('sample_reports.preservation') }}</span>
                            <Input :value="form.preservation ?? '—'" disabled />
                        </label>
                        <label class="rfm__f">
                            <span>{{ $t('sample_reports.tag') }}</span>
                            <Input :value="form.tag ?? '—'" disabled />
                        </label>
                        <label class="rfm__f">
                            <span>{{ $t('sample_reports.sampling_point') }}</span>
                            <Input v-model:value="form.sampling_point" :maxlength="80" />
                        </label>
                        <label class="rfm__f">
                            <span>{{ $t('sample_reports.oil_temp_c') }}</span>
                            <InputNumber v-model:value="form.oil_temp_c" :min="-50" :max="250" style="width:100%" />
                        </label>
                        <label class="rfm__f">
                            <span>{{ $t('sample_reports.equipment_temp_c') }}</span>
                            <InputNumber v-model:value="form.equipment_temp_c" :min="-50" :max="250" style="width:100%" />
                        </label>
                        <label class="rfm__f">
                            <span>{{ $t('sample_reports.ambient_temp_c') }}</span>
                            <InputNumber v-model:value="form.ambient_temp_c" :min="-50" :max="80" style="width:100%" />
                        </label>
                        <label class="rfm__f">
                            <span>{{ $t('sample_reports.relative_humidity') }}</span>
                            <InputNumber v-model:value="form.relative_humidity" :min="0" :max="100" style="width:100%" />
                        </label>
                    </div>

                    <!-- ── Datos de la muestra ─────────────────────────── -->
                    <div class="rfm__band">{{ $t('sample_reports.section_sample') }}</div>
                    <div class="rfm__grid">
                        <label class="rfm__f">
                            <span>{{ $t('sample_reports.code') }}</span>
                            <Input :value="data.report?.code ?? '—'" disabled />
                        </label>
                        <label class="rfm__f">
                            <span>{{ $t('sample_reports.sample') }}</span>
                            <Input :value="data.readonly.sample_code" disabled />
                        </label>
                        <label class="rfm__f">
                            <span>{{ $t('sample_reports.sampled_at') }}</span>
                            <DatePicker
                                :value="asDate(form.sampled_at)"
                                style="width:100%"
                                @update:value="(v) => setDate('sampled_at', v)"
                            />
                        </label>
                        <label class="rfm__f rfm__f--wide">
                            <span>{{ $t('sample_reports.notes') }}</span>
                            <Textarea
                                v-model:value="form.notes"
                                :rows="2"
                                :maxlength="2000"
                                :placeholder="$t('sample_reports.notes_help')"
                            />
                        </label>
                    </div>

                    <!-- ── Análisis ────────────────────────────────────── -->
                    <div class="rfm__band">{{ $t('sample_reports.section_tests') }}</div>
                    <table v-if="data.tests.length > 0" class="rfm__tests">
                        <thead>
                            <tr>
                                <th>{{ $t('sample_reports.test') }}</th>
                                <th class="rfm__tests-col">{{ $t('sample_reports.show_in_report') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="test in data.tests" :key="test.id">
                                <td>
                                    {{ test.name ?? test.code }}
                                    <!-- Marcar una prueba sin validar no la
                                         imprime: el informe publica resultados
                                         firmados. Se dice acá para que nadie
                                         busque después por qué no salió. -->
                                    <span
                                        v-if="!['validated', 'reported'].includes(test.status)"
                                        class="rfm__warn"
                                    >{{ $t('sample_reports.not_validated') }}</span>
                                </td>
                                <td class="rfm__tests-col">
                                    <Checkbox
                                        :checked="(form.tests ?? []).includes(test.id)"
                                        @change="(e) => toggleTest(test.id, e.target.checked)"
                                    />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-else class="rfm__empty">{{ $t('sample_reports.no_tests') }}</p>
                </div>
            </Spin>

            <div class="rfm__foot">
                <Button @click="close">{{ $t('global.cancel') }}</Button>
                <Button type="primary" :loading="saving" :disabled="loading" @click="submit">
                    {{ isEdit ? $t('global.save_changes') : $t('sample_reports.create') }}
                </Button>
            </div>
        </div>
    </Modal>
</template>

<style scoped>
.rfm { display: flex; flex-direction: column; min-height: calc(100vh - 120px); }

.rfm__head { padding: 0 0 12px; border-bottom: 1px solid var(--color-border); }
.rfm__title {
    display: inline-flex; align-items: center; gap: 8px;
    margin: 0; font-size: 1.05rem; font-weight: 600; color: var(--color-text-strong, #32363a);
}
.rfm__sub { display: block; margin-top: 2px; font-size: 0.8125rem; color: var(--color-text-muted); }

.rfm__body { flex: 1; padding: 12px 0 24px; }
.rfm__note { margin-bottom: 12px; }

/* La banda de sección, igual que la del informe impreso: la pantalla y el papel
   se leen con el mismo mapa. */
.rfm__band {
    background: #354a5f; color: #fff;
    font-size: 0.75rem; font-weight: 600; letter-spacing: 0.02em;
    padding: 5px 10px; margin: 18px 0 12px;
}

.rfm__grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
    gap: 12px 16px;
}
.rfm__f { display: flex; flex-direction: column; gap: 4px; min-width: 0; }
.rfm__f > span { font-size: 0.75rem; color: var(--color-text-muted); }
.rfm__f--wide { grid-column: 1 / -1; }

.rfm__tests { width: 100%; border-collapse: collapse; font-size: 0.8125rem; }
.rfm__tests th, .rfm__tests td {
    text-align: left; padding: 7px 10px; border-bottom: 1px solid var(--color-border);
}
.rfm__tests th { font-size: 0.72rem; text-transform: uppercase; color: var(--color-text-muted); }
.rfm__tests-col { width: 160px; text-align: center !important; }
.rfm__warn { display: block; font-size: 0.72rem; color: #b45309; }
.rfm__empty { color: var(--color-text-muted); font-size: 0.8125rem; }

.rfm__foot {
    position: sticky; bottom: 0;
    display: flex; justify-content: flex-end; gap: 10px;
    padding: 12px 0;
    background: var(--color-surface, #fff);
    border-top: 1px solid var(--color-border);
}
</style>
