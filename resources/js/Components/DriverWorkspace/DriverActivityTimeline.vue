<script setup>
import StatusBadge from "@/Components/AppShell/StatusBadge.vue";

defineProps({
    title: { type: String, default: "Operational activity" },
    items: { type: Array, default: () => [] },
});

function toneFor(item) {
    return item.tone || {
        success: "success",
        warning: "warning",
        danger: "danger",
        info: "info",
    }[String(item.severity || "").toLowerCase()] || "neutral";
}
</script>

<template>
    <section class="rounded-[30px] border p-5 shadow-[0_18px_48px_rgba(15,23,42,0.06)]" style="border-color: var(--app-border); background: linear-gradient(150deg, rgba(255,255,255,0.98), rgba(246,243,237,0.94));">
        <div class="flex items-center justify-between gap-4">
            <div>
                <p class="text-[11px] font-bold uppercase tracking-[0.18em] app-muted">Alerts and activity</p>
                <h3 class="mt-2 text-2xl font-black app-title">{{ title }}</h3>
            </div>
            <div class="text-sm app-muted">{{ items.length }} items</div>
        </div>

        <div v-if="items.length" class="mt-5 space-y-3">
            <article v-for="item in items" :key="item.id" class="rounded-[24px] border px-4 py-4" style="border-color: rgba(47,46,124,0.08); background: rgba(255,255,255,0.82);">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="text-sm font-black app-title">{{ item.title }}</div>
                        <div class="mt-1 text-sm app-muted">{{ item.message }}</div>
                        <div v-if="item.tracking_number" class="mt-2 text-[11px] font-bold uppercase tracking-[0.16em] app-muted">{{ item.tracking_number }}</div>
                    </div>
                    <div class="shrink-0 text-right">
                        <StatusBadge :label="item.time || item.created_at || 'Now'" :tone="toneFor(item)" small />
                    </div>
                </div>
            </article>
        </div>

        <div v-else class="mt-5 rounded-[24px] border px-4 py-5 text-sm app-muted" style="border-color: rgba(47,46,124,0.08); background: rgba(255,255,255,0.82);">
            No operational alerts right now. Your workspace is clear.
        </div>
    </section>
</template>
