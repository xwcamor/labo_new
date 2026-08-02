<script setup>
/**
 * Alta y edición del personal que autoriza ingresos, en diálogo (regla Fiori
 * de la casa: menos de 7 campos no merece página completa).
 *
 * Dos campos, como el catálogo del sistema anterior: nombre completo y firma.
 * La firma se sube o se dibuja (mismo `SignatureDrawPad` que Firmas), y el
 * envío va como multipart o el archivo se perdería en silencio.
 */
import { computed, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { FormItem, Input, Switch, Space, RadioGroup, RadioButton } from 'ant-design-vue';
import FormModal from '@/Components/Common/FormModal.vue';
import SignatureDrawPad from '@/Components/Signatures/SignatureDrawPad.vue';
import { useI18n } from '@/Plugins/i18n';

const props = defineProps({
    open:   { type: Boolean, default: false },
    // El registro a editar; null = alta.
    record: { type: Object,  default: null },
});

const emit = defineEmits(['close']);

const { t } = useI18n();

const isEdit = computed(() => !!props.record);

const form = useForm({
    name:      '',
    image:     null,
    is_active: true,
});

const modoFirma = ref('upload');
const imagenLista = ref(null);

// El diálogo se reusa entre aperturas: al abrir se carga el registro (o se
// limpia para el alta) y se descartan los errores de la vez anterior.
watch(() => props.open, (abierto) => {
    if (!abierto) return;
    form.clearErrors();
    form.name      = props.record?.name ?? '';
    form.image     = null;
    form.is_active = props.record?.is_active ?? true;
    imagenLista.value = null;
    modoFirma.value = 'upload';
});

const tomarDibujo = (file) => {
    form.image = file;
    imagenLista.value = URL.createObjectURL(file);
};

const tomarArchivo = (e) => {
    form.image = e.target.files?.[0] ?? null;
    imagenLista.value = form.image ? URL.createObjectURL(form.image) : null;
};

// La ficha manda `image_url`; las filas del listado traen la ruta cruda del
// disco público. Cualquiera de las dos sirve para la vista previa.
const firmaActual = computed(() => props.record?.image_url
    ?? (props.record?.image ? `/storage/${props.record.image}` : null));

const title = computed(() => (isEdit.value
    ? `${t('global.edit')} ${t('entry_authorizers.record')}`
    : t('entry_authorizers.new')));

const submit = () => {
    const opciones = { preserveScroll: true, forceFormData: true, onSuccess: () => emit('close') };

    if (isEdit.value) {
        // PUT con archivo no viaja como multipart: method spoofing.
        form.transform((d) => ({ ...d, _method: 'put' }))
            .post(route('business_management.entry_authorizers.update', props.record.slug), opciones);
    } else {
        form.post(route('business_management.entry_authorizers.store'), opciones);
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
        create-label-key="entry_authorizers.new"
        @close="emit('close')"
        @submit="submit"
    >
        <FormItem
            :label="$t('entry_authorizers.name')"
            :tooltip="$t('entry_authorizers.name_help')"
            required
            :validate-status="form.errors.name ? 'error' : ''"
            :help="form.errors.name"
        >
            <Input
                v-model:value="form.name"
                :maxlength="255"
                showCount
                autofocus
                :placeholder="$t('entry_authorizers.name_placeholder')"
            />
        </FormItem>

        <FormItem
            :label="$t('entry_authorizers.image')"
            :tooltip="$t('entry_authorizers.image_help')"
            :validate-status="form.errors.image ? 'error' : ''"
            :help="form.errors.image"
        >
            <!-- La firma vigente, si ya hay una; la nueva apenas se toma. -->
            <img
                v-if="firmaActual && !imagenLista"
                :src="firmaActual"
                alt=""
                class="sig-preview"
            >
            <img v-if="imagenLista" :src="imagenLista" alt="" class="sig-preview">

            <RadioGroup v-model:value="modoFirma" size="small" class="sig-mode">
                <RadioButton value="upload">{{ $t('signatures.mode_upload') }}</RadioButton>
                <RadioButton value="draw">{{ $t('signatures.mode_draw') }}</RadioButton>
            </RadioGroup>

            <input
                v-if="modoFirma === 'upload'"
                type="file"
                accept="image/png,image/jpeg"
                @change="tomarArchivo"
            >
            <SignatureDrawPad v-else @done="tomarDibujo" />
        </FormItem>

        <!-- El estado solo aparece al editar: un alta siempre nace activa. -->
        <FormItem
            v-if="isEdit"
            :label="$t('entry_authorizers.is_active')"
            :tooltip="$t('entry_authorizers.is_active_help')"
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
    </FormModal>
</template>

<style scoped>
.state-label {
    font-size: 0.875rem;
    color: var(--color-text);
    font-weight: 500;
}
.sig-preview {
    display: block;
    max-width: 220px;
    max-height: 90px;
    margin-bottom: 10px;
    border: 1px solid var(--color-border, #e5e7eb);
    border-radius: 6px;
    padding: 6px;
    background: #fff;
}
.sig-mode { display: block; margin-bottom: 8px; }
</style>
