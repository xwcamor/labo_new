<script setup>
/**
 * "Valores Constantes": los campos cuyo valor se arrastra de una muestra a la
 * siguiente — el factor de la solución titulante, la temperatura de la sala.
 *
 * Sigue siendo una pantalla propia y no una pestaña del editor de la plantilla
 * porque el supervisor la usa a diario: cambia el factor cuando titula una
 * solución nueva y no tiene por qué entrar a la definición completa de la
 * prueba para eso.
 */
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Alert, Button, Input, Tooltip } from 'ant-design-vue';
import { SaveOutlined, TableOutlined } from '@ant-design/icons-vue';

import AppLayout from '@/Layouts/AppLayout.vue';
import SectionHeader from '@/Components/Common/SectionHeader.vue';
import { useAuth } from '@/Composables/useAuth';

defineOptions({ layout: AppLayout });

const props = defineProps({
    definition: { type: Object, required: true },
    /** Solo los campos marcados constantes: los filtra el controlador. */
    fields:     { type: Array,  default: () => [] },
});

const { can } = useAuth();

const canEdit = computed(() => can('test_definitions.edit'));

// Un solo borrador para toda la tabla y un solo guardado: cambiar el factor de
// KOH y la temperatura de la sala es un mismo gesto del supervisor.
const values = ref(Object.fromEntries(
    props.fields.map((f) => [f.id, f.default_value ?? '']),
));

const saving = ref(false);

const dirty = computed(() =>
    props.fields.some((f) => (values.value[f.id] ?? '') !== (f.default_value ?? '')));

const save = () => {
    saving.value = true;
    router.post(
        route('lab_management.test_definitions.constants.update', props.definition.slug),
        { values: values.value },
        {
            preserveScroll: true,
            onFinish: () => { saving.value = false; },
        },
    );
};
</script>

<template>
    <Head :title="`${definition.name} — ${$t('test_fields.constants')}`" />

    <div class="form-page sap-form">
        <SectionHeader
            :back-href="route('lab_management.test_definitions.fields.index', definition.slug)"
            :title="$t('test_fields.constants')"
            :subtitle="definition.name"
        >
            <template #icon><TableOutlined /></template>
            <template #actions>
                <Link :href="route('lab_management.test_definitions.fields.index', definition.slug)">
                    <Button>{{ $t('test_fields.title') }}</Button>
                </Link>
            </template>
        </SectionHeader>

        <div class="form-body">
            <Alert type="info" show-icon :message="$t('test_fields.constants_intro')" class="tfc-alert" />

            <div v-if="fields.length === 0" class="tfc-card tfc-empty">
                {{ $t('test_fields.constants_empty') }}
            </div>

            <template v-else>
                <div class="tfc-card">
                    <div class="tfc-scroll">
                        <table class="tfc-table">
                            <thead>
                                <tr>
                                    <th>{{ $t('test_fields.label') }}</th>
                                    <th>{{ $t('test_fields.code') }}</th>
                                    <th>{{ $t('test_fields.unit') }}</th>
                                    <th class="tfc-th-value">{{ $t('test_fields.default_value') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="field in fields" :key="field.id">
                                    <td class="tfc-label">{{ field.label }}</td>
                                    <td><code class="tfc-code">{{ field.code }}</code></td>
                                    <td>{{ field.unit || '—' }}</td>
                                    <td>
                                        <Input
                                            v-model:value="values[field.id]"
                                            :maxlength="255"
                                            :disabled="!canEdit"
                                            :placeholder="$t('test_fields.default_value')"
                                        />
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div v-if="canEdit" class="tfc-footer">
                    <Tooltip :title="$t('global.save_changes_hint')">
                        <Button type="primary" :loading="saving" :disabled="!dirty" @click="save">
                            <SaveOutlined /> {{ $t('global.save_changes') }}
                        </Button>
                    </Tooltip>
                </div>
            </template>
        </div>
    </div>
</template>

<style scoped>
.tfc-alert { margin-bottom: 12px; }

/* Contenedor propio: dentro de `.sap-form` las Cards de Ant Design quedan
   transparentes y sin borde. */
.tfc-card {
    border-radius: 8px;
    border: 1px solid var(--color-border);
    background: var(--color-surface);
    overflow: hidden;
}

.tfc-scroll { overflow-x: auto; }

.tfc-table {
    width: 100%;
    min-width: 620px;
    border-collapse: collapse;
    font-size: 0.85rem;
}
.tfc-table th {
    text-align: left;
    padding: 10px 12px;
    font-size: 0.66rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--color-text-muted);
    border-bottom: 1px solid var(--color-border);
    white-space: nowrap;
}
.tfc-table td {
    padding: 8px 12px;
    border-bottom: 1px solid var(--color-border-soft);
    color: var(--color-text);
}
.tfc-table tbody tr:last-child td { border-bottom: none; }

.tfc-th-value { width: 320px; }
.tfc-label { font-weight: 600; }
.tfc-code {
    font-family: ui-monospace, Consolas, monospace;
    font-size: 0.78rem;
    background: var(--color-surface-alt);
    padding: 2px 6px;
    border-radius: 3px;
}

.tfc-empty {
    padding: 40px 16px;
    text-align: center;
    color: var(--color-text-muted);
    font-size: 0.875rem;
}

.tfc-footer {
    display: flex;
    justify-content: flex-end;
    margin-top: 14px;
}
</style>
