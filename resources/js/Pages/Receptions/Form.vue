<script setup>
/**
 * Alta y edición de la cabecera de la recepción.
 *
 * Acá NO se emiten correlativos. Mientras la entrega es un borrador se corrige
 * el cliente, la cantidad de envases o el muestreador sin quemar números; los
 * números se emiten al confirmar, en la ficha, y una sola vez.
 *
 * En una recepción YA CONFIRMADA, el cliente y la fecha de recepción quedan
 * deshabilitados: sus correlativos ya están emitidos y el año de esos números
 * sale de esa fecha, así que cambiarlos dejaría muestras con un número de un
 * ejercicio y un dueño que no corresponden. El servidor los ignora igual — esto
 * es para que se vea, no para que se cumpla.
 */
import { computed, watch } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import {
    Alert, DatePicker, Form, FormItem, Input, InputNumber,
    Select, SelectOption, Switch, Textarea,
} from 'ant-design-vue';
import { InboxOutlined } from '@ant-design/icons-vue';
import dayjs from 'dayjs';

import AppLayout from '@/Layouts/AppLayout.vue';
import SectionHeader from '@/Components/Common/SectionHeader.vue';
import FormFooter from '@/Components/Common/FormFooter.vue';
import { isoDate } from './config/format';

defineOptions({ layout: AppLayout });

const props = defineProps({
    reception:  { type: Object, default: null },
    customers:  { type: Array,  default: () => [] },
    samplers:   { type: Array,  default: () => [] },
    // Quiénes pueden autorizar el ingreso: el catálogo «Personal que autoriza».
    authorizers: { type: Array, default: () => [] },
});

const isEdit = computed(() => !!props.reception);

/** Ya emitió correlativos: cliente y fecha de recepción quedan congelados. */
const isLocked = computed(() => isEdit.value && props.reception.status !== 'draft');

const today = new Date().toISOString().slice(0, 10);

const form = useForm({
    // Sin `code`: el N° de recepción lo genera el servidor al guardar. El
    // campo se muestra deshabilitado, solo para que se sepa que existe.
    service_order: props.reception?.service_order ?? '',
    // El contacto y el usuario final: viven en la recepción y el informe los
    // imprime, pero solo se podían cargar desde el modal del informe, o sea al
    // final. Quien recibe la muestra tiene el correo del cliente delante.
    contact_info:  props.reception?.contact_info ?? '',
    customer_id:   props.reception?.customer_id ?? null,
    sampler_id:    props.reception?.sampler_id ?? null,
    sampler_name:  props.reception?.sampler_name ?? '',
    authorized_by_id: props.reception?.authorized_by_id ?? null,
    received_at:   isoDate(props.reception?.received_at) ?? today,
    due_at:        isoDate(props.reception?.due_at),
    packages:      props.reception?.packages ?? null,
    // Los tres controles del envase arrancan APROBADOS: lo normal es que la
    // muestra llegue bien, y el gesto del operador es APAGAR el que falló —
    // no encender tres switches en cada entrega. En edición se respeta lo
    // guardado.
    container_ok:  isEdit.value ? !!props.reception.container_ok : true,
    volume_ok:     isEdit.value ? !!props.reception.volume_ok : true,
    label_ok:      isEdit.value ? !!props.reception.label_ok : true,
    is_urgent:     !!props.reception?.is_urgent,
    notes:         props.reception?.notes ?? '',
});

/**
 * La fecha comprometida no puede ser anterior a la de recepción, y en vez de
 * dejar elegir y rebotar en el servidor, el calendario directamente no ofrece
 * esos días. La validación del servidor sigue ahí — esto es comodidad.
 */
const disableBeforeReception = (current) => {
    if (!current || !form.received_at) return false;

    return current.isBefore(dayjs(form.received_at), 'day');
};

// Si después de comprometer una fecha se corrige la de recepción hacia
// adelante y la comprometida queda ANTES, se limpia para que se vuelva a
// elegir: dejarla sería mandar al servidor una fecha que el calendario ya
// no permite.
watch(() => form.received_at, (nueva) => {
    if (form.due_at && nueva && dayjs(form.due_at).isBefore(dayjs(nueva), 'day')) {
        form.due_at = null;
    }
});

const submit = () => {
    if (isEdit.value) {
        form.put(route('lab_management.receptions.update', props.reception.slug));

        return;
    }

    form.post(route('lab_management.receptions.store'));
};
</script>

<template>
    <Head :title="isEdit ? $t('receptions.edit_title') : $t('receptions.create_title')" />

    <div class="form-page sap-form">
        <SectionHeader
            :back-href="isEdit
                ? route('lab_management.receptions.show', reception.slug)
                : route('lab_management.receptions.index')"
            :title="isEdit ? $t('receptions.edit_title') : $t('receptions.create_title')"
            :subtitle="$t('receptions.create_subtitle')"
        >
            <template #icon><InboxOutlined /></template>
        </SectionHeader>

        <div class="form-body">
            <Form layout="vertical" @submit.prevent="submit">
                <Alert
                    v-if="form.hasErrors"
                    type="error"
                    show-icon
                    :message="$t('global.fix_marked_fields')"
                    class="rc-form__alert"
                />

                <Alert
                    v-if="isLocked"
                    type="info"
                    show-icon
                    class="rc-form__alert"
                    :message="$t('receptions.errors.confirmed_no_edit')"
                />

                <h2 class="form-section-title">{{ $t('receptions.section_header') }}</h2>
                <div class="form-grid">
                    <!-- Solo al editar: el número ya existe. En el alta no se
                         muestra — no hay nada que llenar; el sistema lo asigna
                         al guardar. -->
                    <FormItem
                        v-if="isEdit"
                        :label="$t('receptions.code')"
                        :extra="$t('receptions.code_help')"
                    >
                        <Input :value="reception?.code ?? ''" size="large" disabled />
                    </FormItem>

                    <FormItem
                        :label="$t('receptions.service_order')"
                        :validate-status="form.errors.service_order ? 'error' : ''"
                        :help="form.errors.service_order"
                    >
                        <Input v-model:value="form.service_order" size="large" :maxlength="60" />
                    </FormItem>

                    <FormItem
                        :label="$t('receptions.customer')"
                        required
                        :extra="$t('receptions.customer_help')"
                        :validate-status="form.errors.customer_id ? 'error' : ''"
                        :help="form.errors.customer_id"
                    >
                        <Select
                            v-model:value="form.customer_id"
                            size="large"
                            show-search
                            option-filter-prop="label"
                            :disabled="isLocked"
                            :placeholder="$t('receptions.customer')"
                        >
                            <SelectOption
                                v-for="customer in customers"
                                :key="customer.id"
                                :value="customer.id"
                                :label="customer.name"
                            >
                                {{ customer.name }}
                            </SelectOption>
                        </Select>
                    </FormItem>

                    <FormItem
                        :label="$t('receptions.contact_info')"
                        :extra="$t('receptions.contact_info_help')"
                        :validate-status="form.errors.contact_info ? 'error' : ''"
                        :help="form.errors.contact_info"
                    >
                        <Input v-model:value="form.contact_info" size="large" :maxlength="190" />
                    </FormItem>

                    <!-- El muestreador no siempre es alguien del laboratorio: puede
                         ser personal del cliente o un tercero. Por eso hay las dos
                         formas y ninguna obliga a dar de alta un usuario. Es
                         OBLIGATORIO por cualquiera de las dos: del catálogo o el
                         nombre suelto del externo. -->
                    <FormItem
                        :label="$t('receptions.sampler')"
                        required
                        :extra="$t('receptions.sampler_help')"
                        :validate-status="form.errors.sampler_id ? 'error' : ''"
                        :help="form.errors.sampler_id"
                    >
                        <Select
                            v-model:value="form.sampler_id"
                            size="large"
                            allow-clear
                            show-search
                            option-filter-prop="label"
                            :placeholder="$t('receptions.sampler')"
                        >
                            <SelectOption
                                v-for="user in samplers"
                                :key="user.id"
                                :value="user.id"
                                :label="user.name"
                            >
                                {{ user.name }}
                            </SelectOption>
                        </Select>
                    </FormItem>

                    <FormItem
                        :label="$t('receptions.sampler_name')"
                        :validate-status="form.errors.sampler_name ? 'error' : ''"
                        :help="form.errors.sampler_name"
                    >
                        <Input v-model:value="form.sampler_name" size="large" :maxlength="120" />
                    </FormItem>

                    <!-- Quién autoriza el ingreso. En el sistema anterior era
                         obligatorio y su firma salía en el acta de recepción;
                         la lista sale del catálogo «Personal que autoriza». -->
                    <FormItem
                        :label="$t('receptions.authorized_by')"
                        required
                        :extra="$t('receptions.authorized_by_help')"
                        :validate-status="form.errors.authorized_by_id ? 'error' : ''"
                        :help="form.errors.authorized_by_id"
                    >
                        <Select
                            v-model:value="form.authorized_by_id"
                            size="large"
                            show-search
                            option-filter-prop="label"
                            :placeholder="$t('receptions.authorized_by')"
                        >
                            <SelectOption
                                v-for="person in authorizers"
                                :key="person.id"
                                :value="person.id"
                                :label="person.name"
                            >
                                {{ person.name }}
                            </SelectOption>
                        </Select>
                        <!-- Sin habilitados no hay nada que elegir: se dice
                             DÓNDE se arregla, no solo que falta. -->
                        <Alert
                            v-if="!authorizers.length"
                            type="warning"
                            show-icon
                            class="rc-form__note"
                            :message="$t('receptions.authorized_by_empty')"
                        />
                    </FormItem>

                    <FormItem
                        :label="$t('receptions.received_at')"
                        required
                        :extra="$t('receptions.received_at_help')"
                        :validate-status="form.errors.received_at ? 'error' : ''"
                        :help="form.errors.received_at"
                    >
                        <DatePicker
                            autocomplete="off"
                            v-model:value="form.received_at"
                            size="large"
                            value-format="YYYY-MM-DD"
                            style="width: 100%"
                            :disabled="isLocked"
                        />
                    </FormItem>

                    <!-- Los días anteriores a la recepción ni se ofrecen: mejor
                         que dejar elegir y rebotar en el servidor. -->
                    <FormItem
                        :label="$t('receptions.due_at')"
                        required
                        :extra="$t('receptions.due_at_help')"
                        :validate-status="form.errors.due_at ? 'error' : ''"
                        :help="form.errors.due_at"
                    >
                        <DatePicker
                            autocomplete="off"
                            v-model:value="form.due_at"
                            size="large"
                            value-format="YYYY-MM-DD"
                            style="width: 100%"
                            :disabled-date="disableBeforeReception"
                        />
                    </FormItem>

                    <FormItem
                        :label="$t('receptions.packages')"
                        required
                        :extra="$t('receptions.packages_help')"
                        :validate-status="form.errors.packages ? 'error' : ''"
                        :help="form.errors.packages"
                    >
                        <InputNumber
                            v-model:value="form.packages"
                            size="large"
                            style="width: 100%"
                            :min="1"
                            :max="9999"
                        />
                    </FormItem>
                </div>

                <h2 class="form-section-title form-section-title--spaced">{{ $t('receptions.section_check') }}</h2>
                <div class="form-grid">
                    <!-- Los cuatro switches comparten la fila ancha: son una sola
                         verificación al recibir y en fila se comparan de un vistazo.
                         Los tres del envase arrancan ENCENDIDOS (lo normal es que
                         la muestra llegue bien; el gesto es apagar el que falló);
                         Urgente arranca apagado. -->
                    <FormItem class="form-grid__wide">
                        <p class="rc-form__hint">{{ $t('receptions.check_help') }}</p>
                        <div class="rc-form__checks">
                            <label class="rc-form__switch">
                                <Switch v-model:checked="form.container_ok" />
                                {{ $t('receptions.container_ok') }}
                            </label>
                            <label class="rc-form__switch">
                                <Switch v-model:checked="form.volume_ok" />
                                {{ $t('receptions.volume_ok') }}
                            </label>
                            <label class="rc-form__switch">
                                <Switch v-model:checked="form.label_ok" />
                                {{ $t('receptions.label_ok') }}
                            </label>
                            <label class="rc-form__switch rc-form__switch--urgent">
                                <Switch v-model:checked="form.is_urgent" />
                                {{ $t('receptions.is_urgent') }}
                            </label>
                        </div>
                    </FormItem>

                    <FormItem
                        class="form-grid__wide"
                        :label="$t('receptions.notes')"
                        :validate-status="form.errors.notes ? 'error' : ''"
                        :help="form.errors.notes"
                    >
                        <Textarea v-model:value="form.notes" :rows="3" :maxlength="2000" show-count />
                    </FormItem>
                </div>

                <FormFooter
                    :cancel-href="isEdit
                        ? route('lab_management.receptions.show', reception.slug)
                        : route('lab_management.receptions.index')"
                    :is-edit="isEdit"
                    :processing="form.processing"
                    create-label-key="receptions.new"
                    floating
                />
            </Form>
        </div>
    </div>
</template>

<style scoped>
.rc-form__alert { margin-bottom: 16px; }
.rc-form__note { margin-top: 8px; }
.rc-form__hint { font-size: 0.8125rem; color: var(--color-text-muted); margin: 0 0 10px; }
/* En fila con salto: los cuatro switches se leen como una sola verificación;
   en pantallas angostas caen de a uno sin desbordar. */
.rc-form__checks { display: flex; flex-wrap: wrap; gap: 10px 28px; align-items: center; }
/* El label envuelve al switch para que clickear el TEXTO también conmute. */
.rc-form__switch {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-size: 0.875rem;
    color: var(--color-text);
    cursor: pointer;
    user-select: none;
}
/* Urgente encendido se distingue: no es un control del envase, es una alerta. */
.rc-form__switch--urgent :deep(.ant-switch-checked) { background: #cf1322; }
</style>
