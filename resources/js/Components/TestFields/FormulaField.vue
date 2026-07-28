<script setup>
/**
 * La fórmula de una columna calculada, con comprobación contra el servidor.
 *
 * En el sistema Rails viejo la fórmula era un bloque de JavaScript escrito en
 * un campo de texto de la configuración, guardado en la base e inyectado sin
 * escapar en la página de todos los analistas. Nadie la revisaba: un error de
 * tipeo se descubría cuando el ensayo salía en blanco, o con el texto "NaN"
 * guardado en la base.
 *
 * El botón "Comprobar" pregunta al MISMO analizador que valida al guardar, así
 * que lo que dice aquí es lo que va a pasar. Ver el problema mientras se
 * escribe es la diferencia entre corregir un tipeo y descubrirlo tres semanas
 * después en un certificado.
 */
import { ref, watch } from 'vue';
import { Alert, Button, Textarea } from 'ant-design-vue';
import { CheckCircleOutlined } from '@ant-design/icons-vue';

const props = defineProps({
    /** La expresión. Se edita con v-model desde el formulario del campo. */
    modelValue: { type: String, default: '' },
    /** Código de la columna: hace falta para detectar un ciclo consigo misma. */
    code: { type: String, default: '' },
    /** Slug de la prueba (clave de ruta del editor). */
    definitionSlug: { type: String, required: true },
});

const emit = defineEmits(['update:modelValue']);

const checking = ref(false);
const result = ref(null);
const failed = ref(false);

// Al reescribir la fórmula el resultado anterior deja de valer: mostrarlo
// mientras el texto ya cambió es peor que no mostrar nada.
watch(() => props.modelValue, () => { result.value = null; failed.value = false; });

const check = async () => {
    const formula = (props.modelValue ?? '').trim();
    if (formula === '') return;

    checking.value = true;
    failed.value = false;

    try {
        const { data } = await window.axios.post(
            route('lab_management.test_definitions.fields.check_formula', props.definitionSlug),
            { formula, code: props.code || null },
        );
        result.value = data;
    } catch (e) {
        failed.value = true;
        result.value = null;
    } finally {
        checking.value = false;
    }
};
</script>

<template>
    <div class="ff">
        <Textarea
            :value="modelValue"
            @update:value="(v) => emit('update:modelValue', v)"
            :rows="2"
            :maxlength="1000"
            :placeholder="$t('test_fields.formula_help')"
            class="ff__input"
        />

        <div class="ff__bar">
            <Button size="small" :loading="checking" :disabled="!modelValue" @click="check">
                <CheckCircleOutlined /> {{ $t('test_fields.formula_check') }}
            </Button>
            <span class="ff__hint">{{ $t('test_fields.formula_functions') }}</span>
        </div>

        <p class="ff__hint">{{ $t('test_fields.formula_server') }}</p>

        <Alert v-if="failed" type="error" show-icon :message="$t('global.unknown_error')" class="ff__result" />

        <template v-else-if="result">
            <Alert
                v-if="result.ok && (result.cycles ?? []).length === 0"
                type="success"
                show-icon
                :message="$t('test_fields.formula_valid')"
                class="ff__result"
            >
                <template v-if="(result.uses ?? []).length > 0" #description>
                    {{ $t('test_fields.formula_uses', { fields: result.uses.join(', ') }) }}
                </template>
            </Alert>

            <Alert v-else type="error" show-icon class="ff__result">
                <template #message>{{ $t('global.form_has_errors') }}</template>
                <template #description>
                    <ul class="ff__errors">
                        <li v-for="(err, i) in (result.errors ?? [])" :key="`e${i}`">{{ err }}</li>
                        <li v-for="(cycle, i) in (result.cycles ?? [])" :key="`c${i}`">
                            {{ $t('test_fields.errors.formula_cycle', { path: cycle.join(' → ') }) }}
                        </li>
                    </ul>
                    <p v-if="(result.uses ?? []).length > 0" class="ff__uses">
                        {{ $t('test_fields.formula_uses', { fields: result.uses.join(', ') }) }}
                    </p>
                </template>
            </Alert>
        </template>
    </div>
</template>

<style scoped>
.ff__input { font-family: ui-monospace, Consolas, monospace; font-size: 0.85rem; }
.ff__bar {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    margin-top: 8px;
}
.ff__hint {
    font-size: 0.75rem;
    color: var(--color-text-muted);
    margin: 6px 0 0;
    line-height: 1.4;
}
.ff__result { margin-top: 10px; }
.ff__errors { margin: 0; padding-left: 18px; }
.ff__uses { margin: 6px 0 0; font-size: 0.8125rem; }
</style>
