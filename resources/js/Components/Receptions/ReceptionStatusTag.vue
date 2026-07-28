<script setup>
/**
 * El estado de la entrega, con el mismo color en el listado y en la ficha.
 *
 * La anulada se muestra tachada y en gris a propósito: sigue estando —hay que
 * poder explicar qué pasó con esos correlativos— pero no cuenta para nada.
 */
import { computed } from 'vue';
import { Tag } from 'ant-design-vue';
import { statusColor } from '@/Pages/Receptions/config/format';

const props = defineProps({
    status: { type: String, required: true },
});

const color = computed(() => statusColor(props.status));
const isCancelled = computed(() => props.status === 'cancelled');
</script>

<template>
    <Tag :color="color" :bordered="false" :class="{ 'rc-status--cancelled': isCancelled }">
        {{ $t(`receptions.status_${status}`) }}
    </Tag>
</template>

<style scoped>
.rc-status--cancelled {
    text-decoration: line-through;
    color: var(--color-text-muted);
}
</style>
