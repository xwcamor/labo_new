<script setup>
import { computed, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import {
    Card, Tag, Space, Alert,
} from 'ant-design-vue';
import { TeamOutlined, TagsOutlined } from '@ant-design/icons-vue';

import AppLayout from '@/Layouts/AppLayout.vue';
import SectionHeader from '@/Components/Common/SectionHeader.vue';
import EntityShowTabs from '@/Components/Common/EntityShowTabs.vue';
import EntityShowActions from '@/Components/Common/EntityShowActions.vue';
import ViewDeletedButton from '@/Components/Common/ViewDeletedButton.vue';
import RecordHistory from '@/Components/Common/RecordHistory.vue';
import TapChangerBrandFormModal from '@/Pages/TapChangerBrands/FormModal.vue';
import { useAuth } from '@/Composables/useAuth';
import { useDateFormat } from '@/Composables/useDateFormat';

defineOptions({ layout: AppLayout });

const props = defineProps({
    tapChangerBrand: { type: Object, required: true },
    activity:   { type: Array,  default: () => [] },
    recordAudit: { type: Object, default: null },
});

const { can, isSuper, canSeeAudit } = useAuth();
const { formatDateTimeFull } = useDateFormat();

const isDeleted = computed(() => !!props.tapChangerBrand.deleted_at);
const iconBg = computed(() => isDeleted.value ? 'var(--color-danger)' : 'var(--color-primary)');

// Wrapper local para mantener call-sites compactos (fmt(...) en templates).
const fmt = (d) => formatDateTimeFull(d);

// Editar abre el diálogo sobre la ficha (regla Fiori: menos de 7 campos).
const editOpen = ref(false);
</script>

<template>
    <Head :title="tapChangerBrand.name" />

    <div class="show-page sap-show">
        <SectionHeader
            :back-href="route('business_management.tap_changer_brands.index')"
            :title="tapChangerBrand.name"
            :icon-bg="iconBg"
        >
            <template #icon><TagsOutlined /></template>
            <template #subtitle>
                <Space :size="6">
                    <Tag v-if="isDeleted" color="red" :bordered="false">{{ $t('global.deleted') }}</Tag>
                    <Tag v-else :color="tapChangerBrand.is_active ? 'success' : 'default'" :bordered="false">
                        {{ tapChangerBrand.is_active ? $t('global.active') : $t('global.inactive') }}
                    </Tag>
                </Space>
            </template>
            <template #actions>
                <EntityShowActions
                    module="tap_changer_brands"
                    route-prefix="business_management"
                    :slug="tapChangerBrand.slug"
                    :id="tapChangerBrand.id"
                    :is-deleted="isDeleted"
                    :can-edit="can('tap_changer_brands.edit')"
                    :can-delete="can('tap_changer_brands.delete')"
                    :can-see-audit="canSeeAudit"
                :is-super="isSuper"
                :is-global="tapChangerBrand.tenant_id === null"
                :lock="tapChangerBrand.lock"
                edit-as-modal
                @edit="editOpen = true"
                />
            </template>
        </SectionHeader>

        <Alert v-if="isDeleted" type="error" show-icon class="deleted-alert">
            <template #message>{{ $t('global.record_is_deleted') }}</template>
            <template #description>
                <div><strong>{{ $t('global.deleted_at') }}:</strong> {{ fmt(tapChangerBrand.deleted_at) }}</div>
                <div v-if="tapChangerBrand.deleter">
                    <strong>{{ $t('global.deleted_by') }}:</strong> {{ tapChangerBrand.deleter.name }}
                </div>
                <div v-if="tapChangerBrand.deleted_description">
                    <strong>{{ $t('global.delete_description') }}:</strong> {{ tapChangerBrand.deleted_description }}
                </div>
            </template>
            <template v-if="isSuper" #action>
                <ViewDeletedButton module="tap_changer_brands" route-prefix="business_management" />
            </template>
        </Alert>

        <EntityShowTabs :show-history="canSeeAudit" :history-count="activity.length">
            <template #general>
                <Card :bodyStyle="{ padding: 14 }" class="info-card">
                    <template #title><TagsOutlined /> {{ $t('global.general_info') }}</template>
                    <div class="spec-grid">
                        <!-- El id de la base y el slug son para dar soporte, no información
                             del laboratorio: van en caja como el resto —la ficha se ve
                             pareja— pero AL FINAL, y solo los ve el super. El `order` de
                             `.spec-cell--id` los manda al final de la grilla, así que la
                             caja no tiene que moverse de lugar en el archivo. -->
                        <div v-if="isSuper" class="spec-cell spec-cell--id">
                            <span class="spec-cell__label">ID</span>
                            <span class="spec-cell__value">{{ tapChangerBrand.id }}</span>
                        </div>
                        <div v-if="isSuper" class="spec-cell spec-cell--id">
                            <span class="spec-cell__label">Slug</span>
                            <span class="spec-cell__value">{{ tapChangerBrand.slug }}</span>
                        </div>
                        <div class="spec-cell">
                            <span class="spec-cell__label">{{ $t('tap_changer_brands.name') }}</span>
                            <span class="spec-cell__value">{{ tapChangerBrand.name }}</span>
                        </div>
                        <div v-if="tapChangerBrand.code" class="spec-cell">
                            <span class="spec-cell__label">{{ $t('tap_changer_brands.code') }}</span>
                            <span class="spec-cell__value"><code>{{ tapChangerBrand.code }}</code></span>
                        </div>
                        <div class="spec-cell">
                            <span class="spec-cell__label">{{ $t('tap_changer_brands.is_active') }}</span>
                            <span class="spec-cell__value">
                                <Tag :color="tapChangerBrand.is_active ? 'success' : 'default'" :bordered="false">
                                    {{ tapChangerBrand.is_active ? $t('global.active') : $t('global.inactive') }}
                                </Tag>
                            </span>
                        </div>
                    </div>
                </Card>
            </template>

            <template #history>
                <RecordHistory :record-audit="recordAudit" :activity="activity" :can-see-activity="canSeeAudit" />
            </template>
        </EntityShowTabs>

        <!-- Edición en diálogo, sobre la ficha (regla Fiori: menos de 7 campos). -->
        <TapChangerBrandFormModal
            :open="editOpen"
            :record="tapChangerBrand"
            @close="editOpen = false"
        />
    </div>
</template>

<style scoped>
.show-page { /* fullscreen — sin max-width, ocupa todo el ancho del content */ }
.muted { color: var(--color-text-muted); font-size: 0.8125rem; }
.deleted-alert { margin-bottom: 16px; }
.info-card { margin-bottom: 12px; border-radius: 6px; }

@media (max-width: 767px) {
    :deep(.ant-descriptions-item-label) {
        width: auto !important;
        min-width: 0 !important;
        white-space: normal !important;
        font-weight: 500;
    }
    :deep(.ant-descriptions-item-content) { word-break: break-word; }
}
</style>
