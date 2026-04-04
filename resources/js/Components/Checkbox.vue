<script setup>
import { computed } from 'vue';

const emit = defineEmits(['update:checked']);
const props = defineProps({
    checked: {
        type: [Array, Boolean],
        default: false,
    },
    value: {
        default: null,
    },
});

const proxyChecked = computed({
    get() {
        return props.checked;
    },
    set(val) {
        emit('update:checked', val);
    },
});
</script>

<template>
    <input
        type="checkbox"
        :value="value"
        v-model="proxyChecked"
        class="h-4 w-4 rounded border"
        style="border-color: var(--app-border); accent-color: #2F2E7C;"
    />
</template>
