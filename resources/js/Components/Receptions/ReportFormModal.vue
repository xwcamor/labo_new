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
    Alert, Button, DatePicker, Input, InputNumber, Modal, Select,
    Spin, Switch, Tooltip,
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
            tests: json.tests.filter((x) => x.is_visible).map((x) => x.id),
        };
        errores.value = [];
    } finally {
        loading.value = false;
    }
};

watch(() => props.open, (abierto) => { if (abierto) cargar(); });

/**
 * Las opciones de una lista, MÁS lo que ya tenga cargado la muestra.
 *
 * Estos cuatro campos eran texto libre, así que hay filas históricas con
 * valores que no están en el catálogo («Valvula inferior», «2500 galones»). Un
 * desplegable pelado los mostraría vacíos y el primer guardado los borraría sin
 * que nadie lo note. El valor actual entra siempre a la lista: se ve, se
 * conserva, y el operador decide si lo cambia por el del catálogo.
 */
const opciones = (kind, actual) => {
    const lista = data.value?.catalogs?.[kind] ?? [];

    if (actual && !lista.some((o) => o.value === actual)) {
        return [{ value: actual, label: actual }, ...lista];
    }

    return lista;
};

const toggleTest = (id, checked) => {
    const set = new Set(form.value.tests ?? []);
    if (checked) set.add(id); else set.delete(id);
    form.value.tests = [...set];
};

/** Las fechas viajan como texto: el servidor no tiene por qué saber de dayjs. */
const asDate = (v) => (v ? dayjs(v) : null);
const setDate = (campo, v) => { form.value[campo] = v ? dayjs(v).format('YYYY-MM-DD') : null; };

// ── El orden de las fechas: recepción ≤ emisión ≤ entrega ────────────────
// El calendario DESHABILITA lo que no puede ser, en vez de dejar elegir y
// rebotar al guardar: no se emite antes de que el frasco entrara, y no se
// entrega antes de emitir. Si la emisión se mueve para adelante y deja a la
// entrega atrás, la entrega se limpia para volver a elegirla.
const antesDeRecepcion = (d) => !!form.value.received_at
    && d.isBefore(dayjs(form.value.received_at), 'day');

const antesDeEmision = (d) => !!form.value.issued_at
    && d.isBefore(dayjs(form.value.issued_at), 'day');

const setEmision = (v) => {
    setDate('issued_at', v);

    if (form.value.delivered_at && v && dayjs(form.value.delivered_at).isBefore(dayjs(v), 'day')) {
        form.value.delivered_at = null;
    }
};

/** Lo que rebotó del servidor, visible arriba del formulario. */
const errores = ref([]);

const submit = () => {
    saving.value = true;

    const url = isEdit.value
        ? route('lab_management.sample_reports.update', props.report.slug)
        : route('lab_management.sample_reports.store', props.sample.slug);

    const method = isEdit.value ? 'put' : 'post';

    router[method](url, form.value, {
        preserveScroll: true,
        onSuccess: () => { close(); emit('saved'); },
        onError:   (errs) => { errores.value = Object.values(errs ?? {}).filter(Boolean); },
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

                    <!-- ── Referencia ──────────────────────────────────────
                         Dos filas fijas (pedido del laboratorio, 2026-08-03):
                         la primera con los datos de la referencia y quién
                         extrajo la muestra al final; la segunda con las TRES
                         fechas juntas —se leen como una línea de tiempo:
                         recepción ≤ emisión ≤ entrega— y el usuario final. -->
                    <div class="rfm__band">{{ $t('sample_reports.section_reference') }}</div>
                    <Alert
                        v-for="(err, i) in errores"
                        :key="i"
                        type="error"
                        show-icon
                        class="rfm__note"
                        :message="err"
                    />
                    <div class="rfm__grid rfm__grid--5">
                        <label class="rfm__f">
                            <span>{{ $t('sample_reports.service_order') }} <b class="rfm__req">*</b></span>
                            <Input v-model:value="form.service_order" :maxlength="60" />
                        </label>
                        <label class="rfm__f">
                            <span>{{ $t('sample_reports.sampling_reason') }} <b class="rfm__req">*</b></span>
                            <Select
                                v-model:value="form.sampling_reason"
                                show-search
                                :placeholder="$t('sample_reports.pick_one')"
                                :options="opciones('sampling_reason', form.sampling_reason)"
                            />
                        </label>
                        <label class="rfm__f">
                            <span>{{ $t('sample_reports.contact_info') }} <b class="rfm__req">*</b></span>
                            <Input v-model:value="form.contact_info" :maxlength="1000" />
                        </label>
                        <label class="rfm__f">
                            <span>{{ $t('sample_reports.description') }} <b class="rfm__req">*</b></span>
                            <Input v-model:value="form.description" :maxlength="1000" />
                        </label>
                        <label class="rfm__f">
                            <span>{{ $t('sample_reports.sampler') }}</span>
                            <!-- Quien extrajo la muestra se declara UNA vez, en
                                 la recepción, y de ahí lo heredan todas sus
                                 muestras: la cuadrilla que va a la subestación
                                 es la misma para toda la entrega. Editarlo acá
                                 cambiaría el de la entrega entera sin decirlo. -->
                            <Tooltip :title="$t('sample_reports.sampler_help')">
                                <Input :value="form.sampler ?? '—'" disabled />
                            </Tooltip>
                        </label>
                    </div>
                    <div class="rfm__grid rfm__grid--4">
                        <label class="rfm__f">
                            <span>{{ $t('sample_reports.received_at') }}</span>
                            <Input :value="form.received_at ?? '—'" disabled />
                        </label>
                        <label class="rfm__f">
                            <span>{{ $t('sample_reports.issued_at') }} <b class="rfm__req">*</b></span>
                            <DatePicker
                                :value="asDate(form.issued_at)"
                                :disabled-date="antesDeRecepcion"
                                style="width:100%"
                                @update:value="setEmision"
                            />
                        </label>
                        <label class="rfm__f">
                            <span>{{ $t('sample_reports.delivered_at') }} <b class="rfm__req">*</b></span>
                            <DatePicker
                                :value="asDate(form.delivered_at)"
                                :disabled-date="antesDeEmision"
                                style="width:100%"
                                @update:value="(v) => setDate('delivered_at', v)"
                            />
                        </label>
                        <label class="rfm__f">
                            <span>{{ $t('sample_reports.end_user') }} <b class="rfm__req">*</b></span>
                            <Tooltip :title="$t('sample_reports.end_user_help')">
                                <Input v-model:value="form.end_user" :maxlength="255" />
                            </Tooltip>
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
                            <Select
                                v-model:value="form.oil_brand"
                                show-search
                                allow-clear
                                :placeholder="$t('sample_reports.pick_one')"
                                :options="opciones('oil_brand', form.oil_brand)"
                            />
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
                            <!-- La unidad va PEGADA al número, igual que en la
                                 ficha del equipo. Sin ella «2500» no dice nada, y
                                 escribirla adentro del mismo campo es lo que dejó
                                 «2500 gal», «2500 galones» y «2500Gal». -->
                            <InputNumber v-model:value="form.oil_volume" :min="0" style="width:100%">
                                <template #addonAfter>
                                    <Select
                                        v-model:value="form.oil_volume_unit"
                                        style="width:78px"
                                        allow-clear
                                        :options="opciones('volume_unit', form.oil_volume_unit)"
                                    />
                                </template>
                            </InputNumber>
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
                            <Select
                                v-model:value="form.sampling_point"
                                show-search
                                allow-clear
                                :placeholder="$t('sample_reports.pick_one')"
                                :options="opciones('sampling_point', form.sampling_point)"
                            />
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
                            <span>{{ $t('sample_reports.sampled_at') }} <b class="rfm__req">*</b></span>
                            <DatePicker
                                :value="asDate(form.sampled_at)"
                                style="width:100%"
                                @update:value="(v) => setDate('sampled_at', v)"
                            />
                        </label>
                        <!-- Sin «Observaciones internas»: no existía en el
                             sistema anterior y era un campo más para llenar
                             que no salía en ningún papel. -->
                    </div>

                    <!-- ── Análisis ──────────────────────────────────────
                         El interruptor va AL INICIO de la fila, pegado al
                         nombre (como la tabla sin bordes del sistema
                         anterior): con la casilla sola a la derecha, el ojo
                         tenía que cruzar toda la pantalla para saber de qué
                         prueba era cada check. -->
                    <div class="rfm__band">{{ $t('sample_reports.section_tests') }}</div>
                    <table v-if="data.tests.length > 0" class="rfm__tests">
                        <thead>
                            <tr>
                                <th class="rfm__tests-sw">{{ $t('sample_reports.show_in_report') }}</th>
                                <th>{{ $t('sample_reports.test') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="test in data.tests" :key="test.id">
                                <td class="rfm__tests-sw">
                                    <Switch
                                        size="small"
                                        :checked="(form.tests ?? []).includes(test.id)"
                                        @change="(v) => toggleTest(test.id, v)"
                                    />
                                </td>
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
/* Las dos filas fijas de Referencia. En pantalla angosta caen a la grilla
   fluida de siempre: cinco columnas en un teléfono serían ilegibles. */
.rfm__grid--5 { grid-template-columns: repeat(5, 1fr); margin-bottom: 12px; }
.rfm__grid--4 { grid-template-columns: repeat(4, 1fr); }
@media (max-width: 900px) {
    .rfm__grid--5, .rfm__grid--4 { grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); }
}
.rfm__f { display: flex; flex-direction: column; gap: 4px; min-width: 0; }
.rfm__f > span { font-size: 0.75rem; color: var(--color-text-muted); }
.rfm__f--wide { grid-column: 1 / -1; }
.rfm__req { color: var(--color-input-error, #c8281d); font-weight: 600; }

.rfm__tests { width: 100%; border-collapse: collapse; font-size: 0.8125rem; }
.rfm__tests th, .rfm__tests td {
    text-align: left; padding: 7px 10px; border-bottom: 1px solid var(--color-border);
}
.rfm__tests th { font-size: 0.72rem; text-transform: uppercase; color: var(--color-text-muted); }
.rfm__tests-sw { width: 150px; text-align: center !important; }
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
