<script setup>
import { computed } from "vue";
import { Link } from "@inertiajs/vue3";

const props = defineProps({
    drivers: {
        type: Array,
        required: true,
    },
});

function createDriverInitials(name) {
    return (name || "Route Driver")
        .split(" ")
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part.charAt(0))
        .join("")
        .toUpperCase();
}

const driverCards = computed(() =>
    props.drivers.map((driver) => ({
        ...driver,
        href: driver.href || (driver.id ? route("driver.detail", driver.id) : route("find.Driver")),
        image: driver.image || null,
        initials: createDriverInitials(driver.name),
        routeStops: [driver.route, driver.secondaryRoute].filter(Boolean),
        quickStats: [
            { label: "Rating", value: driver.rating || "New" },
            { label: "Vehicle", value: driver.vehicle || "Route vehicle" },
            { label: "Loads", value: (driver.badges || []).slice(0, 2).join(" • ") || "General parcels" },
        ],
    }))
);
</script>

<template>
    <section class="relative left-1/2 right-1/2 w-screen -translate-x-1/2 overflow-hidden px-4 py-12 sm:px-6 lg:px-10 lg:py-14 2xl:px-12">
        <div class="absolute inset-x-0 top-0 -z-10 h-[420px]" style="background: radial-gradient(circle at 8% 20%, rgba(47,46,124,0.07), transparent 34%), radial-gradient(circle at 86% 18%, rgba(242,201,0,0.16), transparent 24%), linear-gradient(180deg, rgba(255,255,255,0.96), rgba(255,255,255,0));" />
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div class="max-w-2xl">
                <p class="text-[11px] font-bold uppercase tracking-[0.28em]" style="color: #2F2E7C;">Driver marketplace</p>
                <h2 class="mt-4 text-3xl font-black tracking-tight app-title sm:text-4xl lg:text-5xl">
                    Trusted drivers on the routes people need most
                </h2>
                <p class="mt-3 max-w-xl text-sm leading-6 app-muted">Browse lane-ready drivers with clearer route coverage, vehicle context, and parcel fit before you open a profile.</p>
            </div>
            <Link :href="route('find.Driver')" class="app-outline-btn self-start lg:self-end">
                View all drivers
            </Link>
        </div>

        <div class="mt-8 grid gap-5 md:grid-cols-2 xl:grid-cols-3">
            <Link
                v-for="driver in driverCards"
                :key="driver.id"
                :href="driver.href"
                class="group relative overflow-hidden rounded-[32px] border transition duration-300 hover:-translate-y-1.5"
                style="border-color: rgba(47,46,124,0.14); background: linear-gradient(180deg, rgba(255,255,255,1), rgba(248,248,248,0.98)); box-shadow: 0 22px 50px rgba(31,31,31,0.08);"
            >
                <div class="absolute inset-0 opacity-0 transition duration-300 group-hover:opacity-100" style="background: linear-gradient(180deg, rgba(242,201,0,0.08), transparent 40%, rgba(47,46,124,0.05));" />

                <div class="relative h-64 overflow-hidden">
                    <img v-if="driver.image" :src="driver.image" :alt="driver.name" class="h-full w-full object-cover transition duration-500 group-hover:scale-[1.03]" />
                    <div
                        v-else
                        class="flex h-full w-full items-center justify-center transition duration-500 group-hover:scale-[1.03]"
                        style="background: linear-gradient(180deg, rgba(47,46,124,0.10), rgba(47,46,124,0.22));"
                    >
                        <div
                            class="flex h-28 w-28 items-center justify-center rounded-[30px] border text-4xl font-black text-white"
                            style="border-color: rgba(255,255,255,0.24); background: rgba(255,255,255,0.12); backdrop-filter: blur(10px);"
                        >
                            {{ driver.initials }}
                        </div>
                    </div>
                    <div class="absolute inset-0" style="background: linear-gradient(180deg, rgba(20,20,20,0.04) 0%, rgba(20,20,20,0.10) 42%, rgba(20,20,20,0.72) 100%);" />
                    <div class="absolute inset-x-4 top-4 flex items-center justify-between gap-3">
                        <span class="rounded-full px-3 py-1 text-[11px] font-bold uppercase tracking-[0.16em]" :style="driver.available ? 'background:#F2C900;color:#1F1F1F;' : 'background:rgba(255,255,255,0.88);color:#1F1F1F;'">
                            {{ driver.available ? "Available" : "Busy" }}
                        </span>
                        <span class="rounded-full px-3 py-1 text-[11px] font-bold uppercase tracking-[0.16em]" style="background: rgba(31, 31, 31, 0.82); color: #FFFFFF;">
                            {{ driver.rating }} rating
                        </span>
                    </div>

                    <div class="absolute inset-x-5 bottom-5">
                        <div class="inline-flex items-center rounded-full px-3 py-1 text-[11px] font-bold uppercase tracking-[0.18em]" style="background: rgba(255,255,255,0.16); color: #FFFFFF; backdrop-filter: blur(10px);">
                            Intercity lane ready
                        </div>
                        <div class="mt-4 flex items-end justify-between gap-4">
                            <div>
                                <div class="text-2xl font-black text-white sm:text-[1.9rem]">{{ driver.name }}</div>
                                <div class="mt-1 text-sm text-white/80">{{ driver.vehicle }}</div>
                            </div>
                            <div class="rounded-[20px] border px-3 py-2 text-right" style="border-color: rgba(255,255,255,0.18); background: rgba(255,255,255,0.12); backdrop-filter: blur(10px);">
                                <div class="text-[10px] font-bold uppercase tracking-[0.18em] text-white/70">Parcel fit</div>
                                <div class="mt-1 text-sm font-black text-white">{{ driver.badges?.length || 1 }} load types</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="relative space-y-5 p-5 sm:p-6">
                    <div class="grid gap-3 sm:grid-cols-3">
                        <div
                            v-for="stat in driver.quickStats"
                            :key="stat.label"
                            class="rounded-[22px] border px-4 py-3"
                            style="border-color: rgba(47,46,124,0.10); background: var(--app-surface-soft);"
                        >
                            <div class="text-[10px] font-bold uppercase tracking-[0.18em] app-muted">{{ stat.label }}</div>
                            <div class="mt-2 text-sm font-black app-title">{{ stat.value }}</div>
                        </div>
                    </div>

                    <div class="rounded-[26px] border px-4 py-4" style="border-color: rgba(47,46,124,0.12); background: linear-gradient(180deg, rgba(242,201,0,0.10), rgba(255,255,255,0.7));">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <div class="text-[11px] font-bold uppercase tracking-[0.16em]" style="color: #2F2E7C;">Coverage route</div>
                                <div class="mt-2 text-base font-black app-title">{{ driver.route }}</div>
                            </div>
                            <div class="rounded-full px-3 py-1 text-[10px] font-bold uppercase tracking-[0.16em]" style="background: rgba(47,46,124,0.08); color: #2F2E7C;">
                                Active lane
                            </div>
                        </div>

                        <div class="mt-4 space-y-2">
                            <div
                                v-for="stop in driver.routeStops"
                                :key="stop"
                                class="flex items-center gap-3 text-sm"
                            >
                                <span class="h-2.5 w-2.5 rounded-full" :style="stop === driver.route ? 'background:#2F2E7C;' : 'background:#F2C900;'" />
                                <span class="app-muted">{{ stop }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <span v-for="badge in driver.badges" :key="badge" class="rounded-full px-3 py-1.5 text-[11px] font-bold uppercase tracking-[0.14em]" style="background: var(--app-surface-soft); color: var(--app-text); border: 1px solid var(--app-border);">
                            {{ badge }}
                        </span>
                    </div>

                    <div class="rounded-[22px] border px-4 py-4" style="border-color: rgba(47,46,124,0.10); background: rgba(255,255,255,0.78);">
                        <div class="text-[11px] font-bold uppercase tracking-[0.16em]" style="color: #2F2E7C;">Best for</div>
                        <div class="mt-2 text-sm leading-6 app-muted">{{ driver.parcelTypes }}</div>
                    </div>

                    <div class="flex items-center justify-between border-t pt-4" style="border-color: rgba(47,46,124,0.10);">
                        <div class="text-sm app-muted">Open profile for full route and availability details.</div>
                        <div class="text-sm font-black uppercase tracking-[0.14em]" style="color: #2F2E7C;">
                            View driver
                        </div>
                    </div>
                </div>
            </Link>
        </div>
    </section>
</template>
