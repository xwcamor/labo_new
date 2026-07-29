<script setup>
/**
 * FormModal — el diálogo estándar de alta/edición para módulos chicos.
 *
 * Regla de la casa (SAP Fiori): la primera pregunta de un formulario no es
 * "¿grande o chico?" sino "¿merece página completa o diálogo?". Con MENOS de
 * 7 campos la respuesta es diálogo: se abre sobre la pantalla en la que uno
 * ya está (el listado o la ficha) y no lo saca de contexto. De 7 a 8 campos:
 * página de una columna. Más de 8: página a dos columnas (la otra regla de la
 * casa, pedida antes).
 *
 * El diálogo NO decide nada: cada módulo le pasa su título, sus campos (slot)
 * y su envío. Enter envía (los campos van dentro de un <Form> real), Escape
 * cancela, y el clic fuera NO cierra — un formulario a medio llenar que se
 * pierde por un clic accidental es trabajo tirado.
 *
 * El pie replica el orden Fiori del FormFooter flotante: acción primaria
 * primero, Cancelar al borde derecho.
 */
import { Alert, Button, Form, Modal } from 'ant-design-vue';
import { SaveOutlined } from '@ant-design/icons-vue';

defineProps({
    open:       { type: Boolean, default: false },
    title:      { type: String,  required: true },
    isEdit:     { type: Boolean, default: false },
    processing: { type: Boolean, default: false },
    // form.hasErrors del módulo: dibuja el aviso general arriba de los campos.
    hasErrors:  { type: Boolean, default: false },
    width:      { type: Number,  default: 560 },
    // Label del botón cuando es alta (ej. 'test_groups.new'). Default: crear.
    createLabelKey: { type: String, default: 'global.create' },
});

const emit = defineEmits(['close', 'submit']);
</script>

<template>
    <Modal
        :open="open"
        :title="title"
        :width="width"
        :footer="null"
        :mask-closable="false"
        destroy-on-close
        @cancel="emit('close')"
    >
        <Form layout="vertical" @submit.prevent="emit('submit')">
            <Alert
                v-if="hasErrors"
                type="error"
                show-icon
                :message="$t('global.fix_marked_fields')"
                class="fm__alert"
            />

            <slot />

            <div class="fm__footer">
                <Button type="primary" html-type="submit" :loading="processing">
                    <SaveOutlined />
                    {{ isEdit ? $t('global.save_changes') : $t(createLabelKey) }}
                </Button>
                <Button @click="emit('close')">{{ $t('global.cancel') }}</Button>
            </div>
        </Form>
    </Modal>
</template>

<style scoped>
.fm__alert { margin-bottom: 16px; }
.fm__footer {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 20px;
    padding-top: 14px;
    border-top: 1px solid var(--color-border-soft, #E5E5E5);
}
@media (max-width: 768px) {
    .fm__footer { flex-direction: column-reverse; }
    .fm__footer :deep(.ant-btn) { width: 100%; }
}
</style>
