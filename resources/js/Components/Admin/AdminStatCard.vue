<script setup>
import StatusBadge from "@/Components/AppShell/StatusBadge.vue";

defineProps({
    label: { type: String, default: "" },
    value: { type: [String, Number], default: 0 },
    meta: { type: String, default: "" },
    tone: { type: String, default: "neutral" },
});
</script>

<template>
    <article class="app-panel rounded-[28px] p-5">
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <div class="text-[11px] font-bold uppercase tracking-[0.18em] app-muted">{{ label }}</div>
                <div class="mt-3 text-3xl font-black app-title">{{ value }}</div>
                <div v-if="meta" class="mt-2 text-sm leading-6 app-muted">{{ meta }}</div>
            </div>
            <StatusBadge :label="label" :tone="tone" small />
        </div>
    </article>
</template>
