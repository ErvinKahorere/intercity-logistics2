<script setup>
defineProps({
    performance: { type: Object, default: () => ({}) },
});

const stats = [
    ["Completion rate", "completion_rate", "%", "Delivery follow-through"],
    ["Acceptance rate", "acceptance_rate", "%", "Request conversion"],
    ["Completed deliveries", "completed_deliveries", "", "Completed handovers"],
    ["Active jobs", "active_jobs", "", "Current workload"],
    ["Response speed", "response_speed", "", "Average response time"],
    ["Rating", "rating", "", "Trust indicator"],
];
</script>

<template>
    <section class="rounded-[30px] border p-5 shadow-[0_18px_48px_rgba(15,23,42,0.06)]" style="border-color: var(--app-border); background: linear-gradient(150deg, rgba(255,255,255,0.98), rgba(246,243,237,0.94));">
        <div class="flex items-center justify-between gap-4">
            <div>
                <p class="text-[11px] font-bold uppercase tracking-[0.18em] app-muted">Trust and operations</p>
                <h3 class="mt-2 text-2xl font-black app-title">Performance</h3>
            </div>
            <div class="rounded-full px-4 py-2 text-[11px] font-bold uppercase tracking-[0.16em]" style="background: rgba(47,46,124,0.08); color:#2F2E7C;">
                {{ performance.rating || "4.5" }} trusted
            </div>
        </div>

        <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            <div v-for="[label, key, suffix, meta] in stats" :key="label" class="rounded-[22px] border px-4 py-4" style="border-color: rgba(47,46,124,0.08); background: rgba(255,255,255,0.82);">
                <div class="text-[11px] font-bold uppercase tracking-[0.16em] app-muted">{{ label }}</div>
                <div class="mt-2 text-xl font-black app-title">{{ performance[key] ?? "0" }}{{ suffix }}</div>
                <div class="mt-1 text-xs app-muted">{{ meta }}</div>
            </div>
        </div>
    </section>
</template>
