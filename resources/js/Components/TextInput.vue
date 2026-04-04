<script setup>
import { onMounted, ref } from 'vue';

defineProps(['modelValue']);
defineEmits(['update:modelValue']);

const input = ref(null);

onMounted(() => {
    if (input.value?.hasAttribute('autofocus')) {
        input.value.focus();
    }
});

defineExpose({ focus: () => input.value?.focus() });
</script>

<template>
    <input
        ref="input"
        class="w-full rounded-2xl border px-4 py-3 text-sm shadow-sm"
        style="border-color: var(--app-border); background: var(--app-surface-soft); color: var(--app-text);"
        :value="modelValue"
        @input="$emit('update:modelValue', $event.target.value)"
    />
</template>
