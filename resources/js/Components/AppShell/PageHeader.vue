<script setup>
defineProps({
    eyebrow: {
        type: String,
        default: "",
    },
    title: {
        type: String,
        required: true,
    },
    description: {
        type: String,
        default: "",
    },
});
</script>

<template>
    <div class="app-panel overflow-hidden rounded-[30px] p-6 sm:p-8">
        <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
            <div class="max-w-3xl">
                <p v-if="eyebrow" class="text-[11px] font-bold uppercase tracking-[0.28em]" style="color: #2F2E7C;">
                    {{ eyebrow }}
                </p>
                <h1 class="mt-3 text-3xl font-black tracking-tight app-title sm:text-4xl">
                    {{ title }}
                </h1>
                <p v-if="description" class="mt-3 text-sm leading-7 app-muted sm:text-base">
                    {{ description }}
                </p>
            </div>
            <div v-if="$slots.actions" class="flex flex-wrap gap-3">
                <slot name="actions" />
            </div>
        </div>
    </div>
</template>
