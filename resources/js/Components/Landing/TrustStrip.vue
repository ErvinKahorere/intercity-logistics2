<script setup>
defineProps({
    items: {
        type: Array,
        required: true,
    },
});
</script>

<template>
    <section class="px-4 py-4 sm:px-6 lg:px-10 2xl:px-12">
        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
            <article v-for="item in items" :key="item.label" class="rounded-[24px] border px-5 py-5" style="border-color: var(--app-border); background: rgba(255, 255, 255, 0.86); box-shadow: var(--app-shadow-soft);">
                <div class="flex items-center gap-3">
                    <div class="flex h-11 w-11 items-center justify-center rounded-[18px] text-sm font-black" style="background: #F2C900; color: #1F1F1F;">
                        {{ item.icon }}
                    </div>
                    <div>
                        <div class="text-[11px] font-bold uppercase tracking-[0.18em] app-muted">{{ item.label }}</div>
                        <div class="mt-1 text-2xl font-black app-title">{{ item.value }}</div>
                    </div>
                </div>
                <div class="mt-2 text-sm app-muted">{{ item.meta }}</div>
            </article>
        </div>
    </section>
</template>
