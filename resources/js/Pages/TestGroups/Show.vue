<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { Card, Tag, Space, Alert, Empty } from 'ant-design-vue';
import { FolderOpenOutlined, FileDoneOutlined } from '@ant-design/icons-vue';

import AppLayout from '@/Layouts/AppLayout.vue';
import SectionHeader from '@/Components/Common/SectionHeader.vue';
import EntityShowTabs from '@/Components/Common/EntityShowTabs.vue';
import EntityShowActions from '@/Components/Common/EntityShowActions.vue';
import ViewDeletedButton from '@/Components/Common/ViewDeletedButton.vue';
import RecordHistory from '@/Components/Common/RecordHistory.vue';
import { useAuth } from '@/Composables/useAuth';
import { useDateFormat } from '@/Composables/useDateFormat';

defineOptions({ layout: AppLayout });

const props = defineProps({
    testGroup:   { type: Object, required: true },
    // Las pruebas que cuelgan del grupo. Solo se listan: se crean y editan
    // desde el módulo Pruebas, que es su dueño.
    tests:       { type: Array,  default: () => [] },
    activity:    { type: Array,  default: () => [] },
    recordAudit: { type: Object, default: null },
});

const { can, isSuper, canSeeAudit } = useAuth();
const { formatDateTimeFull } = useDateFormat();

const isDeleted = computed(() => !!props.testGroup.deleted_at);
const iconBg = computed(() => isDeleted.value ? 'var(--color-danger)' : 'var(--color-primary)');

// Wrapper local para mantener call-sites compactos (fmt(...) en templates).
const fmt = (d) => formatDateTimeFull(d);
</script>

<template>
    <Head :title="testGroup.name" />

    <div class="show-page sap-show">
        <SectionHeader
            :back-href="route('lab_management.test_groups.index')"
            :title="testGroup.name"
            :icon-bg="iconBg"
        >
            <template #icon><FolderOpenOutlined /></template>
            <template #subtitle>
                <Space :size="6">
                    <Tag v-if="testGroup.code" :bordered="false">{{ testGroup.code }}</Tag>
                    <Tag v-if="isDeleted" color="red" :bordered="false">{{ $t('global.deleted') }}</Tag>
                    <Tag v-else :color="testGroup.is_active ? 'success' : 'default'" :bordered="false">
                        {{ testGroup.is_active ? $t('global.active') : $t('global.inactive') }}
                    </Tag>
                    <Tag color="blue" :bordered="false">
                        {{ $t('test_groups.tests_count') }}: {{ tests.length }}
                    </Tag>
                </Space>
            </template>
            <template #actions>
                <EntityShowActions
                    module="test_groups"
                    route-prefix="lab_management"
                    :slug="testGroup.slug"
                    :id="testGroup.id"
                    :is-deleted="isDeleted"
                    :can-edit="can('test_groups.edit')"
                    :can-delete="can('test_groups.delete')"
                    :can-see-audit="canSeeAudit"
                    :is-super="isSuper"
                    :is-global="testGroup.tenant_id === null"
                    :lock="testGroup.lock"
                />
            </template>
        </SectionHeader>

        <Alert v-if="isDeleted" type="error" show-icon class="deleted-alert">
            <template #message>{{ $t('global.record_is_deleted') }}</template>
            <template #description>
                <div><strong>{{ $t('global.deleted_at') }}:</strong> {{ fmt(testGroup.deleted_at) }}</div>
                <div v-if="testGroup.deleter">
                    <strong>{{ $t('global.deleted_by') }}:</strong> {{ testGroup.deleter.name }}
                </div>
                <div v-if="testGroup.deleted_description">
                    <strong>{{ $t('global.delete_description') }}:</strong> {{ testGroup.deleted_description }}
                </div>
            </template>
            <template v-if="isSuper" #action>
                <ViewDeletedButton module="test_groups" route-prefix="lab_management" />
            </template>
        </Alert>

        <EntityShowTabs :show-history="canSeeAudit" :history-count="activity.length">
            <template #general>
                <Card :bodyStyle="{ padding: 14 }" class="info-card">
                    <template #title><FolderOpenOutlined /> {{ $t('global.general_info') }}</template>
                    <div class="spec-grid">
                        <!-- El id de la base y el slug NO son información del
                             laboratorio: son para dar soporte. Iban en dos cajas del
                             mismo tamaño que el nombre, compitiendo con lo que sí
                             importa. `order` los manda al pie de la grilla sin
                             depender de dónde estén escritos. -->
                        <p v-if="isSuper" class="spec-ids">
                            <span><b>ID</b> {{ testGroup.id }}</span>
                            <span><b>Slug</b> {{ testGroup.slug }}</span>
                        </p>
                        <div class="spec-cell">
                            <span class="spec-cell__label">{{ $t('test_groups.code') }}</span>
                            <span class="spec-cell__value"><code>{{ testGroup.code || '—' }}</code></span>
                        </div>
                        <div class="spec-cell">
                            <span class="spec-cell__label">{{ $t('test_groups.name') }}</span>
                            <span class="spec-cell__value">{{ testGroup.name }}</span>
                        </div>
                        <div class="spec-cell">
                            <span class="spec-cell__label">{{ $t('test_groups.sort_order') }}</span>
                            <span class="spec-cell__value">{{ testGroup.sort_order ?? '—' }}</span>
                        </div>
                        <div class="spec-cell">
                            <span class="spec-cell__label">{{ $t('test_groups.is_active') }}</span>
                            <span class="spec-cell__value">
                                <Tag :color="testGroup.is_active ? 'success' : 'default'" :bordered="false">
                                    {{ testGroup.is_active ? $t('global.active') : $t('global.inactive') }}
                                </Tag>
                            </span>
                        </div>
                    </div>
                </Card>

                <!-- Qué hay dentro del grupo. Es lo primero que se busca al
                     abrirlo, y lo que dice si se puede desactivar sin dejar
                     pruebas sin clasificar. -->
                <Card :bodyStyle="{ padding: 14 }" class="info-card">
                    <template #title><FileDoneOutlined /> {{ $t('test_groups.tests') }}</template>
                    <template #extra>
                        <Link v-if="can('test_definitions.view')" :href="route('lab_management.test_definitions.index')">
                            {{ $t('test_groups.go_to_tests') }}
                        </Link>
                    </template>

                    <p class="card-hint">{{ $t('test_groups.tests_hint') }}</p>

                    <ul v-if="tests.length" class="tests-list">
                        <li v-for="t in tests" :key="t.id" class="tests-list__item">
                            <component
                                :is="can('test_definitions.view') ? Link : 'span'"
                                :href="can('test_definitions.view') ? route('lab_management.test_definitions.show', t.slug) : undefined"
                                class="tests-list__name"
                            >
                                <code class="tests-list__code">{{ t.code }}</code> {{ t.name }}
                            </component>
                            <Tag v-if="!t.is_active" color="default" :bordered="false">
                                {{ $t('global.inactive') }}
                            </Tag>
                        </li>
                    </ul>

                    <Empty v-else :description="$t('test_groups.tests_empty')" />
                </Card>
            </template>

            <template #history>
                <RecordHistory :record-audit="recordAudit" :activity="activity" :can-see-activity="canSeeAudit" />
            </template>
        </EntityShowTabs>
    </div>
</template>

<style scoped>
.show-page { /* fullscreen — sin max-width, ocupa todo el ancho del content */ }
.muted { color: var(--color-text-muted); font-size: 0.8125rem; }
.deleted-alert { margin-bottom: 16px; }
.info-card { margin-bottom: 12px; border-radius: 8px; }
.card-hint { margin: 0 0 12px 0; color: var(--color-text-muted); font-size: 0.8125rem; }

.tests-list { list-style: none; margin: 0; padding: 0; }
.tests-list__item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 0;
    border-bottom: 1px solid var(--color-border-subtle, #f2f3f5);
}
.tests-list__item:last-child { border-bottom: 0; }
.tests-list__code {
    font-family: ui-monospace, Consolas, monospace;
    font-size: 0.8125rem;
    color: var(--color-text-muted);
    margin-right: 6px;
}

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
