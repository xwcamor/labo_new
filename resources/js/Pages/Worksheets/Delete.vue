<script setup>
/**
 * Dar de baja la hoja de trabajo — la confirmación estándar de los módulos.
 *
 * El motivo acá no es `deleted_description` sino `void_reason`: la baja de una
 * hoja es una anulación de ensayo y el motivo se guarda en la propia hoja,
 * donde la auditoría lo espera. Los valores crudos no se pierden.
 */
import { Head, useForm } from '@inertiajs/vue3';
import { Tag } from 'ant-design-vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import DeletePage from '@/Components/Common/DeletePage.vue';
import DeleteSummaryRow from '@/Components/Common/DeleteSummaryRow.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    worksheet: { type: Object, required: true },
});

const form = useForm({
    void_reason: '',
});

const submit = () => {
    form.transform((data) => ({ void_reason: data.void_reason }))
        .delete(route('lab_management.worksheets.destroy', props.worksheet.slug), {
            preserveScroll: true,
        });
};
</script>

<template>
    <Head :title="$t('global.delete') + ' — ' + $t('worksheets.singular')" />

    <DeletePage
        :back-href="route('lab_management.worksheets.show', worksheet.slug)"
        :title="$t('global.delete') + ' ' + $t('worksheets.singular').toLowerCase()"
        :subtitle="worksheet.definition?.name ?? ''"
        v-model="form.void_reason"
        :error="form.errors.void_reason"
        :processing="form.processing"
        @submit="submit"
    >
        <template #summary>
            <DeleteSummaryRow :label="$t('worksheets.test_definition')">
                {{ worksheet.definition?.name ?? '—' }}
            </DeleteSummaryRow>
            <DeleteSummaryRow :label="$t('worksheets.run_date')">
                {{ (worksheet.run_date ?? '').slice(0, 10) }}
            </DeleteSummaryRow>
            <DeleteSummaryRow :label="$t('worksheets.analyst')">
                {{ worksheet.analyst?.name ?? '—' }}
            </DeleteSummaryRow>
            <DeleteSummaryRow :label="$t('worksheets.status')">
                <Tag :bordered="false">{{ $t('worksheets.state.' + worksheet.status) }}</Tag>
            </DeleteSummaryRow>
        </template>
    </DeletePage>
</template>
