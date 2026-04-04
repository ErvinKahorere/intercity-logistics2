<script setup>
import StatusBadge from "@/Components/AppShell/StatusBadge.vue";

const props = defineProps({
    filters: {
        type: Object,
        default: () => ({}),
    },
    routeOptions: {
        type: Array,
        default: () => [],
    },
    parcelOptions: {
        type: Array,
        default: () => [],
    },
    quickFilters: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(["update:quickFilter", "update:route", "update:parcelType", "update:urgency", "update:status"]);
</script>

<template>
    <div class="space-y-4">
        <div class="flex flex-wrap gap-2">
            <button
                v-for="filter in quickFilters"
                :key="filter.value"
                type="button"
                class="rounded-full border px-4 py-2 text-xs font-bold uppercase tracking-[0.16em] transition"
                :style="filters.quickFilter === filter.value ? 'background:#2F2E7C;color:#FFFFFF;border-color:#2F2E7C;' : 'background:var(--app-surface-soft);color:var(--app-text);border-color:var(--app-border);'"
                @click="emit('update:quickFilter', filter.value)"
            >
                {{ filter.label }}
            </button>
            <StatusBadge v-if="filters.quickFilter !== 'all'" tone="brand" :label="filters.quickFilter" small />
        </div>

        <div class="grid gap-3 lg:grid-cols-4 xl:grid-cols-5">
            <select class="app-control h-12 rounded-2xl border px-4" style="border-color: var(--app-border);" :value="filters.route" @change="emit('update:route', $event.target.value)">
                <option value="">All routes</option>
                <option v-for="routeOption in routeOptions" :key="routeOption" :value="routeOption">{{ routeOption }}</option>
            </select>
            <select class="app-control h-12 rounded-2xl border px-4" style="border-color: var(--app-border);" :value="filters.parcelType" @change="emit('update:parcelType', $event.target.value)">
                <option value="">Parcel type</option>
                <option v-for="parcelOption in parcelOptions" :key="parcelOption" :value="parcelOption">{{ parcelOption }}</option>
            </select>
            <select class="app-control h-12 rounded-2xl border px-4" style="border-color: var(--app-border);" :value="filters.urgency" @change="emit('update:urgency', $event.target.value)">
                <option value="">Urgency</option>
                <option value="standard">Standard</option>
                <option value="express">Express</option>
                <option value="same_day">Same day</option>
            </select>
            <select class="app-control h-12 rounded-2xl border px-4" style="border-color: var(--app-border);" :value="filters.status" @change="emit('update:status', $event.target.value)">
                <option value="">Status</option>
                <option value="matched">Matched</option>
                <option value="accepted">Accepted</option>
                <option value="picked_up">Picked up</option>
                <option value="in_transit">In transit</option>
                <option value="arrived">Arrived</option>
            </select>
            <div class="rounded-2xl border px-4 py-3 text-sm app-muted" style="border-color: var(--app-border); background: var(--app-surface-soft);">
                Fast filters for route, load, and urgency.
            </div>
        </div>
    </div>
</template>
