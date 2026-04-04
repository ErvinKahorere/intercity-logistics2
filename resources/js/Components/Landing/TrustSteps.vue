<script setup>
defineProps({
    steps: {
        type: Array,
        required: true,
    },
});
</script>

<template>
    <section class="px-4 py-12 sm:px-6 lg:px-10 lg:py-14 2xl:px-12">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-[11px] font-bold uppercase tracking-[0.28em]" style="color: #2F2E7C;">How it works</p>
                <h2 class="mt-4 text-3xl font-black tracking-tight app-title sm:text-4xl lg:text-5xl">
                    Three quick steps
                </h2>
            </div>
        </div>

        <div class="mt-8 grid gap-4 md:grid-cols-3">
            <article v-for="step in steps" :key="step.title" class="rounded-[28px] border p-5 sm:p-6" style="border-color: var(--app-border); background: var(--app-surface); box-shadow: var(--app-shadow-soft);">
                <div class="flex h-14 w-14 items-center justify-center rounded-[18px] text-xl font-black" style="background: #2F2E7C; color: #F2C900;">
                    {{ step.icon }}
                </div>
                <p class="mt-4 text-[11px] font-bold uppercase tracking-[0.18em]" style="color: #2F2E7C;">
                    {{ step.number }}
                </p>
                <h3 class="mt-2 text-2xl font-black app-title">{{ step.title }}</h3>
                <p class="mt-2 text-sm app-muted">{{ step.description }}</p>
            </article>
        </div>
    </section>
</template>
