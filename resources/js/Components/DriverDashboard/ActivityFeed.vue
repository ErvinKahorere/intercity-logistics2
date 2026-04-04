<script setup>
import StatusBadge from "@/Components/AppShell/StatusBadge.vue";

const props = defineProps({
    title: {
        type: String,
        default: 'Activity',
    },
    items: {
        type: Array,
        default: () => [],
    },
});
</script>

<template>
    <section class="app-panel rounded-[30px] p-5 sm:p-6">
        <div class="flex items-center justify-between gap-3">
            <div>
                <p class="text-[11px] font-bold uppercase tracking-[0.18em] app-muted">Live feed</p>
                <h3 class="mt-1 text-2xl font-black app-title">{{ title }}</h3>
            </div>
            <StatusBadge tone="brand" :label="`${items.length} items`" small />
        </div>

        <div class="mt-5 space-y-3">
            <article v-for="item in items" :key="item.id" class="rounded-[22px] border p-4" style="border-color: var(--app-border); background: var(--app-surface-soft);">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <StatusBadge :tone="item.tone || 'info'" :label="item.tracking_number || 'Update'" small />
                        </div>
                        <div class="mt-2 text-lg font-black app-title">{{ item.title }}</div>
                        <p class="mt-1 text-sm app-muted">{{ item.message }}</p>
                    </div>
                    <div class="text-[11px] font-bold uppercase tracking-[0.16em] app-muted">{{ item.time }}</div>
                </div>
            </article>
        </div>
    </section>
</template>
