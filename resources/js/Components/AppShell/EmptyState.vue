<script setup>
defineProps({
    title: {
        type: String,
        required: true,
    },
    description: {
        type: String,
        required: true,
    },
    icon: {
        type: String,
        default: "i",
    },
});
</script>

<template>
    <div class="app-panel border-dashed px-6 py-10 text-center">
        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl text-xl font-black" style="background: rgba(47, 46, 124, 0.1); color: #2F2E7C;">
            {{ icon }}
        </div>
        <h3 class="mt-4 text-xl font-black app-title">{{ title }}</h3>
        <p class="mx-auto mt-2 max-w-md text-sm leading-6 app-muted">{{ description }}</p>
        <div v-if="$slots.action" class="mt-5">
            <slot name="action" />
        </div>
    </div>
</template>
