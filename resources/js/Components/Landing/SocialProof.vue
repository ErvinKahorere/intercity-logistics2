<script setup>
defineProps({
    testimonials: {
        type: Array,
        required: true,
    },
    stats: {
        type: Array,
        required: true,
    },
});
</script>

<template>
    <section class="px-4 py-12 sm:px-6 lg:px-10 lg:py-14 2xl:px-12">
        <div class="grid gap-6 xl:grid-cols-[0.9fr_1.1fr]">
            <div class="rounded-[30px] border p-6 sm:p-7" style="border-color: var(--app-border); background: var(--app-surface); box-shadow: var(--app-shadow-soft);">
                <p class="text-[11px] font-bold uppercase tracking-[0.28em]" style="color: #2F2E7C;">Trusted on the route</p>
                <h2 class="mt-4 text-3xl font-black app-title sm:text-4xl">Built for real delivery work</h2>
                <div class="mt-6 grid gap-3 sm:grid-cols-3">
                    <div v-for="item in stats" :key="item.label" class="rounded-[22px] border px-4 py-4" style="border-color: var(--app-border); background: var(--app-surface-soft);">
                        <div class="text-2xl font-black app-title">{{ item.value }}</div>
                        <div class="mt-1 text-sm app-muted">{{ item.label }}</div>
                    </div>
                </div>
            </div>

            <div class="grid gap-3">
                <article v-for="item in testimonials" :key="item.name" class="rounded-[28px] border p-5 sm:p-6" style="border-color: var(--app-border); background: rgba(255, 255, 255, 0.88); box-shadow: var(--app-shadow-soft);">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <div class="text-lg font-black app-title">{{ item.name }}</div>
                            <div class="text-sm app-muted">{{ item.role }}</div>
                        </div>
                        <span class="rounded-full px-3 py-1 text-[11px] font-bold uppercase tracking-[0.16em]" style="background: #F2C900; color: #1F1F1F;">
                            {{ item.rating }}
                        </span>
                    </div>
                    <p class="mt-4 text-sm leading-7 app-muted">{{ item.quote }}</p>
                </article>
            </div>
        </div>
    </section>
</template>
