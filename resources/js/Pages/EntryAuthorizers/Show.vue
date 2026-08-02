<script setup>
import { computed, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import {
    Card, Tag, Space, Alert,
} from 'ant-design-vue';
import { TagsOutlined } from '@ant-design/icons-vue';

import AppLayout from '@/Layouts/AppLayout.vue';
import SectionHeader from '@/Components/Common/SectionHeader.vue';
import EntityShowTabs from '@/Components/Common/EntityShowTabs.vue';
import EntityShowActions from '@/Components/Common/EntityShowActions.vue';
import ViewDeletedButton from '@/Components/Common/ViewDeletedButton.vue';
import RecordHistory from '@/Components/Common/RecordHistory.vue';
import EntryAuthorizerFormModal from '@/Pages/EntryAuthorizers/FormModal.vue';
import { useAuth } from '@/Composables/useAuth';
import { useDateFormat } from '@/Composables/useDateFormat';

defineOptions({ layout: AppLayout });

const props = defineProps({
    entryAuthorizer: { type: Object, required: true },
    activity:   { type: Array,  default: () => [] },
    recordAudit: { type: Object, default: null },
});

const { can, isSuper, canSeeAudit } = useAuth();
const { formatDateTimeFull } = useDateFormat();

const isDeleted = computed(() => !!props.entryAuthorizer.deleted_at);
const iconBg = computed(() => isDeleted.value ? 'var(--color-danger)' : 'var(--color-primary)');

// Wrapper local para mantener call-sites compactos (fmt(...) en templates).
const fmt = (d) => formatDateTimeFull(d);

// Editar abre el diálogo sobre la ficha (regla Fiori: menos de 7 campos).
const editOpen = ref(false);
</script>

<template>
    <Head :title="entryAuthorizer.name" />

    <div class="show-page sap-show">
        <SectionHeader
            :back-href="route('business_management.entry_authorizers.index')"
            :title="entryAuthorizer.name"
            :icon-bg="iconBg"
        >
            <template #icon><TagsOutlined /></template>
            <template #subtitle>
                <Space :size="6">
                    <Tag v-if="isDeleted" color="red" :bordered="false">{{ $t('global.deleted') }}</Tag>
                    <Tag v-else :color="entryAuthorizer.is_active ? 'success' : 'default'" :bordered="false">
                        {{ entryAuthorizer.is_active ? $t('global.active') : $t('global.inactive') }}
                    </Tag>
                </Space>
            </template>
            <template #actions>
                <EntityShowActions
                    module="entry_authorizers"
                    route-prefix="business_management"
                    :slug="entryAuthorizer.slug"
                    :id="entryAuthorizer.id"
                    :is-deleted="isDeleted"
                    :can-edit="can('entry_authorizers.edit')"
                    :can-delete="can('entry_authorizers.delete')"
                    :can-see-audit="canSeeAudit"
                    :is-super="isSuper"
                    :is-global="entryAuthorizer.tenant_id === null"
                    :lock="entryAuthorizer.lock"
                    edit-as-modal
                    @edit="editOpen = true"
                />
            </template>
        </SectionHeader>

        <Alert v-if="isDeleted" type="error" show-icon class="deleted-alert">
            <template #message>{{ $t('global.record_is_deleted') }}</template>
            <template #description>
                <div><strong>{{ $t('global.deleted_at') }}:</strong> {{ fmt(entryAuthorizer.deleted_at) }}</div>
                <div v-if="entryAuthorizer.deleter">
                    <strong>{{ $t('global.deleted_by') }}:</strong> {{ entryAuthorizer.deleter.name }}
                </div>
                <div v-if="entryAuthorizer.deleted_description">
                    <strong>{{ $t('global.delete_description') }}:</strong> {{ entryAuthorizer.deleted_description }}
                </div>
            </template>
            <template v-if="isSuper" #action>
                <ViewDeletedButton module="entry_authorizers" route-prefix="business_management" />
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
                            <span class="spec-cell__value">{{ entryAuthorizer.id }}</span>
                        </div>
                        <div v-if="isSuper" class="spec-cell spec-cell--id">
                            <span class="spec-cell__label">Slug</span>
                            <span class="spec-cell__value">{{ entryAuthorizer.slug }}</span>
                        </div>
                        <div class="spec-cell">
                            <span class="spec-cell__label">{{ $t('entry_authorizers.name') }}</span>
                            <span class="spec-cell__value">{{ entryAuthorizer.name }}</span>
                        </div>
                        <div v-if="entryAuthorizer.code" class="spec-cell">
                            <span class="spec-cell__label">{{ $t('entry_authorizers.code') }}</span>
                            <span class="spec-cell__value"><code>{{ entryAuthorizer.code }}</code></span>
                        </div>
                        <!-- La firma escaneada: es lo que este catálogo existe
                             para guardar, así que la ficha la muestra. -->
                        <div class="spec-cell">
                            <span class="spec-cell__label">{{ $t('entry_authorizers.image') }}</span>
                            <span class="spec-cell__value">
                                <img
                                    v-if="entryAuthorizer.image_url"
                                    :src="entryAuthorizer.image_url"
                                    alt=""
                                    class="ea-sig"
                                >
                                <template v-else>{{ $t('entry_authorizers.no_image') }}</template>
                            </span>
                        </div>
                        <!-- Estado: siempre al final. -->
                        <div class="spec-cell">
                            <span class="spec-cell__label">{{ $t('entry_authorizers.is_active') }}</span>
                            <span class="spec-cell__value">
                                <Tag :color="entryAuthorizer.is_active ? 'success' : 'default'" :bordered="false">
                                    {{ entryAuthorizer.is_active ? $t('global.active') : $t('global.inactive') }}
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
        <EntryAuthorizerFormModal
            :open="editOpen"
            :record="entryAuthorizer"
            @close="editOpen = false"
        />
    </div>
</template>

<style scoped>
.show-page { /* fullscreen — sin max-width, ocupa todo el ancho del content */ }
.muted { color: var(--color-text-muted); font-size: 0.8125rem; }
.deleted-alert { margin-bottom: 16px; }
.info-card { margin-bottom: 12px; border-radius: 8px; }

@media (max-width: 767px) {
    :deep(.ant-descriptions-item-label) {
        width: auto !important;
        min-width: 0 !important;
        white-space: normal !important;
        font-weight: 500;
    }
    :deep(.ant-descriptions-item-content) { word-break: break-word; }
}
.ea-sig { max-width: 180px; max-height: 70px; border: 1px solid var(--color-border, #e5e7eb); border-radius: 6px; padding: 4px; background: #fff; }
</style>
