<script setup>
defineProps({
    title: { type: String, default: "Booking summary" },
    items: { type: Array, default: () => [] },
    badges: { type: Array, default: () => [] },
});
</script>

<template>
    <div class="lg:sticky lg:top-24 space-y-4">
        <div class="rounded-[28px] border p-5 sm:p-6" style="border-color: var(--app-border); background: var(--app-surface); box-shadow: var(--app-shadow);">
            <div class="text-[11px] font-bold uppercase tracking-[0.18em]" style="color:#2F2E7C;">{{ title }}</div>

            <div class="mt-4 space-y-3">
                <div
                    v-for="item in items"
                    :key="item.label"
                    class="rounded-[20px] border p-4"
                    style="border-color: var(--app-border); background: var(--app-surface-soft);"
                >
                    <div class="text-[11px] font-bold uppercase tracking-[0.16em] app-muted">{{ item.label }}</div>
                    <div class="mt-2 text-sm font-black app-title">{{ item.value }}</div>
                    <div v-if="item.meta" class="mt-1 text-sm app-muted">{{ item.meta }}</div>
                </div>
            </div>

            <div v-if="badges.length" class="mt-4 flex flex-wrap gap-2">
                <span
                    v-for="badge in badges"
                    :key="badge"
                    class="rounded-full border px-3 py-1.5 text-[11px] font-bold uppercase tracking-[0.14em]"
                    style="border-color: var(--app-border); background: rgba(255,255,255,0.82); color: var(--app-text);"
                >
                    {{ badge }}
                </span>
            </div>

            <slot />
        </div>
    </div>
</template>
