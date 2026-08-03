<script setup>
/**
 * La ficha del préstamo: sus líneas y las devoluciones de cada una.
 *
 * Es donde se registra que algo volvió. La devolución es POR LÍNEA porque eso
 * es lo que se devuelve —seis matraces de los diez, no "seis del préstamo"— y
 * porque el tope de cada una es lo que falta de ESE artículo.
 */
import { computed, reactive, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import {
    Alert, Button, Card, DatePicker, Descriptions, DescriptionsItem, Input,
    InputNumber, Modal, Tag, Tooltip,
} from 'ant-design-vue';
import { DeleteOutlined, PlusOutlined, SwapOutlined } from '@ant-design/icons-vue';
import dayjs from 'dayjs';
import AppLayout from '@/Layouts/AppLayout.vue';
import SectionHeader from '@/Components/Common/SectionHeader.vue';
import { useI18n } from '@/Plugins/i18n';
import { useAuth } from '@/Composables/useAuth';

defineOptions({ layout: AppLayout });

const props = defineProps({
    loan: { type: Object, required: true },
});

const { t } = useI18n();
const { can } = useAuth();

const abierto = ref(false);
const guardando = ref(false);
const errores = ref({});
const form = reactive({ stock_loan_line_id: null, returned_on: null, qty: 1, notes: '' });

const lineaElegida = computed(
    () => props.loan.lines.find((l) => l.id === form.stock_loan_line_id) ?? null,
);

const devolver = (linea) => {
    errores.value = {};
    Object.assign(form, {
        stock_loan_line_id: linea.id,
        // La fecha de hoy, salvo que el préstamo sea posterior (no puede
        // devolverse antes de haberse prestado).
        returned_on: dayjs().isBefore(dayjs(props.loan.loaned_on)) ? dayjs(props.loan.loaned_on) : dayjs(),
        qty: linea.pending,
        notes: '',
    });
    abierto.value = true;
};

const guardar = () => {
    guardando.value = true;
    errores.value = {};

    router.post(route('lab_management.stock_loans.returns.store', props.loan.slug), {
        ...form,
        returned_on: form.returned_on?.format('YYYY-MM-DD'),
    }, {
        preserveScroll: true,
        onSuccess: () => { abierto.value = false; },
        onError:   (e) => { errores.value = e; },
        onFinish:  () => { guardando.value = false; },
    });
};

const borrarDevolucion = (devolucion) => {
    Modal.confirm({
        title:   t('global.delete_confirm_title'),
        content: t('stock_loans.return_delete_confirm'),
        okText:  t('global.delete'),
        okType:  'danger',
        cancelText: t('global.cancel'),
        onOk: () => router.delete(
            route('lab_management.stock_loans.returns.destroy', [props.loan.slug, devolucion.id]),
            { preserveScroll: true },
        ),
    });
};

const err = (campo) => errores.value?.[campo] ?? null;
const fecha = (d) => (d ? dayjs(d).format('DD-MM-YYYY') : '—');
</script>

<template>
    <Head :title="$t('stock_loans.singular')" />

    <div class="show-page sap-show sl-show">
        <SectionHeader
            :back-href="route('lab_management.stock_loans.index')"
            :title="loan.borrower || $t('stock_loans.singular')"
            :subtitle="fecha(loan.loaned_on)"
        >
            <template #icon><SwapOutlined /></template>
            <template #actions>
                <Tag :bordered="false" :color="loan.status === 'open' ? 'red' : 'green'">
                    {{ $t(`stock_loans.status_${loan.status}`) }}
                </Tag>
            </template>
        </SectionHeader>

        <Card class="sl-head">
            <Descriptions :column="{ xs: 1, sm: 2, lg: 3 }" size="small" bordered>
                <DescriptionsItem :label="$t('stock_loans.loaned_on')">{{ fecha(loan.loaned_on) }}</DescriptionsItem>
                <DescriptionsItem :label="$t('stock_loans.borrower')">{{ loan.borrower || '—' }}</DescriptionsItem>
                <DescriptionsItem :label="$t('stock_loans.pending')">{{ loan.pending }}</DescriptionsItem>
                <DescriptionsItem :label="$t('stock_loans.created_by')">{{ loan.created_by || '—' }}</DescriptionsItem>
                <DescriptionsItem :label="$t('stock_loans.returned_at')">
                    {{ loan.returned_at ? dayjs(loan.returned_at).format('DD-MM-YYYY HH:mm') : '—' }}
                </DescriptionsItem>
                <DescriptionsItem :label="$t('stock_loans.purpose')">{{ loan.purpose || '—' }}</DescriptionsItem>
            </Descriptions>
        </Card>

        <Card v-for="linea in loan.lines" :key="linea.id" class="sl-line-card">
            <div class="sl-line-card__head">
                <div>
                    <h3>{{ linea.item?.name }}</h3>
                    <p class="sl-line-card__meta">
                        {{ linea.item?.code }}
                        <span v-if="linea.item?.unit"> · {{ linea.item.unit }}</span>
                        <span v-if="linea.notes"> · {{ linea.notes }}</span>
                    </p>
                </div>
                <div class="sl-line-card__nums">
                    <div><b>{{ linea.qty }}</b><small>{{ $t('stock_loans.qty') }}</small></div>
                    <div><b>{{ linea.returned }}</b><small>{{ $t('stock_loans.returned') }}</small></div>
                    <div :class="{ 'sl-pend': linea.pending > 0 }">
                        <b>{{ linea.pending }}</b><small>{{ $t('stock_loans.pending') }}</small>
                    </div>
                    <Button
                        v-if="can('stock_loans.edit') && linea.pending > 0"
                        type="primary"
                        size="small"
                        @click="devolver(linea)"
                    >
                        <PlusOutlined /> {{ $t('stock_loans.new_return') }}
                    </Button>
                </div>
            </div>

            <div v-if="linea.returns.length === 0" class="sl-none">{{ $t('stock_loans.no_returns') }}</div>
            <table v-else class="sl-returns">
                <thead>
                    <tr>
                        <th>{{ $t('stock_loans.returned_on') }}</th>
                        <th>{{ $t('stock_loans.return_qty') }}</th>
                        <th>{{ $t('stock_loans.notes') }}</th>
                        <th>{{ $t('stock_loans.created_by') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="d in linea.returns" :key="d.id">
                        <td>{{ fecha(d.returned_on) }}</td>
                        <td class="sl-num">{{ d.qty }}</td>
                        <td>{{ d.notes || '—' }}</td>
                        <td>{{ d.by || '—' }}</td>
                        <td class="sl-num">
                            <Tooltip v-if="can('stock_loans.edit')" :title="$t('global.delete')">
                                <Button size="small" danger @click="borrarDevolucion(d)"><DeleteOutlined /></Button>
                            </Tooltip>
                        </td>
                    </tr>
                </tbody>
            </table>
        </Card>

        <Modal
            v-model:open="abierto"
            :title="$t('stock_loans.new_return')"
            :confirm-loading="guardando"
            :ok-text="$t('global.save')"
            :cancel-text="$t('global.cancel')"
            @ok="guardar"
        >
            <div class="sl-form">
                <Alert
                    v-if="lineaElegida"
                    type="info"
                    show-icon
                    :message="`${lineaElegida.item?.name} — ${$t('stock_loans.pending')}: ${lineaElegida.pending}`"
                />

                <label>
                    <span>{{ $t('stock_loans.returned_on') }} <i>*</i></span>
                    <DatePicker
                        v-model:value="form.returned_on"
                        format="DD-MM-YYYY"
                        style="width: 100%"
                        :status="err('returned_on') ? 'error' : ''"
                        :disabled-date="(d) => d && (d.isAfter(dayjs(), 'day') || d.isBefore(dayjs(loan.loaned_on), 'day'))"
                    />
                    <small v-if="err('returned_on')" class="sl-err">{{ err('returned_on') }}</small>
                </label>

                <label>
                    <span>{{ $t('stock_loans.return_qty') }} <i>*</i></span>
                    <InputNumber
                        v-model:value="form.qty"
                        :min="1"
                        :max="lineaElegida?.pending"
                        style="width: 100%"
                        :status="err('qty') ? 'error' : ''"
                    />
                    <small v-if="err('qty')" class="sl-err">{{ err('qty') }}</small>
                </label>

                <label>
                    <span>{{ $t('stock_loans.notes') }}</span>
                    <Input.TextArea v-model:value="form.notes" :auto-size="{ minRows: 2, maxRows: 4 }" />
                </label>
            </div>
        </Modal>
    </div>
</template>

<style scoped>
.sl-head { margin-bottom: 14px; }
.sl-line-card { margin-bottom: 12px; }
.sl-line-card__head { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; flex-wrap: wrap; }
.sl-line-card__head h3 { margin: 0; font-size: 1rem; }
.sl-line-card__meta { margin: 2px 0 0; font-size: 0.78rem; color: var(--color-text-muted); }
.sl-line-card__nums { display: flex; align-items: center; gap: 18px; }
.sl-line-card__nums div { display: flex; flex-direction: column; align-items: center; }
.sl-line-card__nums b { font-size: 1.1rem; font-variant-numeric: tabular-nums; }
.sl-line-card__nums small { font-size: 0.68rem; color: var(--color-text-muted); }
.sl-pend b { color: #C8281D; }

.sl-none { margin-top: 12px; font-size: 0.8rem; color: var(--color-text-muted); }
.sl-returns { width: 100%; margin-top: 12px; border-collapse: collapse; font-size: 0.82rem; }
.sl-returns th, .sl-returns td { padding: 6px 8px; border-bottom: 1px solid var(--color-border); text-align: left; }
.sl-returns th { font-weight: 500; color: var(--color-text-muted); }
.sl-num { text-align: right; }

.sl-form { display: flex; flex-direction: column; gap: 12px; }
.sl-form label { display: flex; flex-direction: column; gap: 4px; }
.sl-form label > span { font-size: 0.8rem; color: var(--color-text-muted); }
.sl-form label > span i { color: #C8281D; font-style: normal; }
.sl-err { color: #C8281D; font-size: 0.75rem; }
</style>
