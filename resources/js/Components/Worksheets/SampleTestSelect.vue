<script setup>
/**
 * Qué muestra es esta fila de la bancada.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ POR QUÉ UN SELECTOR Y NO UN CAMPO DE TEXTO                               │
 * └──────────────────────────────────────────────────────────────────────────┘
 * En el sistema anterior el analista TIPEABA el correlativo y el enlace con la
 * muestra se resolvía después partiendo esa cadena e interpolándola en SQL. Si
 * el formato no coincidía —un espacio, otro guion, el año abreviado— el enlace
 * no se hacía, y el resultado no llegaba nunca al informe del cliente sin que
 * nada avisara.
 *
 * Acá se elige de la lista de pruebas que esta hoja espera, así la fila queda
 * atada a la muestra por clave foránea. De eso dependen tres cosas que ya están
 * construidas: el avance de la muestra, el equipo del resultado y el bloque de
 * condiciones de ensayo del informe.
 *
 * Se muestra el cliente y el equipo junto al código: el analista tiene delante
 * un frasco rotulado, y "2026-0695" no le dice de quién es.
 */
import { computed } from 'vue';
import { Select, SelectOption } from 'ant-design-vue';

const props = defineProps({
    /** [{ id, code, customer, equipment }] — las que esta hoja todavía espera. */
    tests:    { type: Array,  default: () => [] },
    value:    { type: [Number, null], default: null },
    disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['update:value', 'picked']);

const opciones = computed(() => props.tests.map((t) => ({
    value: t.id,
    // Lo que se busca al escribir: el código y también el cliente y el equipo,
    // porque el analista muchas veces sabe de quién es la muestra antes que su
    // número.
    label: [t.code, t.customer, t.equipment].filter(Boolean).join(' · '),
    test:  t,
})));

const elegir = (id) => {
    emit('update:value', id ?? null);
    emit('picked', props.tests.find((t) => t.id === id) ?? null);
};
</script>

<template>
    <Select
        :value="value"
        :disabled="disabled"
        allow-clear
        show-search
        option-filter-prop="label"
        size="small"
        class="sts"
        :placeholder="$t('worksheets.pick_sample')"
        @update:value="elegir"
    >
        <SelectOption
            v-for="opcion in opciones"
            :key="opcion.value"
            :value="opcion.value"
            :label="opcion.label"
        >
            <span class="sts__code">{{ opcion.test.code }}</span>
            <span v-if="opcion.test.customer" class="sts__meta">{{ opcion.test.customer }}</span>
            <span v-if="opcion.test.equipment" class="sts__meta">{{ opcion.test.equipment }}</span>
        </SelectOption>
    </Select>
</template>

<style scoped>
.sts { width: 100%; min-width: 150px; }
.sts__code { font-weight: 600; }
.sts__meta { margin-left: 6px; font-size: 0.75rem; color: var(--color-text-muted); }
</style>
