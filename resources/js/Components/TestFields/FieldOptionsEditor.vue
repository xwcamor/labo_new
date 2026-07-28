<script setup>
/**
 * Las opciones de una columna de selección, editadas en línea.
 *
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ LAS OPCIONES SE OCULTAN, NO SE BORRAN                                    │
 * └──────────────────────────────────────────────────────────────────────────┘
 * Un ensayo cerrado apunta a la opción que se eligió ESE día. Borrarla dejaría
 * el registro histórico apuntando a la nada — es lo que hacía el sistema viejo,
 * y por eso necesitaba un parche que reinyectara la opción seleccionada aunque
 * estuviera oculta o borrada. Aquí quitar una de la lista la marca oculta: deja
 * de ofrecerse en la bancada y los ensayos viejos siguen diciendo lo mismo.
 */
import { Button, Checkbox, Input, InputNumber, Tooltip } from 'ant-design-vue';
import { EyeInvisibleOutlined, PlusOutlined } from '@ant-design/icons-vue';

const props = defineProps({
    /** [{ value, sort_order, is_hidden, accreditation_flag }] */
    modelValue: { type: Array, default: () => [] },
});

const emit = defineEmits(['update:modelValue']);

const update = (rows) => emit('update:modelValue', rows);

const addOption = () => {
    update([
        ...props.modelValue,
        { value: '', sort_order: props.modelValue.length + 1, is_hidden: false, accreditation_flag: null },
    ]);
};

const patch = (index, changes) => {
    const rows = props.modelValue.map((row, i) => (i === index ? { ...row, ...changes } : row));
    update(rows);
};

/** El orden es el de la lista: el servidor lo reasigna 1..n al guardar. */
const move = (index, delta) => {
    const target = index + delta;
    if (target < 0 || target >= props.modelValue.length) return;

    const rows = [...props.modelValue];
    [rows[index], rows[target]] = [rows[target], rows[index]];
    update(rows.map((row, i) => ({ ...row, sort_order: i + 1 })));
};
</script>

<template>
    <div class="foe">
        <p class="foe__note">{{ $t('test_fields.types_help.select') }}</p>

        <div v-if="modelValue.length > 0" class="foe__list">
            <div class="foe__head">
                <span>{{ $t('test_fields.sort_order') }}</span>
                <span>{{ $t('test_fields.options') }}</span>
                <span>{{ $t('test_fields.accreditation_flag') }}</span>
                <span>{{ $t('test_fields.is_hidden') }}</span>
                <span />
            </div>

            <div
                v-for="(option, index) in modelValue"
                :key="option.id ?? `new-${index}`"
                class="foe__row"
                :class="{ 'foe__row--hidden': option.is_hidden }"
            >
                <InputNumber
                    :value="option.sort_order ?? index + 1"
                    @update:value="(v) => patch(index, { sort_order: v })"
                    :min="0"
                    size="small"
                    class="foe__order"
                />

                <Input
                    :value="option.value"
                    @update:value="(v) => patch(index, { value: v })"
                    size="small"
                    :maxlength="255"
                />

                <Input
                    :value="option.accreditation_flag ?? ''"
                    @update:value="(v) => patch(index, { accreditation_flag: v === '' ? null : v })"
                    size="small"
                    :maxlength="60"
                />

                <Tooltip :title="$t('test_fields.is_hidden')">
                    <Checkbox
                        :checked="!!option.is_hidden"
                        @change="(e) => patch(index, { is_hidden: e.target.checked })"
                    >
                        <EyeInvisibleOutlined />
                    </Checkbox>
                </Tooltip>

                <span class="foe__moves">
                    <Button size="small" :disabled="index === 0" @click="move(index, -1)">↑</Button>
                    <Button size="small" :disabled="index === modelValue.length - 1" @click="move(index, 1)">↓</Button>
                </span>
            </div>
        </div>

        <Button size="small" class="foe__add" @click="addOption">
            <PlusOutlined /> {{ $t('global.add') }}
        </Button>
    </div>
</template>

<style scoped>
.foe__note {
    margin: 0 0 10px;
    font-size: 0.78rem;
    line-height: 1.45;
    color: var(--color-text-muted);
}

.foe__list { overflow-x: auto; }

.foe__head,
.foe__row {
    display: grid;
    grid-template-columns: 80px minmax(160px, 1fr) minmax(120px, 0.7fr) 70px 88px;
    gap: 8px;
    align-items: center;
    min-width: 560px;
}
.foe__head {
    font-size: 0.66rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--color-text-muted);
    padding-bottom: 6px;
}
.foe__row { padding: 4px 0; }
.foe__row--hidden { opacity: 0.55; }

.foe__order { width: 100%; }
.foe__moves { display: inline-flex; gap: 4px; }
.foe__add { margin-top: 10px; }
</style>
