<script setup>
import { computed, ref, watch } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import {
    Form, FormItem, Input, Switch, Space, Alert, Select, SelectOption, InputNumber,
} from 'ant-design-vue';
import { ApartmentOutlined } from '@ant-design/icons-vue';

import AppLayout from '@/Layouts/AppLayout.vue';
import SectionHeader from '@/Components/Common/SectionHeader.vue';
import FormFooter from '@/Components/Common/FormFooter.vue';

defineOptions({ layout: AppLayout });

/**
 * Alta y edición de un equipo.
 *
 * Lo que este formulario preguntaba antes: nombre, un CÓDIGO que no existe como
 * columna —guardarlo reventaba con "no existe la columna «code»"— y ocho
 * números sueltos. No preguntaba DE QUIÉN ES EL EQUIPO, así que todo lo que se
 * cargaba desde acá quedaba sin cliente, y un equipo sin cliente no aparece en
 * ninguna recepción: la recepción solo ofrece los del cliente de la entrega.
 *
 * Los tres desplegables de la jerarquía se encadenan: ubicación → área →
 * subestación. Se piden juntos al elegir el cliente (una respuesta anidada) en
 * vez de un viaje por nivel.
 */
const props = defineProps({
    equipment:       { type: Object, default: null },
    customers:       { type: Array,  default: () => [] },
    equipmentTypes:  { type: Array,  default: () => [] },
    oilTypes:        { type: Array,  default: () => [] },
    brands:          { type: Array,  default: () => [] },
    tapChangerTypes: { type: Array,  default: () => [] },
    preservations:   { type: Array,  default: () => [] },
    oilVolumeUnits:  { type: Array,  default: () => [] },
    serviceStates:   { type: Array,  default: () => [] },
    hierarchy:       { type: Array,  default: () => [] },
});

const isEdit = computed(() => !!props.equipment);

const form = useForm({
    name:   props.equipment?.name ?? '',
    serial: props.equipment?.serial ?? '',
    tag:    props.equipment?.tag ?? '',

    customer_id:            props.equipment?.customer_id ?? null,
    customer_location_id:   props.equipment?.customer_location_id ?? null,
    customer_area_id:       props.equipment?.customer_area_id ?? null,
    customer_substation_id: props.equipment?.customer_substation_id ?? null,

    equipment_type_id:           props.equipment?.equipment_type_id ?? null,
    oil_type_id:                 props.equipment?.oil_type_id ?? null,
    brand_id:                    props.equipment?.brand_id ?? null,
    tap_changer_type_id:         props.equipment?.tap_changer_type_id ?? null,
    transformer_preservation_id: props.equipment?.transformer_preservation_id ?? null,

    voltage_kv_hv:    props.equipment?.voltage_kv_hv ?? null,
    voltage_kv_lv:    props.equipment?.voltage_kv_lv ?? null,
    power_mva:        props.equipment?.power_mva ?? null,
    phases:           props.equipment?.phases ?? null,
    manufacture_year: props.equipment?.manufacture_year ?? null,
    oil_volume:       props.equipment?.oil_volume ?? null,
    oil_volume_unit:  props.equipment?.oil_volume_unit ?? null,
    service_state:    props.equipment?.service_state ?? null,
    external_ref:     props.equipment?.external_ref ?? '',

    is_active: props.equipment?.is_active ?? true,
});

// ── Jerarquía encadenada ────────────────────────────────────────────────
// Al editar llega ya cargada, para que los tres desplegables abran con su
// valor puesto en vez de vacíos esperando un viaje al servidor.
const hierarchy = ref(props.hierarchy ?? []);
const loadingHierarchy = ref(false);

const areas = computed(
    () => hierarchy.value.find((u) => u.id === form.customer_location_id)?.areas ?? [],
);
const substations = computed(
    () => areas.value.find((a) => a.id === form.customer_area_id)?.substations ?? [],
);

watch(() => form.customer_id, async (nuevo, viejo) => {
    // En la carga inicial de la edición no hay nada que limpiar ni que pedir.
    if (viejo === undefined) return;

    if (viejo !== null && nuevo !== viejo) {
        // Cambiar de cliente invalida los tres niveles: dejarlos puestos es
        // cómo un equipo termina colgado de la subestación de otra empresa.
        form.customer_location_id = null;
        form.customer_area_id = null;
        form.customer_substation_id = null;
    }

    if (!nuevo) { hierarchy.value = []; return; }

    loadingHierarchy.value = true;
    try {
        const r = await fetch(route('business_management.equipment.hierarchy', nuevo), {
            headers: { Accept: 'application/json' },
        });
        hierarchy.value = r.ok ? await r.json() : [];
    } finally {
        loadingHierarchy.value = false;
    }
});

watch(() => form.customer_location_id, (nuevo, viejo) => {
    if (viejo !== undefined && nuevo !== viejo) {
        form.customer_area_id = null;
        form.customer_substation_id = null;
    }
});

watch(() => form.customer_area_id, (nuevo, viejo) => {
    if (viejo !== undefined && nuevo !== viejo) form.customer_substation_id = null;
});

// El desplegable de Ant no filtra por el contenido de la opción en esta
// versión: hay que darle el texto en `label` y filtrar contra eso.
const filtrar = (input, option) =>
    (option.label ?? '').toLowerCase().includes(input.toLowerCase());

const submit = () => {
    if (isEdit.value) {
        form.put(route('business_management.equipment.update', props.equipment.slug));
    } else {
        form.post(route('business_management.equipment.store'));
    }
};
</script>

<template>
    <Head :title="isEdit ? $t('global.edit') + ' — ' + $t('equipment.singular') : $t('equipment.new')" />

    <div class="form-page sap-form">
        <SectionHeader
            :back-href="route('business_management.equipment.index')"
            :title="isEdit ? $t('global.edit') + ' ' + $t('equipment.record') : $t('equipment.new')"
            :subtitle="isEdit ? equipment.name : $t('equipment.create_subtitle')"
        >
            <template #icon><ApartmentOutlined /></template>
        </SectionHeader>

        <div class="form-body">
            <!-- `layout="vertical"` + `.form-grid`: el rótulo va ARRIBA del
                 campo y los campos se acomodan en varias columnas. Un equipo
                 tiene veinte datos; con el rótulo a la izquierda y uno por fila
                 —el patrón del resto de los formularios, pensado para catálogos
                 de cuatro campos— la página se iba a dos pantallas y media. -->
            <Form layout="vertical" @submit.prevent="submit">
                <Alert
                    v-if="form.hasErrors && Object.keys(form.errors).length > 0"
                    type="error"
                    show-icon
                    :message="$t('global.fix_marked_fields')"
                    class="mb-4"
                />

                <h2 class="form-section-title">{{ $t('equipment.section_owner') }}</h2>
                <div class="form-grid">
                    <FormItem
                        :label="$t('equipment.customer')"
                        :tooltip="$t('equipment.customer_help')"
                        required
                        :validate-status="form.errors.customer_id ? 'error' : ''"
                        :help="form.errors.customer_id"
                    >
                        <Select v-model:value="form.customer_id" show-search allow-clear :filter-option="filtrar" :loading="loadingHierarchy">
                            <SelectOption v-for="c in customers" :key="c.id" :value="c.id" :label="c.name">{{ c.name }}</SelectOption>
                        </Select>
                    </FormItem>
                    <FormItem
                        :label="$t('equipment.location')"
                        :validate-status="form.errors.customer_location_id ? 'error' : ''"
                        :help="form.errors.customer_location_id"
                    >
                        <Select
                            v-model:value="form.customer_location_id"
                            show-search allow-clear
                            :filter-option="filtrar"
                            :disabled="!form.customer_id"
                            :placeholder="form.customer_id ? '' : $t('equipment.location_placeholder')"
                        >
                            <SelectOption v-for="o in hierarchy" :key="o.id" :value="o.id" :label="o.name">{{ o.name }}</SelectOption>
                        </Select>
                    </FormItem>
                    <FormItem
                        :label="$t('equipment.area')"
                        :validate-status="form.errors.customer_area_id ? 'error' : ''"
                        :help="form.errors.customer_area_id"
                    >
                        <Select
                            v-model:value="form.customer_area_id"
                            show-search allow-clear
                            :filter-option="filtrar"
                            :disabled="!form.customer_location_id"
                            :placeholder="form.customer_location_id ? '' : $t('equipment.area_placeholder')"
                        >
                            <SelectOption v-for="o in areas" :key="o.id" :value="o.id" :label="o.name">{{ o.name }}</SelectOption>
                        </Select>
                    </FormItem>
                    <FormItem
                        :label="$t('equipment.substation')"
                        :validate-status="form.errors.customer_substation_id ? 'error' : ''"
                        :help="form.errors.customer_substation_id"
                    >
                        <Select
                            v-model:value="form.customer_substation_id"
                            show-search allow-clear
                            :filter-option="filtrar"
                            :disabled="!form.customer_area_id"
                            :placeholder="form.customer_area_id ? '' : $t('equipment.substation_placeholder')"
                        >
                            <SelectOption v-for="o in substations" :key="o.id" :value="o.id" :label="o.name">{{ o.name }}</SelectOption>
                        </Select>
                    </FormItem>
                </div>

                <h2 class="form-section-title form-section-title--spaced">{{ $t('equipment.section_identity') }}</h2>
                <div class="form-grid">
                    <FormItem
                        class="form-grid__wide"
                        :label="$t('equipment.name')"
                        :tooltip="$t('equipment.name_help')"
                        required
                        :validate-status="form.errors.name ? 'error' : ''"
                        :help="form.errors.name"
                    >
                        <Input v-model:value="form.name" :maxlength="255" autofocus :placeholder="$t('equipment.name_placeholder')" />
                    </FormItem>
                    <FormItem
                        :label="$t('equipment.serial')"
                        :tooltip="$t('equipment.serial_help')"
                        :validate-status="form.errors.serial ? 'error' : ''"
                        :help="form.errors.serial"
                    >
                        <Input v-model:value="form.serial" :maxlength="255" />
                    </FormItem>
                    <FormItem
                        :label="$t('equipment.tag')"
                        :tooltip="$t('equipment.tag_help')"
                        :validate-status="form.errors.tag ? 'error' : ''"
                        :help="form.errors.tag"
                    >
                        <Input v-model:value="form.tag" :maxlength="255" />
                    </FormItem>
                </div>

                <h2 class="form-section-title form-section-title--spaced">{{ $t('equipment.section_what') }}</h2>
                <div class="form-grid">
                    <FormItem
                        :label="$t('equipment.equipment_type')"
                        :tooltip="$t('equipment.equipment_type_help')"
                        :validate-status="form.errors.equipment_type_id ? 'error' : ''"
                        :help="form.errors.equipment_type_id"
                    >
                        <Select v-model:value="form.equipment_type_id" show-search allow-clear :filter-option="filtrar">
                            <SelectOption v-for="t in equipmentTypes" :key="t.id" :value="t.id" :label="t.name">{{ t.name }}</SelectOption>
                        </Select>
                    </FormItem>
                    <FormItem
                        :label="$t('equipment.oil_type')"
                        :validate-status="form.errors.oil_type_id ? 'error' : ''"
                        :help="form.errors.oil_type_id"
                    >
                        <Select v-model:value="form.oil_type_id" show-search allow-clear :filter-option="filtrar">
                            <SelectOption v-for="o in oilTypes" :key="o.id" :value="o.id" :label="o.name">{{ o.name }}</SelectOption>
                        </Select>
                    </FormItem>
                    <FormItem
                        :label="$t('equipment.brand')"
                        :validate-status="form.errors.brand_id ? 'error' : ''"
                        :help="form.errors.brand_id"
                    >
                        <Select v-model:value="form.brand_id" show-search allow-clear :filter-option="filtrar">
                            <SelectOption v-for="b in brands" :key="b.id" :value="b.id" :label="b.name">{{ b.name }}</SelectOption>
                        </Select>
                    </FormItem>
                    <FormItem
                        :label="$t('equipment.tap_changer_type')"
                        :validate-status="form.errors.tap_changer_type_id ? 'error' : ''"
                        :help="form.errors.tap_changer_type_id"
                    >
                        <Select v-model:value="form.tap_changer_type_id" show-search allow-clear :filter-option="filtrar">
                            <SelectOption v-for="t in tapChangerTypes" :key="t.id" :value="t.id" :label="t.name">{{ t.name }}</SelectOption>
                        </Select>
                    </FormItem>
                    <FormItem
                        :label="$t('equipment.preservation')"
                        :validate-status="form.errors.transformer_preservation_id ? 'error' : ''"
                        :help="form.errors.transformer_preservation_id"
                    >
                        <Select v-model:value="form.transformer_preservation_id" show-search allow-clear :filter-option="filtrar">
                            <SelectOption v-for="p in preservations" :key="p.id" :value="p.id" :label="p.name">{{ p.name }}</SelectOption>
                        </Select>
                    </FormItem>
                    <FormItem
                        :label="$t('equipment.service_state')"
                        :validate-status="form.errors.service_state ? 'error' : ''"
                        :help="form.errors.service_state"
                    >
                        <Select v-model:value="form.service_state" allow-clear>
                            <SelectOption v-for="e in serviceStates" :key="e" :value="e" :label="$t('equipment.service_states.' + e)">
                                {{ $t('equipment.service_states.' + e) }}
                            </SelectOption>
                        </Select>
                    </FormItem>
                </div>

                <h2 class="form-section-title form-section-title--spaced">{{ $t('equipment.section_physical') }}</h2>
                <div class="form-grid">
                    <FormItem
                        :label="$t('equipment.voltage_kv_hv')"
                        :validate-status="form.errors.voltage_kv_hv ? 'error' : ''"
                        :help="form.errors.voltage_kv_hv"
                    >
                        <InputNumber v-model:value="form.voltage_kv_hv" :min="0" style="width: 100%" />
                    </FormItem>
                    <FormItem
                        :label="$t('equipment.voltage_kv_lv')"
                        :validate-status="form.errors.voltage_kv_lv ? 'error' : ''"
                        :help="form.errors.voltage_kv_lv"
                    >
                        <InputNumber v-model:value="form.voltage_kv_lv" :min="0" style="width: 100%" />
                    </FormItem>
                    <FormItem
                        :label="$t('equipment.power_mva')"
                        :validate-status="form.errors.power_mva ? 'error' : ''"
                        :help="form.errors.power_mva"
                    >
                        <InputNumber v-model:value="form.power_mva" :min="0" style="width: 100%" />
                    </FormItem>
                    <FormItem
                        :label="$t('equipment.phases')"
                        :validate-status="form.errors.phases ? 'error' : ''"
                        :help="form.errors.phases"
                    >
                        <InputNumber v-model:value="form.phases" :min="1" :max="3" style="width: 100%" />
                    </FormItem>
                    <FormItem
                        :label="$t('equipment.manufacture_year')"
                        :validate-status="form.errors.manufacture_year ? 'error' : ''"
                        :help="form.errors.manufacture_year"
                    >
                        <InputNumber v-model:value="form.manufacture_year" :min="1900" :max="new Date().getFullYear() + 1" style="width: 100%" />
                    </FormItem>
                    <FormItem
                        :label="$t('equipment.oil_volume')"
                        :validate-status="form.errors.oil_volume ? 'error' : ''"
                        :help="form.errors.oil_volume"
                    >
                        <!-- La unidad va PEGADA al número. En el sistema anterior
                             venía escrita dentro del mismo campo ("2500 gal",
                             "2500 galones") y comparar dos equipos exigía
                             adivinar cuál era cuál. -->
                        <InputNumber v-model:value="form.oil_volume" :min="0" style="width: 100%">
                            <template #addonAfter>
                                <Select v-model:value="form.oil_volume_unit" style="width: 72px" allow-clear>
                                    <SelectOption v-for="u in oilVolumeUnits" :key="u" :value="u">{{ u }}</SelectOption>
                                </Select>
                            </template>
                        </InputNumber>
                    </FormItem>
                    <FormItem
                        :label="$t('equipment.external_ref')"
                        :validate-status="form.errors.external_ref ? 'error' : ''"
                        :help="form.errors.external_ref"
                    >
                        <Input v-model:value="form.external_ref" :maxlength="255" />
                    </FormItem>
                    <FormItem
                        v-if="isEdit"
                        :label="$t('equipment.is_active')"
                        :tooltip="$t('equipment.is_active_help')"
                        :validate-status="form.errors.is_active ? 'error' : ''"
                        :help="form.errors.is_active"
                    >
                        <Space>
                            <Switch v-model:checked="form.is_active" />
                            <span class="state-label">
                                {{ form.is_active ? $t('global.active') : $t('global.inactive') }}
                            </span>
                        </Space>
                    </FormItem>
                </div>

                <FormFooter
                    :cancel-href="route('business_management.equipment.index')"
                    :is-edit="isEdit"
                    :processing="form.processing"
                    floating
                />
            </Form>
        </div>
    </div>
</template>

<style scoped>
.state-label {
    font-size: 0.875rem;
    color: var(--color-text);
    font-weight: 500;
}
.mb-4 { margin-bottom: 16px; }
</style>
