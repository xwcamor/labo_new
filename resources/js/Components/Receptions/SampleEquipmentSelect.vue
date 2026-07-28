<script setup>
/**
 * De qué equipo se tomó la muestra. Guarda al cambiar.
 *
 * Las opciones vienen del servidor y son SOLO las del cliente de la recepción
 * (`equipment`). No se filtra acá: en el sistema anterior el desplegable
 * filtraba por cliente en el navegador pero cargaba en paralelo los equipos de
 * todos, y el guardado no lo verificaba — alcanzaba un envío directo para
 * colgarle la muestra de un cliente al transformador de otro. El servidor lo
 * vuelve a verificar (`ReceptionService::assignEquipment`); esto es comodidad,
 * no control.
 */
import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { Select, SelectOption } from 'ant-design-vue';

const props = defineProps({
    reception: { type: Object, required: true },
    sample:    { type: Object, required: true },
    equipment: { type: Array,  default: () => [] },
    disabled:  { type: Boolean, default: false },
});

const value = ref(props.sample.equipment_id ?? null);
const saving = ref(false);

// La ficha se recarga entera al guardar (Inertia): el valor local tiene que
// seguir al de la muestra o el desplegable mostraría lo viejo tras un rechazo.
watch(() => props.sample.equipment_id, (fresh) => { value.value = fresh ?? null; });

/** Cómo se nombra un equipo: su nombre, y la placa o serie para desempatar. */
const label = (item) => {
    const extra = item.tag || item.serial;

    return extra ? `${item.name} (${extra})` : item.name;
};

const save = (equipmentId) => {
    router.patch(
        route('lab_management.receptions.samples.equipment', [props.reception.slug, props.sample.slug]),
        { equipment_id: equipmentId ?? null },
        {
            preserveScroll: true,
            onStart:  () => { saving.value = true; },
            onFinish: () => { saving.value = false; },
        },
    );
};
</script>

<template>
    <Select
        v-model:value="value"
        allow-clear
        show-search
        option-filter-prop="label"
        size="small"
        class="rc-eqsel"
        :loading="saving"
        :disabled="disabled || saving"
        :placeholder="$t('receptions.no_equipment')"
        @change="save"
    >
        <SelectOption
            v-for="item in equipment"
            :key="item.id"
            :value="item.id"
            :label="label(item)"
        >
            {{ label(item) }}
        </SelectOption>
    </Select>
</template>

<style scoped>
.rc-eqsel { min-width: 200px; width: 100%; }
</style>
