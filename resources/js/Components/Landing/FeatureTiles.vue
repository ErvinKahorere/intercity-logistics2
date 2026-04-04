<script setup>
defineProps({
    items: {
        type: Array,
        required: true,
    },
});
</script>

<template>
    <section class="px-4 py-12 sm:px-6 lg:px-10 lg:py-14 2xl:px-12">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div class="max-w-2xl">
                <p class="text-[11px] font-bold uppercase tracking-[0.28em]" style="color: #2F2E7C;">Why InterCity</p>
                <h2 class="mt-4 text-3xl font-black tracking-tight app-title sm:text-4xl lg:text-5xl">
                    Built for fast delivery decisions
                </h2>
            </div>
        </div>

        <div class="mt-8 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <article v-for="item in items" :key="item.title" class="rounded-[28px] border p-5 sm:p-6" style="border-color: var(--app-border); background: rgba(255, 255, 255, 0.86); box-shadow: var(--app-shadow-soft);">
                <div class="flex h-12 w-12 items-center justify-center rounded-[18px] text-lg font-black" style="background: var(--app-surface-soft); color: #2F2E7C; border: 1px solid var(--app-border);">
                    {{ item.icon }}
                </div>
                <h3 class="mt-5 text-2xl font-black app-title">{{ item.title }}</h3>
                <p class="mt-2 text-sm app-muted">{{ item.copy }}</p>
            </article>
        </div>
    </section>
</template>
