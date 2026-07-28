<script setup>
/**
 * De qué equipo del cliente es la muestra.
 *
 * Este enlace es lo que hace que el ensayo exista para el cliente: sin él,
 * `ResultMaterializer` saltea la fila y el valor medido nunca llega al informe
 * ni a la tendencia del equipo. Por eso la celda no queda muda cuando está
 * vacía: avisa, aunque no obligue.
 *
 * En el sistema viejo el enlace se resolvía por texto —el analista escribía el
 * nombre del equipo y algo lo intentaba emparejar—, y cuando el emparejamiento
 * fallaba el resultado desaparecía del informe sin que nadie se enterara. La
 * diferencia aquí no es impedirlo: es que se vea.
 *
 * SOLO APLICA A LAS MUESTRAS. El patrón control, el duplicado y el blanco de
 * reactivos no son de un cliente: no tienen equipo del que provenir. En esas
 * filas se dice por qué en vez de dejar un control muerto que invite a
 * completarlo.
 */
import { computed } from 'vue';
import { Select, SelectOption, Tooltip } from 'ant-design-vue';
import { InfoCircleOutlined, WarningOutlined } from '@ant-design/icons-vue';

const props = defineProps({
    equipment: { type: Array, default: () => [] },
    value:     { type: [Number, String, null], default: null },
    disabled:  { type: Boolean, default: false },
    /**
     * Si la fila puede tener equipo. Falso en patrón, duplicado y blanco: ahí
     * la columna explica el motivo en vez de ofrecer un selector inservible.
     */
    applicable: { type: Boolean, default: true },
});

const emit = defineEmits(['update:value']);

/**
 * Sin acentos y en minúsculas: el analista escribe "bahia" buscando "Bahía", y
 * un buscador que no encuentra lo que existe empuja a dejar la celda vacía.
 */
const normalize = (text) => String(text ?? '')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase();

/**
 * El texto contra el que busca cada opción: nombre, serie y etiqueta juntos. La
 * lista puede traer cientos de equipos y en la bancada el analista tiene a mano
 * la serie o la etiqueta de la placa, no el nombre con el que se cargó.
 */
const haystack = computed(() => {
    const map = new Map();

    for (const item of props.equipment) {
        map.set(
            Number(item.id),
            normalize(`${item.name ?? ''} ${item.serial ?? ''} ${item.tag ?? ''}`),
        );
    }

    return map;
});

const filterOption = (input, option) => {
    // Si el objeto de la opción no trae el id esperado se busca por el nombre,
    // que es lo que la opción muestra. Devolver falso dejaría la lista VACÍA y
    // el analista concluiría que el equipo no está cargado.
    const text = haystack.value.get(Number(option?.value)) ?? normalize(option?.label);

    return text.includes(normalize(input));
};

const selected = computed(
    () => props.equipment.find((item) => Number(item.id) === Number(props.value)) ?? null,
);

/** La serie y la etiqueta, en una línea, para el renglón chico de la opción. */
const subtitle = (item) => [item?.serial, item?.tag].filter(Boolean).join(' · ');

/**
 * Pendiente, no error. La fila se está cargando y el analista puede no tener
 * todavía el ingreso de la muestra registrado; el aviso es ámbar, no rojo.
 */
const isPending = computed(() => props.applicable && !props.value);
</script>

<template>
    <!-- Patrón, duplicado y blanco: se dice por qué no lleva equipo. Dejar el
         selector deshabilitado y sin explicación es lo que hace que el analista
         crea que le falta un permiso. -->
    <Tooltip v-if="!applicable" :title="$t('worksheets.equipment_not_applicable')">
        <span class="ws-equipment__na">
            <InfoCircleOutlined />
            {{ $t('worksheets.equipment_na_short') }}
        </span>
    </Tooltip>

    <div v-else class="ws-equipment">
        <!-- SIN botón de limpiar, a propósito: WorksheetService::saveRow resuelve
             el equipo con `?? $row->equipment_id`, de modo que un null enviado
             no borra el que ya está guardado. Ofrecer un aspa que parece
             desasignar y no desasigna es peor que no ofrecerla; para corregir un
             equipo mal puesto se elige el correcto. -->
        <Select
            :value="value ?? undefined"
            :disabled="disabled"
            show-search
            :filter-option="filterOption"
            option-label-prop="label"
            size="small"
            class="ws-equipment__select"
            :class="{ 'ws-equipment__select--pending': isPending }"
            :placeholder="$t('worksheets.equipment_placeholder')"
            @change="emit('update:value', $event ?? null)"
        >
            <SelectOption
                v-for="item in equipment"
                :key="item.id"
                :value="item.id"
                :label="item.name"
            >
                <span class="ws-equipment__opt">
                    <span class="ws-equipment__name">{{ item.name }}</span>
                    <span v-if="subtitle(item)" class="ws-equipment__meta">{{ subtitle(item) }}</span>
                </span>
            </SelectOption>
        </Select>

        <!-- La consecuencia, escrita, debajo de la celda: que el ensayo no
             aparezca en el informe se entiende ahora o no se entiende nunca. -->
        <Tooltip v-if="isPending" :title="$t('worksheets.equipment_missing_help')">
            <span class="ws-equipment__pending">
                <WarningOutlined />
                {{ $t('worksheets.equipment_missing') }}
            </span>
        </Tooltip>

        <span v-else-if="selected && subtitle(selected)" class="ws-equipment__meta">
            {{ subtitle(selected) }}
        </span>
    </div>
</template>

<style scoped>
.ws-equipment { display: flex; flex-direction: column; gap: 2px; min-width: 190px; }
.ws-equipment__select { width: 100%; }

.ws-equipment__opt  { display: flex; flex-direction: column; line-height: 1.25; }
.ws-equipment__name { font-size: 0.8125rem; }
.ws-equipment__meta {
    font-family: ui-monospace, Consolas, monospace;
    font-size: 0.7rem;
    color: var(--color-text-muted);
}

/* Ámbar punteado, no rojo: la fila está a medio cargar, no rota. */
.ws-equipment__select--pending :deep(.ant-select-selector) {
    border-style: dashed !important;
    border-color: var(--color-warning) !important;
    background: var(--tint-dirty);
}
.ws-equipment__pending {
    display: inline-flex; align-items: center; gap: 4px;
    font-size: 0.72rem; font-weight: 600; line-height: 1.3;
    color: var(--color-warning);
}

.ws-equipment__na {
    display: inline-flex; align-items: center; gap: 4px;
    font-size: 0.72rem; line-height: 1.3;
    color: var(--color-text-muted);
}
</style>
