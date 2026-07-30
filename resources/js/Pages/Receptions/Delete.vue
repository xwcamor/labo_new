<script setup>
/**
 * Dar de baja una entrega, con su motivo.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ LOS CORRELATIVOS NO VUELVEN AL POZO                                      │
 * └──────────────────────────────────────────────────────────────────────────┘
 * Una entrega CONFIRMADA ya emitió sus números de muestra, y esos números no se
 * reasignan nunca: dar de baja la entrega no los libera. Se avisa acá, antes,
 * porque es la consecuencia que no se ve y la que no tiene vuelta.
 *
 * La confirmación existe como PANTALLA y no como diálogo por el estándar del
 * resto de los módulos, y porque el motivo es obligatorio: queda en el registro
 * y una auditoría lo va a leer.
 */
import { computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import { Alert, Tag } from 'ant-design-vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import DeletePage from '@/Components/Common/DeletePage.vue';
import DeleteSummaryRow from '@/Components/Common/DeleteSummaryRow.vue';
import { plainDate } from './config/format';

defineOptions({ layout: AppLayout });

const props = defineProps({
    reception: { type: Object, required: true },
});

const confirmada = computed(() => props.reception.status !== 'draft');
const muestras   = computed(() => props.reception.samples_count ?? 0);

const form = useForm({ deleted_description: '' });

const submit = () => {
    form.delete(route('lab_management.receptions.destroy', props.reception.slug), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head :title="$t('global.delete') + ' — ' + $t('receptions.singular')" />

    <DeletePage
        :back-href="route('lab_management.receptions.show', reception.slug)"
        :title="$t('global.delete') + ' ' + $t('receptions.record')"
        :subtitle="reception.code"
        v-model="form.deleted_description"
        :error="form.errors.deleted_description"
        :processing="form.processing"
        @submit="submit"
    >
        <template #warning>
            <!-- Con correlativos emitidos, el aviso es lo importante de esta
                 pantalla: la baja no devuelve los números. -->
            <Alert v-if="confirmada" type="error" show-icon class="del-mb">
                <template #message>{{ $t('receptions.delete_confirmed_warning') }}</template>
                <template #description>
                    {{ $t('receptions.delete_confirmed_detail', { count: muestras }) }}
                </template>
            </Alert>
        </template>

        <template #summary>
            <DeleteSummaryRow :label="$t('receptions.code')">
                <code>{{ reception.code }}</code>
            </DeleteSummaryRow>
            <DeleteSummaryRow v-if="reception.customer" :label="$t('receptions.customer')">
                {{ reception.customer.name }}
            </DeleteSummaryRow>
            <DeleteSummaryRow :label="$t('receptions.received_at')">
                {{ plainDate(reception.received_at) }}
            </DeleteSummaryRow>
            <DeleteSummaryRow :label="$t('receptions.status')">
                <Tag :bordered="false">{{ $t('receptions.status_' + reception.status) }}</Tag>
            </DeleteSummaryRow>
            <DeleteSummaryRow :label="$t('receptions.samples')">{{ muestras }}</DeleteSummaryRow>
        </template>
    </DeletePage>
</template>
