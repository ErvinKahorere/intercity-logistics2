<script setup>
defineProps({
    capabilities: {
        type: Array,
        required: true,
    },
});
</script>

<template>
    <section class="px-4 py-12 sm:px-6 lg:px-10 lg:py-14 2xl:px-12">
        <div class="grid gap-6 xl:grid-cols-[0.78fr_1.22fr] xl:items-start">
            <div>
                <p class="text-[11px] font-bold uppercase tracking-[0.28em]" style="color: #2F2E7C;">Parcel types</p>
                <h2 class="mt-4 text-3xl font-black tracking-tight app-title sm:text-4xl lg:text-5xl">
                    From documents to heavy loads
                </h2>
                <p class="mt-3 max-w-lg text-sm leading-6 app-muted">
                    One network for everyday parcels and specialist freight.
                </p>
            </div>

            <div class="app-panel rounded-[36px] p-5 sm:p-6">
                <div class="flex flex-wrap gap-3">
                    <span
                        v-for="capability in capabilities"
                        :key="capability"
                        class="rounded-full border px-4 py-3 text-sm font-bold transition duration-300 hover:-translate-y-0.5"
                        style="border-color: var(--app-border); background: var(--app-surface-soft); color: var(--app-text); box-shadow: var(--app-shadow-soft);"
                    >
                        {{ capability }}
                    </span>
                </div>
            </div>
        </div>
    </section>
</template>

