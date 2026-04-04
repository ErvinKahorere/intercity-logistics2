<script setup>
import { Link } from "@inertiajs/vue3";
import { ArrowRight, Clock3, ShieldCheck, Truck } from "lucide-vue-next";

const highlights = [
    { icon: Clock3, label: "Quick request", value: "Send in minutes" },
    { icon: ShieldCheck, label: "Trusted matching", value: "Best drivers first" },
    { icon: Truck, label: "Intercity coverage", value: "Built for Namibia routes" },
];
</script>

<template>
    <section class="px-4 py-12 sm:px-6 lg:px-10 lg:py-16 2xl:px-12">
        <div
            class="rounded-[34px] border px-6 py-8 sm:px-8 sm:py-10 lg:px-10"
            style="border-color: rgba(47, 46, 124, 0.14); background: var(--app-surface); box-shadow: var(--app-shadow);"
        >
            <div class="grid gap-8 xl:grid-cols-[1.15fr_0.85fr] xl:items-center">
                <div class="max-w-3xl">
                    <p class="text-[11px] font-bold uppercase tracking-[0.28em]" style="color: #2F2E7C;">Ready to send</p>
                    <h2 class="mt-3 text-3xl font-black tracking-tight app-title sm:text-4xl lg:text-5xl">
                        Find the right driver and send your parcel without the back-and-forth.
                    </h2>
                    <p class="mt-4 max-w-2xl text-sm leading-7 app-muted sm:text-base">
                        Choose your route, compare available drivers, and move forward with a clear estimate and live tracking.
                    </p>

                    <div class="mt-6 grid gap-3 sm:grid-cols-3">
                        <div
                            v-for="item in highlights"
                            :key="item.label"
                            class="rounded-[24px] border px-4 py-4"
                            style="border-color: var(--app-border); background: var(--app-surface-soft);"
                        >
                            <div class="flex h-10 w-10 items-center justify-center rounded-[18px]" style="background: rgba(47, 46, 124, 0.1); color: #2F2E7C;">
                                <component :is="item.icon" class="h-4 w-4" />
                            </div>
                            <div class="mt-4 text-[11px] font-bold uppercase tracking-[0.16em] app-muted">{{ item.label }}</div>
                            <div class="mt-2 text-sm font-black app-title">{{ item.value }}</div>
                        </div>
                    </div>
                </div>

                <div
                    class="rounded-[28px] border p-5 sm:p-6"
                    style="border-color: var(--app-border); background: rgba(47, 46, 124, 0.04);"
                >
                    <div class="text-[11px] font-bold uppercase tracking-[0.18em] app-muted">Start here</div>
                    <div class="mt-3 text-2xl font-black app-title">Choose what you need to do next.</div>

                    <div class="mt-5 space-y-3">
                        <Link
                            :href="route('parcel-requests.create')"
                            class="flex items-center justify-between rounded-[22px] border px-5 py-4 transition duration-200 hover:-translate-y-0.5"
                            style="border-color: rgba(47, 46, 124, 0.12); background: #2F2E7C; color: #FFFFFF; box-shadow: 0 18px 30px rgba(47, 46, 124, 0.16);"
                        >
                            <span>
                                <span class="block text-base font-black">Send a parcel</span>
                                <span class="mt-1 block text-sm text-white/72">Create a request and see matching drivers.</span>
                            </span>
                            <ArrowRight class="h-5 w-5" />
                        </Link>

                        <Link
                            :href="route('driver.register')"
                            class="flex items-center justify-between rounded-[22px] border px-5 py-4 transition duration-200 hover:-translate-y-0.5"
                            style="border-color: var(--app-border); background: var(--app-surface); color: var(--app-text);"
                        >
                            <span>
                                <span class="block text-base font-black app-title">Join as a driver</span>
                                <span class="mt-1 block text-sm app-muted">Set your routes and receive delivery requests.</span>
                            </span>
                            <ArrowRight class="h-5 w-5" />
                        </Link>
                    </div>

                    <div class="mt-5 rounded-[22px] border px-4 py-4 text-sm app-muted" style="border-color: var(--app-border); background: var(--app-surface);">
                        Need a quick estimate first? Use the request form above to compare route pricing before you commit.
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
