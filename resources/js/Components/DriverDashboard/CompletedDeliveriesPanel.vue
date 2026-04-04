<script setup>
import StatusBadge from "@/Components/AppShell/StatusBadge.vue";

const props = defineProps({
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
                <p class="text-[11px] font-bold uppercase tracking-[0.18em] app-muted">Completed</p>
                <h3 class="mt-1 text-2xl font-black app-title">Recent deliveries</h3>
            </div>
            <StatusBadge tone="success" :label="`${items.length} done`" small />
        </div>

        <div class="mt-5 space-y-3">
            <article v-for="item in items" :key="item.id" class="rounded-[24px] border p-4" style="border-color: var(--app-border); background: var(--app-surface-soft);">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-[11px] font-bold uppercase tracking-[0.18em] app-muted">{{ item.tracking_number }}</p>
                        <h4 class="mt-2 text-lg font-black app-title">{{ item.pickup_location }} -> {{ item.dropoff_location }}</h4>
                        <p class="mt-1 text-sm app-muted">{{ item.package_type }} · {{ item.weight_kg || 0 }} kg</p>
                    </div>
                    <StatusBadge tone="success" label="Delivered" small />
                </div>
                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                    <div class="rounded-2xl px-4 py-3" style="background: var(--app-surface);">
                        <div class="text-[11px] font-bold uppercase tracking-[0.16em] app-muted">Final value</div>
                        <div class="mt-1 text-lg font-black app-title">N$ {{ Number(item.total_price || 0).toFixed(2) }}</div>
                    </div>
                    <div class="rounded-2xl px-4 py-3" style="background: var(--app-surface);">
                        <div class="text-[11px] font-bold uppercase tracking-[0.16em] app-muted">Delivered</div>
                        <div class="mt-1 text-lg font-black app-title">{{ item.delivered_time || 'Today' }}</div>
                    </div>
                </div>
            </article>
        </div>
    </section>
</template>
