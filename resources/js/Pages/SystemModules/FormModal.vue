<script setup>
/**
 * Alta y edición del módulo del sistema, en diálogo (regla Fiori de la casa:
 * menos de 7 campos no merece página completa). Los campos son los mismos del
 * Form.vue de página completa, que queda como respaldo.
 *
 * La vista previa de permission_key y de los permisos generados es solo
 * informativa: la versión real la calcula el backend (Observer) al guardar.
 */
import { computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { FormItem, Input, Switch, Space, Tag, Divider } from 'ant-design-vue';
import { KeyOutlined, SafetyCertificateOutlined } from '@ant-design/icons-vue';
import FormModal from '@/Components/Common/FormModal.vue';
import { useI18n } from '@/Plugins/i18n';

const props = defineProps({
    open:   { type: Boolean, default: false },
    // El registro a editar; null = alta. Al editar, la fila debe traer
    // permission_key (el índice lo incluye en su select) para la vista previa.
    record: { type: Object,  default: null },
});

const emit = defineEmits(['close']);

const { t } = useI18n();

const isEdit = computed(() => !!props.record);

const form = useForm({
    name:      '',
    is_active: true,
});

// El diálogo se reusa entre aperturas: al abrir se carga el registro (o se
// limpia para el alta) y se descartan los errores de la vez anterior.
watch(() => props.open, (abierto) => {
    if (!abierto) return;
    form.clearErrors();
    form.name      = props.record?.name ?? '';
    form.is_active = props.record?.is_active ?? true;
});

// Auto-derivar permission_key del nombre (mirror del backend setNameAttribute).
// Al EDITAR se muestra el permission_key REAL guardado, no se recalcula: los
// permisos ya generados cuelgan de esa clave.
const previewPermissionKey = computed(() => {
    if (isEdit.value) return props.record?.permission_key ?? '';
    const n = (form.name ?? '').trim();
    if (!n) return '';
    // Aproximación visual — la versión real la calcula el backend.
    const snake = n.replace(/([a-z0-9])([A-Z])/g, '$1_$2').replace(/[\s-]+/g, '_').toLowerCase();
    // pluralización naive (s al final si no termina en s)
    return snake.endsWith('s') ? snake : snake + 's';
});

const ACTIONS = ['view', 'show', 'create', 'edit', 'delete', 'export'];

const previewPermissions = computed(() => {
    const key = previewPermissionKey.value;
    return key ? ACTIONS.map(a => `${key}.${a}`) : [];
});

const title = computed(() => (isEdit.value
    ? `${t('global.edit')} ${t('system_modules.record')}`
    : t('system_modules.new')));

const submit = () => {
    const opciones = { preserveScroll: true, onSuccess: () => emit('close') };

    if (isEdit.value) {
        form.put(route('system_management.system_modules.update', props.record.slug), opciones);
    } else {
        form.post(route('system_management.system_modules.store'), opciones);
    }
};
</script>

<template>
    <FormModal
        :open="open"
        :title="title"
        :is-edit="isEdit"
        :processing="form.processing"
        :has-errors="form.hasErrors"
        create-label-key="system_modules.new"
        @close="emit('close')"
        @submit="submit"
    >
        <FormItem
            :label="$t('system_modules.name')"
            :tooltip="$t('system_modules.name_help')"
            required
            :validate-status="form.errors.name ? 'error' : ''"
            :help="form.errors.name"
        >
            <Input
                v-model:value="form.name"
                :maxlength="255"
                showCount
                autofocus
                :placeholder="$t('system_modules.name_placeholder')"
            />
        </FormItem>

        <FormItem
            v-if="isEdit"
            :label="$t('system_modules.is_active')"
            :tooltip="$t('system_modules.is_active_help')"
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

        <!-- Preview: permission_key + permissions generadas -->
        <div v-if="previewPermissionKey" class="meta-section">
            <Divider orientation="left">
                <Space><KeyOutlined /> <span>{{ $t('system_modules.generated_section_title') }}</span></Space>
            </Divider>

            <FormItem :label="$t('system_modules.permission_key_preview_label')">
                <Input :value="previewPermissionKey" readonly>
                    <template #prefix><KeyOutlined /></template>
                </Input>
            </FormItem>

            <FormItem :label="$t('system_modules.permissions_preview_label')">
                <Space wrap :size="[6, 6]">
                    <Tag v-for="p in previewPermissions" :key="p" color="cyan" :bordered="false">
                        <SafetyCertificateOutlined /> {{ p }}
                    </Tag>
                </Space>
                <p class="hint">{{ $t('system_modules.permissions_preview_hint') }}</p>
            </FormItem>
        </div>
    </FormModal>
</template>

<style scoped>
.state-label {
    font-size: 0.875rem;
    color: var(--color-text);
    font-weight: 500;
}
.meta-section { margin-top: 8px; }
.hint { font-size: 0.8125rem; color: var(--color-text-muted); margin: 6px 0 0 0; line-height: 1.4; }
</style>
