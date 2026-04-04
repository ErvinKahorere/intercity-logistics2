<script setup>
import { Link } from "@inertiajs/vue3";

const props = defineProps({
    mode: {
        type: String,
        default: "user",
    },
    requests: {
        type: Array,
        default: () => [],
    },
});

const supportImage = "/images/img1.jpg";
const supportCards = [
    { kicker: "Best for", title: "Quick city quotes", copy: "See route-ready options with less back and forth." },
    { kicker: "Why it works", title: "Drivers already moving", copy: "Matching is based on active intercity lanes." },
    { kicker: "Visibility", title: "Status stays live", copy: "Pickup, transit, and delivery are easy to follow." },
];
</script>

<template>
    <section class="px-4 py-4 sm:px-6 lg:px-10 2xl:px-12">
        <div class="grid gap-6 xl:grid-cols-[1.14fr_0.86fr]">
            <div class="app-panel overflow-hidden rounded-[34px] p-4 sm:p-5 lg:p-6">
                <div v-if="mode !== 'driver'" class="grid gap-5 xl:grid-cols-[0.92fr_1.08fr] xl:items-stretch">
                    <div class="relative overflow-hidden rounded-[30px] border" style="border-color: var(--app-border); background: var(--app-surface-soft);">
                        <img :src="supportImage" alt="Parcel handover" class="h-full min-h-[320px] w-full object-cover" />
                        <div class="absolute inset-x-4 bottom-4 rounded-[24px] border p-4 backdrop-blur-sm" style="border-color: rgba(255,255,255,0.26); background: rgba(31,31,31,0.76); color: #FFFFFF;">
                            <p class="text-[11px] font-bold uppercase tracking-[0.2em]" style="color: #F2C900;">Built for everyday delivery</p>
                            <p class="mt-1 text-lg font-black">Homes, shops, and business loads</p>
                        </div>
                    </div>

                    <div class="rounded-[30px] border p-5 sm:p-6" style="border-color: var(--app-border); background: var(--app-surface-soft);">
                        <div>
                            <div class="inline-flex items-center rounded-full px-4 py-2 text-xs font-bold uppercase tracking-[0.22em]" style="background: var(--app-surface); color: #2F2E7C; border: 1px solid var(--app-border);">
                                Why customers book here
                            </div>
                            <h2 class="mt-4 text-3xl font-black tracking-tight app-title sm:text-4xl">A cleaner courier flow</h2>
                            <p class="mt-3 max-w-lg text-sm leading-6 app-muted">Fast quoting, route-based matching, and fewer steps to get moving.</p>
                        </div>

                        <div class="mt-6 grid gap-3">
                            <article v-for="card in supportCards" :key="card.title" class="rounded-[24px] border px-5 py-5 transition duration-300 hover:-translate-y-0.5" style="border-color: var(--app-border); background: var(--app-surface);">
                                <p class="text-[11px] font-bold uppercase tracking-[0.18em]" style="color: #2F2E7C;">{{ card.kicker }}</p>
                                <h3 class="mt-2 text-2xl font-black app-title">{{ card.title }}</h3>
                                <p class="mt-2 text-sm leading-6 app-muted">{{ card.copy }}</p>
                            </article>
                        </div>
                    </div>
                </div>

                <div v-else class="space-y-5">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <div class="inline-flex items-center rounded-full px-4 py-2 text-xs font-bold uppercase tracking-[0.22em]" style="background: var(--app-surface-soft); color: #2F2E7C; border: 1px solid var(--app-border);">
                                Latest requests
                            </div>
                            <h2 class="mt-4 text-3xl font-black tracking-tight app-title sm:text-4xl">Fresh loads on your lanes</h2>
                        </div>
                        <Link :href="route('driver.dashboard')" class="app-outline-btn">Open dashboard</Link>
                    </div>

                    <div class="grid gap-3">
                        <article v-for="request in requests" :key="request.id" class="rounded-[26px] border p-4 sm:p-5 transition duration-300 hover:-translate-y-0.5" style="border-color: var(--app-border); background: var(--app-surface-soft);">
                            <div class="flex flex-col gap-4 lg:grid lg:grid-cols-[auto_1fr_auto] lg:items-center">
                                <div class="flex h-12 w-12 items-center justify-center rounded-[18px] text-sm font-black" style="background: #2F2E7C; color: #FFFFFF;">
                                    {{ request.pickup.charAt(0) }}{{ request.destination.charAt(0) }}
                                </div>
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="rounded-full px-3 py-1 text-[11px] font-bold uppercase tracking-[0.18em]" style="background: #F2C900; color: #1F1F1F;">{{ request.urgency }}</span>
                                        <span class="text-xs font-bold uppercase tracking-[0.18em] app-muted">{{ request.time }}</span>
                                    </div>
                                    <h3 class="mt-3 text-xl font-black app-title">{{ request.pickup }} -> {{ request.destination }}</h3>
                                    <div class="mt-2 flex flex-wrap gap-2 text-sm app-muted">
                                        <span>{{ request.parcelType }}</span>
                                        <span>&bull;</span>
                                        <span>{{ request.weight }}</span>
                                    </div>
                                </div>
                                <div class="flex gap-3 lg:justify-end">
                                    <Link :href="route('driver.dashboard')" class="app-outline-btn">View</Link>
                                    <Link :href="route('driver.dashboard')" class="app-secondary-btn">Accept</Link>
                                </div>
                            </div>
                        </article>
                    </div>
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-3 xl:grid-cols-1">
                <article class="app-panel rounded-[30px] p-5 sm:p-6">
                    <p class="text-[11px] font-bold uppercase tracking-[0.22em]" style="color: #2F2E7C;">Route first</p>
                    <p class="mt-2 text-2xl font-black app-title">Pickup to destination</p>
                    <p class="mt-2 text-sm leading-6 app-muted">Built around real intercity demand.</p>
                </article>
                <article class="app-panel rounded-[30px] p-5 sm:p-6">
                    <p class="text-[11px] font-bold uppercase tracking-[0.22em]" style="color: #2F2E7C;">Driver network</p>
                    <p class="mt-2 text-2xl font-black app-title">Active corridor coverage</p>
                    <p class="mt-2 text-sm leading-6 app-muted">Find capacity on the lanes that matter most.</p>
                </article>
                <article class="app-panel rounded-[30px] p-5 sm:p-6">
                    <p class="text-[11px] font-bold uppercase tracking-[0.22em]" style="color: #2F2E7C;">Simple tracking</p>
                    <p class="mt-2 text-2xl font-black app-title">Clear from start to finish</p>
                    <p class="mt-2 text-sm leading-6 app-muted">Status chips and updates stay easy to read.</p>
                </article>
            </div>
        </div>
    </section>
</template>

