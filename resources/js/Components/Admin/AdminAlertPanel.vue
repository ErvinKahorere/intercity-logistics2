<script setup>
import StatusBadge from "@/Components/AppShell/StatusBadge.vue";

defineProps({
    alerts: { type: Array, default: () => [] },
    title: { type: String, default: "Operational alerts" },
});
</script>

<template>
    <section class="app-panel rounded-[30px] p-6">
        <div class="flex items-center justify-between gap-3">
            <div>
                <div class="text-[11px] font-bold uppercase tracking-[0.18em]" style="color:#2F2E7C;">Alerts</div>
                <h2 class="mt-2 text-2xl font-black app-title">{{ title }}</h2>
            </div>
            <StatusBadge :label="`${alerts.length} open`" :tone="alerts.length ? 'warning' : 'success'" />
        </div>

        <div class="mt-5 grid gap-3">
            <div v-for="alert in alerts" :key="`${alert.title}-${alert.description}`" class="rounded-[24px] border p-4" style="border-color: var(--app-border); background: var(--app-surface-soft);">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <div class="text-base font-black app-title">{{ alert.title }}</div>
                        <div class="mt-1 text-sm leading-6 app-muted">{{ alert.description }}</div>
                    </div>
                    <StatusBadge :label="alert.tone || 'notice'" :tone="alert.tone || 'neutral'" small />
                </div>
            </div>

            <div v-if="!alerts.length" class="rounded-[24px] border p-4 text-sm app-muted" style="border-color: var(--app-border); background: var(--app-surface-soft);">
                No operational alerts right now.
            </div>
        </div>
    </section>
</template>

