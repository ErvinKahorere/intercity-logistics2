<script setup>
const props = defineProps({
    rows: { type: Array, default: () => [] },
    totalLabel: { type: String, default: "Estimated total" },
    totalValue: { type: String, default: "" },
    note: { type: String, default: "" },
    accent: { type: String, default: "" },
});
</script>

<template>
    <div class="rounded-[26px] border p-5 sm:p-6" style="border-color: var(--app-border); background: var(--app-surface);">
        <div class="text-[11px] font-bold uppercase tracking-[0.18em]" style="color: #2F2E7C;">Price details</div>

        <div class="mt-4 space-y-3">
            <div
                v-for="row in rows"
                :key="row.label"
                class="flex items-center justify-between rounded-[18px] px-4 py-3"
                :style="row.highlight ? 'background: rgba(242,201,0,0.16);' : 'background: var(--app-surface-soft);'"
            >
                <span class="text-sm app-muted">{{ row.label }}</span>
                <strong class="text-sm app-title">{{ row.value }}</strong>
            </div>
        </div>

        <div
            class="mt-4 rounded-[20px] border px-4 py-4"
            :style="accent ? accent : 'border-color: rgba(47,46,124,0.18); background: rgba(47,46,124,0.05);'"
        >
            <div class="flex items-center justify-between gap-3">
                <span class="text-[11px] font-bold uppercase tracking-[0.18em] app-muted">{{ totalLabel }}</span>
                <span class="text-xl font-black app-title">{{ totalValue }}</span>
            </div>
            <p v-if="note" class="mt-2 text-sm app-muted">{{ note }}</p>
        </div>
    </div>
</template>
