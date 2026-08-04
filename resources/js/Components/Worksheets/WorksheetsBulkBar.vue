<script setup>
/**
 * Barra de acciones masivas del listado de hojas de trabajo.
 *
 * Solo ofrece la baja: una hoja no tiene activo/inactivo que alternar, y su
 * estado (borrador → publicada → validada) lo mueve el flujo, no un botón.
 *
 * Sin <style scoped>: los estilos de `.bulk-bar` son globales (app.css) y el
 * scoped mataría el sticky en móvil.
 */
import { Button, Space } from 'ant-design-vue';
import { DeleteOutlined } from '@ant-design/icons-vue';

defineProps({
    count:     { type: Number,  required: true },
    isMobile:  { type: Boolean, default: false },
    canDelete: { type: Boolean, default: false },
});

defineEmits(['cancel', 'delete']);
</script>

<template>
    <div class="bulk-bar" :class="{ 'bulk-bar--mobile-sticky': isMobile }">
        <span class="bulk-bar__label">
            <strong>{{ count }}</strong>
            {{ count === 1 ? $t('global.selected') : $t('global.selected_plural') }}
        </span>
        <Space wrap>
            <Button size="small" @click="$emit('cancel')">{{ $t('global.cancel') }}</Button>
            <Button
                v-if="canDelete"
                size="small"
                danger
                type="primary"
                @click="$emit('delete')"
            >
                <DeleteOutlined /> {{ $t('global.delete') }}
            </Button>
        </Space>
    </div>
</template>
