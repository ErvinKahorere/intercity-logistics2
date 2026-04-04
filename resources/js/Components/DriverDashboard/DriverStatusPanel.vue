<script setup>
import StatusBadge from "@/Components/AppShell/StatusBadge.vue";

const props = defineProps({
    driverStatus: {
        type: Object,
        default: () => ({}),
    },
    vehicle: {
        type: Object,
        default: () => ({}),
    },
    profileSnapshot: {
        type: Object,
        default: () => ({}),
    },
});

const emit = defineEmits(["toggle-availability"]);
</script>

<template>
    <section class="app-panel rounded-[30px] p-5 sm:p-6 lg:p-7">
        <div class="flex flex-col gap-5 xl:flex-row xl:items-start xl:justify-between">
            <div class="flex items-start gap-4">
                <img
                    v-if="profileSnapshot.avatar"
                    :src="profileSnapshot.avatar"
                    alt="Driver avatar"
                    class="h-16 w-16 rounded-[22px] object-cover"
                />
                <div v-else class="app-avatar h-16 w-16 rounded-[22px] text-xl">{{ (profileSnapshot.name || 'D').slice(0, 1) }}</div>
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <p class="text-xl font-black app-title sm:text-2xl">{{ profileSnapshot.name }}</p>
                        <StatusBadge :tone="driverStatus.available ? 'success' : 'neutral'" :label="driverStatus.availability_label || 'Offline'" />
                        <StatusBadge v-if="driverStatus.incomplete_profile" tone="warning" label="Profile incomplete" />
                    </div>
                    <p class="mt-2 text-sm app-muted">{{ profileSnapshot.vehicle || 'Vehicle pending' }} · {{ driverStatus.capacity || 'Capacity pending' }}</p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <span v-for="routeName in (driverStatus.routes || []).slice(0, 4)" :key="routeName" class="rounded-full px-3 py-1.5 text-xs font-bold uppercase tracking-[0.14em]" style="background: var(--app-surface-soft); color: var(--app-text);">
                            {{ routeName }}
                        </span>
                    </div>
                </div>
            </div>

            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <button type="button" class="relative h-10 w-20 rounded-full transition" :class="driverStatus.available ? 'bg-[#2F2E7C]' : 'bg-neutral-400'" @click="emit('toggle-availability')">
                    <span class="absolute top-1.5 h-7 w-7 rounded-full bg-white transition" :class="driverStatus.available ? 'left-11' : 'left-1.5'"></span>
                </button>
                <div class="grid gap-2 text-right">
                    <div class="text-[11px] font-bold uppercase tracking-[0.18em] app-muted">Control</div>
                    <div class="text-sm font-black app-title">{{ driverStatus.available ? 'Receiving jobs' : 'Go online to receive jobs' }}</div>
                </div>
            </div>
        </div>

        <div class="mt-6 grid gap-4 lg:grid-cols-[1.05fr_0.95fr]">
            <div class="rounded-[24px] border p-4 sm:p-5" style="border-color: var(--app-border); background: var(--app-surface-soft);">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-[11px] font-bold uppercase tracking-[0.18em] app-muted">Control center</p>
                        <h3 class="mt-1 text-xl font-black app-title">Routes and capabilities</h3>
                    </div>
                    <StatusBadge :tone="vehicle.is_refrigerated ? 'brand' : 'neutral'" :label="vehicle.is_refrigerated ? 'Refrigerated' : 'Standard'" />
                </div>
                <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    <div class="rounded-2xl px-4 py-3" style="background: var(--app-surface);">
                        <div class="text-[11px] font-bold uppercase tracking-[0.16em] app-muted">Vehicle</div>
                        <div class="mt-1 text-lg font-black app-title">{{ driverStatus.vehicle_type || 'Not set' }}</div>
                    </div>
                    <div class="rounded-2xl px-4 py-3" style="background: var(--app-surface);">
                        <div class="text-[11px] font-bold uppercase tracking-[0.16em] app-muted">Routes</div>
                        <div class="mt-1 text-lg font-black app-title">{{ (driverStatus.routes || []).length }}</div>
                    </div>
                    <div class="rounded-2xl px-4 py-3" style="background: var(--app-surface);">
                        <div class="text-[11px] font-bold uppercase tracking-[0.16em] app-muted">Parcel types</div>
                        <div class="mt-1 text-lg font-black app-title">{{ (driverStatus.package_types || []).length }}</div>
                    </div>
                </div>
            </div>

            <div class="rounded-[24px] border p-4 sm:p-5" style="border-color: var(--app-border); background: var(--app-surface-soft);">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-[11px] font-bold uppercase tracking-[0.18em] app-muted">Profile snapshot</p>
                        <h3 class="mt-1 text-xl font-black app-title">Trusted driver view</h3>
                    </div>
                    <StatusBadge tone="brand" :label="profileSnapshot.trust_indicator || 'Trusted'" />
                </div>
                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                    <div class="rounded-2xl px-4 py-3" style="background: var(--app-surface);">
                        <div class="text-[11px] font-bold uppercase tracking-[0.16em] app-muted">Accepted today</div>
                        <div class="mt-1 text-lg font-black app-title">{{ profileSnapshot.accepted_today || 0 }}</div>
                    </div>
                    <div class="rounded-2xl px-4 py-3" style="background: var(--app-surface);">
                        <div class="text-[11px] font-bold uppercase tracking-[0.16em] app-muted">Active routes</div>
                        <div class="mt-1 text-lg font-black app-title">{{ profileSnapshot.active_routes_count || 0 }}</div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
