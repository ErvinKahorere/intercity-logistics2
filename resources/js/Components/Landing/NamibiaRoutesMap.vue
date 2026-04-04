<script setup>
import { computed, ref } from "vue";

const props = defineProps({
    cities: {
        type: Array,
        required: true,
    },
    routes: {
        type: Array,
        required: true,
    },
});

const selectedCityId = ref(props.cities[0]?.id ?? null);
const selectedCity = computed(() => props.cities.find((city) => city.id === selectedCityId.value) ?? props.cities[0]);
const selectedRoutes = computed(() => props.routes.filter((route) => route.from === selectedCity.value?.id || route.to === selectedCity.value?.id));

function toneForDemand(demand) {
    return demand === "High demand"
        ? { background: "#F2C900", color: "#1F1F1F" }
        : { background: "rgba(47, 46, 124, 0.1)", color: "#2F2E7C" };
}
</script>

<template>
    <section class="px-4 py-10 sm:px-6 lg:px-8 lg:py-12">
        <div class="mx-auto max-w-7xl">
            <div class="mx-auto max-w-3xl text-center">
                <p class="text-[11px] font-bold uppercase tracking-[0.28em]" style="color: #2F2E7C;">Route network</p>
                <h2 class="mt-4 text-3xl font-black tracking-tight app-title sm:text-4xl lg:text-5xl">
                    Live intercity routes across Namibia
                </h2>
                <p class="mt-4 text-base leading-7 app-muted sm:text-lg">
                    A visual route layer built for future live data. Hover or tap cities to see route demand, delivery times, and driver availability.
                </p>
            </div>

            <div class="mt-8 grid gap-5 xl:grid-cols-[1.24fr_0.76fr]">
                <div class="app-panel overflow-hidden rounded-[36px] p-4 sm:p-6">
                    <div class="rounded-[28px] p-3 sm:p-5" style="background: var(--app-surface-soft);">
                        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <p class="text-[11px] font-bold uppercase tracking-[0.24em]" style="color: #2F2E7C;">Interactive map</p>
                                <p class="mt-2 text-xl font-black app-title">Top routes and moving parcel lanes</p>
                            </div>
                            <div class="flex flex-wrap gap-2 text-xs font-semibold app-muted">
                                <span class="inline-flex items-center gap-2 rounded-full border px-3 py-2" style="border-color: #D9D9D9; background: var(--app-surface);">
                                    <span class="h-2.5 w-2.5 rounded-full" style="background: #2F2E7C;"></span>
                                    Active route
                                </span>
                                <span class="inline-flex items-center gap-2 rounded-full border px-3 py-2" style="border-color: #D9D9D9; background: var(--app-surface);">
                                    <span class="h-2.5 w-2.5 rounded-full" style="background: #F2C900;"></span>
                                    High demand
                                </span>
                                <span class="inline-flex items-center gap-2 rounded-full border px-3 py-2" style="border-color: #D9D9D9; background: var(--app-surface);">
                                    <span class="h-2.5 w-2.5 rounded-full" style="background: #1F1F1F;"></span>
                                    Drivers available
                                </span>
                            </div>
                        </div>

                        <div class="relative overflow-hidden rounded-[24px] border" style="border-color: #D9D9D9; background: var(--app-surface);">
                            <div class="absolute inset-0 opacity-40" style="background-image: linear-gradient(rgba(217, 217, 217, 0.8) 1px, transparent 1px), linear-gradient(90deg, rgba(217, 217, 217, 0.8) 1px, transparent 1px); background-size: 34px 34px;"></div>
                            <div class="relative aspect-[5/6] min-h-[420px] w-full sm:min-h-[520px]">
                                <svg viewBox="0 0 620 700" preserveAspectRatio="xMidYMid meet" class="h-full w-full">
                                    <defs>
                                        <filter id="softGlow">
                                            <feGaussianBlur stdDeviation="4" result="coloredBlur" />
                                            <feMerge>
                                                <feMergeNode in="coloredBlur" />
                                                <feMergeNode in="SourceGraphic" />
                                            </feMerge>
                                        </filter>
                                    </defs>

                                    <path
                                        d="M198 36 L274 52 L358 72 L446 114 L518 170 L544 258 L504 348 L520 460 L470 594 L388 650 L280 676 L204 644 L116 580 L82 492 L102 396 L68 312 L92 226 L136 154 L182 82 Z"
                                        fill="#F4F4F4"
                                        stroke="#2F2E7C"
                                        stroke-opacity="0.2"
                                        stroke-width="8"
                                    />

                                    <g v-for="routeItem in routes" :key="routeItem.id">
                                        <line
                                            :x1="cities.find((city) => city.id === routeItem.from)?.x"
                                            :y1="cities.find((city) => city.id === routeItem.from)?.y"
                                            :x2="cities.find((city) => city.id === routeItem.to)?.x"
                                            :y2="cities.find((city) => city.id === routeItem.to)?.y"
                                            :stroke="routeItem.demand === 'High demand' ? '#F2C900' : '#2F2E7C'"
                                            stroke-width="4.5"
                                            stroke-linecap="round"
                                            stroke-dasharray="8 10"
                                            class="route-line"
                                        />
                                        <circle
                                            :cx="cities.find((city) => city.id === routeItem.from)?.x"
                                            :cy="cities.find((city) => city.id === routeItem.from)?.y"
                                            r="5"
                                            :fill="routeItem.demand === 'High demand' ? '#F2C900' : '#2F2E7C'"
                                            filter="url(#softGlow)"
                                        >
                                            <animateMotion
                                                :dur="routeItem.duration"
                                                repeatCount="indefinite"
                                                :path="`M ${cities.find((city) => city.id === routeItem.from)?.x} ${cities.find((city) => city.id === routeItem.from)?.y} L ${cities.find((city) => city.id === routeItem.to)?.x} ${cities.find((city) => city.id === routeItem.to)?.y}`"
                                            />
                                        </circle>
                                    </g>

                                    <g v-for="city in cities" :key="city.id">
                                        <circle
                                            :cx="city.x"
                                            :cy="city.y"
                                            r="16"
                                            :fill="selectedCityId === city.id ? '#F2C900' : '#2F2E7C'"
                                            fill-opacity="0.18"
                                            class="cursor-pointer transition-all duration-300"
                                            @mouseenter="selectedCityId = city.id"
                                            @click="selectedCityId = city.id"
                                        />
                                        <circle
                                            :cx="city.x"
                                            :cy="city.y"
                                            r="6.5"
                                            :fill="selectedCityId === city.id ? '#F2C900' : '#2F2E7C'"
                                            class="cursor-pointer transition-all duration-300"
                                            @mouseenter="selectedCityId = city.id"
                                            @click="selectedCityId = city.id"
                                        />
                                        <circle
                                            :cx="city.x"
                                            :cy="city.y"
                                            r="11"
                                            fill="none"
                                            :stroke="selectedCityId === city.id ? '#F2C900' : '#2F2E7C'"
                                            stroke-opacity="0.25"
                                            class="city-pulse"
                                        />
                                        <text :x="city.labelX ?? city.x + 16" :y="city.labelY ?? city.y - 12" class="fill-[#1F1F1F] text-[13px] font-bold tracking-[0.04em]">
                                            {{ city.name }}
                                        </text>
                                    </g>
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="space-y-5">
                    <aside class="app-panel rounded-[32px] p-5">
                        <p class="text-[11px] font-bold uppercase tracking-[0.24em]" style="color: #2F2E7C;">Selected city</p>
                        <div v-if="selectedCity" class="mt-4">
                            <div class="flex items-center justify-between gap-4">
                                <h3 class="text-2xl font-black app-title">{{ selectedCity.name }}</h3>
                                <span class="rounded-full px-3 py-1 text-xs font-bold uppercase tracking-[0.18em]" style="background: #F2C900; color: #1F1F1F;">
                                    {{ selectedCity.region }}
                                </span>
                            </div>
                            <p class="mt-3 text-sm leading-6 app-muted">{{ selectedCity.summary }}</p>
                        </div>

                        <div class="mt-5 space-y-3">
                            <article v-for="routeItem in selectedRoutes" :key="routeItem.id" class="rounded-[24px] border p-4" style="border-color: #D9D9D9; background: var(--app-surface-soft);">
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <h4 class="font-black app-title">{{ routeItem.label }}</h4>
                                        <p class="mt-1 text-sm app-muted">{{ routeItem.averageTime }}</p>
                                    </div>
                                    <span class="rounded-full px-3 py-1 text-xs font-bold uppercase tracking-[0.18em]" :style="toneForDemand(routeItem.demand)">
                                        {{ routeItem.demand }}
                                    </span>
                                </div>
                                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                    <div class="rounded-2xl px-3 py-3" style="background: var(--app-surface);">
                                        <p class="text-[11px] font-bold uppercase tracking-[0.18em] app-muted">Drivers</p>
                                        <p class="mt-1 text-lg font-black app-title">{{ routeItem.drivers }}</p>
                                    </div>
                                    <div class="rounded-2xl px-3 py-3" style="background: var(--app-surface);">
                                        <p class="text-[11px] font-bold uppercase tracking-[0.18em] app-muted">Average time</p>
                                        <p class="mt-1 text-lg font-black app-title">{{ routeItem.time }}</p>
                                    </div>
                                </div>
                            </article>
                        </div>
                    </aside>

                    <div class="app-panel rounded-[32px] p-5">
                        <p class="text-[11px] font-bold uppercase tracking-[0.24em]" style="color: #2F2E7C;">Top active routes</p>
                        <div class="mt-4 space-y-3">
                            <article v-for="routeItem in routes.slice(0, 3)" :key="`${routeItem.id}-summary`" class="rounded-[24px] border p-4" style="border-color: #D9D9D9; background: var(--app-surface-soft);">
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <h4 class="font-black app-title">{{ routeItem.label }}</h4>
                                        <p class="mt-1 text-sm app-muted">{{ routeItem.averageTime }}</p>
                                    </div>
                                    <span class="rounded-full px-3 py-1 text-xs font-bold uppercase tracking-[0.16em]" style="background: #2F2E7C; color: #FFFFFF;">
                                        {{ routeItem.drivers }} drivers
                                    </span>
                                </div>
                            </article>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>
.route-line {
    animation: dashRoute 12s linear infinite;
}

.city-pulse {
    animation: pulseCity 2.4s ease-in-out infinite;
}

@keyframes dashRoute {
    to {
        stroke-dashoffset: -120;
    }
}

@keyframes pulseCity {
    0%,
    100% {
        opacity: 0.2;
        transform: scale(1);
    }

    50% {
        opacity: 0.9;
        transform: scale(1.06);
    }
}
</style>
