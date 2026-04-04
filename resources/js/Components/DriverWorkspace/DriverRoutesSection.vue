<script setup>
import StatusBadge from "@/Components/AppShell/StatusBadge.vue";

defineProps({
    title: { type: String, default: "Routes" },
    routes: { type: Array, default: () => [] },
});
</script>

<template>
    <section class="rounded-[30px] border p-5 shadow-[0_18px_48px_rgba(15,23,42,0.06)]" style="border-color: var(--app-border); background: linear-gradient(150deg, rgba(255,255,255,0.98), rgba(246,243,237,0.94));">
        <div class="flex items-center justify-between gap-4">
            <div>
                <p class="text-[11px] font-bold uppercase tracking-[0.18em] app-muted">Coverage</p>
                <h3 class="mt-2 text-2xl font-black app-title">{{ title }}</h3>
            </div>
            <div class="text-sm app-muted">{{ routes.length }} lane{{ routes.length === 1 ? "" : "s" }}</div>
        </div>

        <div v-if="routes.length" class="mt-5 grid gap-3">
            <article v-for="route in routes" :key="route.id" class="overflow-hidden rounded-[24px] border" style="border-color: rgba(47,46,124,0.08); background: rgba(255,255,255,0.84);">
                <div class="flex items-start justify-between gap-4 px-4 py-4">
                    <div class="min-w-0">
                        <div class="text-base font-black app-title">{{ route.summary }}</div>
                        <div class="mt-2 flex flex-wrap gap-2">
                            <span v-for="location in route.locations || []" :key="`${route.id}-${location}`" class="rounded-full border px-3 py-1 text-[11px] font-bold" style="border-color: rgba(47,46,124,0.08); background: rgba(47,46,124,0.04); color: var(--app-text);">
                                {{ location }}
                            </span>
                        </div>
                    </div>
                    <StatusBadge :label="route.active ? 'Active' : 'Inactive'" :tone="route.active ? 'success' : 'neutral'" small />
                </div>
                <div v-if="route.packages?.length" class="border-t px-4 py-3" style="border-color: rgba(47,46,124,0.08); background: rgba(246,243,237,0.6);">
                    <div class="text-[11px] font-bold uppercase tracking-[0.16em] app-muted">Load fit</div>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <span v-for="item in route.packages.slice(0, 5)" :key="`${route.id}-${item}`" class="rounded-full border px-3 py-1 text-[11px] font-bold" style="border-color: var(--app-border); background: var(--app-surface-soft); color: var(--app-text);">
                            {{ item }}
                        </span>
                    </div>
                </div>
            </article>
        </div>

        <div v-else class="mt-5 rounded-[24px] border px-4 py-5 text-sm app-muted" style="border-color: rgba(47,46,124,0.08); background: rgba(255,255,255,0.82);">
            No route coverage has been configured yet.
        </div>
    </section>
</template>
