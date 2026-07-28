<script setup>
/**
 * El estado de la hoja, con el mismo color en el listado y en la ficha.
 *
 * La hoja anulada se muestra tachada y en gris a propósito: sigue estando —el
 * laboratorio responde por ella ante la auditoría— pero no cuenta para nada.
 */
import { computed } from 'vue';
import { Tag, Tooltip } from 'ant-design-vue';
import { statusColor } from '@/Pages/Worksheets/config/format';

const props = defineProps({
    status: { type: String, required: true },
    // El texto de ayuda explica qué se puede hacer en ese estado. En el
    // listado estorba; en la ficha es lo que le dice al analista por qué la
    // grilla está deshabilitada.
    withHelp: { type: Boolean, default: false },
});

const color = computed(() => statusColor(props.status));
const isVoided = computed(() => props.status === 'voided');
</script>

<template>
    <Tooltip :title="withHelp ? '' : $t(`worksheets.state_help.${status}`)">
        <Tag :color="color" :bordered="false" :class="{ 'ws-status--voided': isVoided }">
            {{ $t(`worksheets.state.${status}`) }}
        </Tag>
    </Tooltip>
</template>

<style scoped>
.ws-status--voided {
    text-decoration: line-through;
    color: var(--color-text-muted);
}
</style>
