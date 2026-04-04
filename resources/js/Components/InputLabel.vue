<script setup>
defineProps(['value']);
</script>

<template>
    <label class="mb-2 block text-sm font-bold uppercase tracking-[0.16em] app-muted">
        <span v-if="value">{{ value }}</span>
        <span v-else><slot /></span>
    </label>
</template>
