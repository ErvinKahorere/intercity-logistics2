<script setup>
import { computed } from "vue";

const props = defineProps({
    items: {
        type: Array,
        required: true,
    },
});

const fallbackItems = [
    {
        route: "Windhoek -> Okahandja",
        title: "Morning deliveries usually pick up first here.",
        parcel: "General parcels",
        time: "Demand snapshot",
        badge: "Watching",
        badgeStyle: "background:var(--app-surface-soft);color:var(--app-text);border:1px solid var(--app-border);",
    },
    {
        route: "Walvis Bay -> Swakopmund",
        title: "Coastal runs stay active when drivers are available.",
        parcel: "Documents and boxes",
        time: "Demand snapshot",
        badge: "Active route",
        badgeStyle: "background:#F2C900;color:#1F1F1F;",
    },
    {
        route: "Ongwediva -> Windhoek",
        title: "Long-distance requests often need early booking.",
        parcel: "Intercity loads",
        time: "Demand snapshot",
        badge: "Planning ahead",
        badgeStyle: "background:#1F1F1F;color:#FFFFFF;",
    },
    {
        route: "Rundu -> Grootfontein",
        title: "Nearby drivers are usually matched from open routes.",
        parcel: "Flexible loads",
        time: "Demand snapshot",
        badge: "Nearby drivers",
        badgeStyle: "background:var(--app-surface-soft);color:var(--app-text);border:1px solid var(--app-border);",
    },
];

const displayItems = computed(() => (props.items?.length ? props.items : fallbackItems));
</script>

<template>
    <section class="px-4 py-12 sm:px-6 lg:px-10 lg:py-14 2xl:px-12">
        <div class="grid gap-6 xl:grid-cols-[0.82fr_1.18fr] xl:items-start">
            <div class="space-y-4">
                <p class="text-[11px] font-bold uppercase tracking-[0.28em]" style="color: #2F2E7C;">Live requests</p>
                <h2 class="text-3xl font-black tracking-tight app-title sm:text-4xl lg:text-5xl">
                    The marketplace is moving
                </h2>
                <p class="max-w-lg text-sm leading-6 app-muted">
                    Fresh requests show where demand is happening right now.
                </p>
            </div>

            <div class="grid gap-3 md:grid-cols-2">
                <article v-for="item in displayItems" :key="`${item.route}-${item.title}`" class="rounded-[26px] border p-5 transition duration-300 hover:-translate-y-0.5" style="border-color: var(--app-border); background: var(--app-surface); box-shadow: var(--app-shadow-soft);">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="text-base font-black app-title">{{ item.route }}</div>
                            <div class="mt-1 text-sm app-muted">{{ item.title }}</div>
                        </div>
                        <span class="rounded-full px-3 py-1 text-[11px] font-bold uppercase tracking-[0.16em]" :style="item.badgeStyle">
                            {{ item.badge }}
                        </span>
                    </div>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <span class="rounded-full px-3 py-1 text-[11px] font-bold uppercase tracking-[0.16em]" style="background: var(--app-surface-soft); color: var(--app-text); border: 1px solid var(--app-border);">
                            {{ item.parcel }}
                        </span>
                        <span class="rounded-full px-3 py-1 text-[11px] font-bold uppercase tracking-[0.16em]" style="background: var(--app-surface-soft); color: var(--app-text); border: 1px solid var(--app-border);">
                            {{ item.time }}
                        </span>
                    </div>
                </article>
            </div>
        </div>
    </section>
</template>
