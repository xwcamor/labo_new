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
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ LA MUESTRA QUE YA ESTÁ EN LA HOJA NO SE PUEDE VOLVER A ELEGIR            │
 * └──────────────────────────────────────────────────────────────────────────┘
 * Una muestra, una fila: dos filas de la misma muestra serían dos resultados
 * oficiales para la misma medición. Eso el servidor ya lo rechazaba, pero
 * recién DESPUÉS de que el analista eligiera, cargara los valores y apretara
 * guardar — el error llegaba tarde y con el trabajo hecho.
 *
 * Ahora la opción aparece en gris y dice que ya está en la hoja. No se
 * ESCONDE: quien busca "2026-0001" y no lo encuentra concluye que la muestra
 * no existe, o que se le perdió el ingreso. Verla tachada le dice la verdad —
 * ya la cargó— y lo manda a la fila que la tiene.
 */
import { computed } from 'vue';
import { Select, SelectOption } from 'ant-design-vue';

const props = defineProps({
    /** [{ id, code, customer, equipment }] — las que esta hoja todavía espera. */
    tests:    { type: Array,  default: () => [] },
    value:    { type: [Number, null], default: null },
    disabled: { type: Boolean, default: false },
    /**
     * El código que la fila tiene GUARDADO, para no perderlo de vista cuando la
     * prueba pedida desapareció (la cambiaron en la recepción, la cancelaron).
     * Antes ese caso hacía caer la celda a un campo de texto libre.
     */
    storedCode: { type: [String, null], default: null },
    /**
     * Las pruebas que YA tiene otra fila de esta hoja. Se ofrecen en gris.
     * La de la fila actual nunca entra acá: si no, la celda no encontraría su
     * propia opción.
     */
    takenIds: { type: Array, default: () => [] },
});

const emit = defineEmits(['update:value', 'picked']);

const tomadas = computed(() => new Set(props.takenIds));

const opciones = computed(() => props.tests.map((t) => ({
    value: t.id,
    // Lo que se busca al escribir: el código y también el cliente y el equipo,
    // porque el analista muchas veces sabe de quién es la muestra antes que su
    // número.
    label: [t.code, t.customer, t.equipment].filter(Boolean).join(' · '),
    test:  t,
    taken: tomadas.value.has(t.id),
})));

/** ¿Queda alguna sin cargar? Cambia lo que dice el marcador de posición. */
const hayLibres = computed(() => opciones.value.some((o) => !o.taken));

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
        :placeholder="!tests.length
            ? $t('worksheets.no_pending_samples')
            : (hayLibres ? $t('worksheets.pick_sample') : $t('worksheets.all_samples_loaded'))"
        :not-found-content="$t('worksheets.no_pending_samples_help')"
        @update:value="elegir"
    >
        <SelectOption
            v-for="opcion in opciones"
            :key="opcion.value"
            :value="opcion.value"
            :label="opcion.label"
            :disabled="opcion.taken"
            :title="opcion.taken ? `${opcion.label} — ${$t('worksheets.already_in_sheet')}` : opcion.label"
        >
            <span class="sts__code" :class="{ 'sts__code--taken': opcion.taken }">{{ opcion.test.code }}</span>
            <span v-if="opcion.test.customer" class="sts__meta">{{ opcion.test.customer }}</span>
            <span v-if="opcion.test.equipment" class="sts__meta">{{ opcion.test.equipment }}</span>
            <!-- Por qué está en gris. Sin esta línea, una opción deshabilitada
                 se lee como un error del sistema. -->
            <span v-if="opcion.taken" class="sts__taken">{{ $t('worksheets.already_in_sheet') }}</span>
        </SelectOption>
    </Select>

    <!-- La fila quedó sin su prueba pedida (la cambiaron en la recepción o la
         cancelaron). Se muestra el código GUARDADO para que se entienda de qué
         fila se trata; antes acá aparecía un campo de texto libre. -->
    <div v-if="storedCode && !value" class="sts__orphan">{{ storedCode }}</div>
</template>

<style scoped>
.sts { width: 100%; min-width: 150px; }
.sts__code { font-weight: 600; }
.sts__meta { margin-left: 6px; font-size: 0.75rem; color: var(--color-text-muted); }
.sts__code--taken { text-decoration: line-through; }
.sts__taken { margin-left: 6px; font-size: 0.72rem; font-style: italic; color: var(--color-text-muted); }
.sts__orphan { margin-top: 4px; font-size: 0.72rem; color: var(--color-text-muted); }
</style>
