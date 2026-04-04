<script setup>
import { computed, ref, watch } from "vue";
import { Head } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import PageHeader from "@/Components/AppShell/PageHeader.vue";
import StatusBadge from "@/Components/AppShell/StatusBadge.vue";
import api from "@/lib/api";
import { errorToast, successToast } from "@/composables/useAppToast";

const props = defineProps({
    routes: { type: Array, default: () => [] },
    locations: { type: Array, default: () => [] },
});

const routes = ref([...(props.routes || [])]);
const searchTerm = ref("");
const activeFilter = ref("all");
const saving = ref(false);
const selectedId = ref(routes.value[0]?.id || null);
const isCreating = ref(false);
const validationMessage = ref("");
const form = ref({
    id: null,
    origin_location_id: "",
    destination_location_id: "",
    distance_km: "",
    estimated_hours: "",
    base_fare: "",
    per_km_rate: "",
    minimum_price: "",
    distance_source: "manual",
    reverse_route_enabled: true,
    is_active: true,
    operational_notes: "",
});

const filteredRoutes = computed(() => routes.value.filter((item) => {
    const query = searchTerm.value.trim().toLowerCase();
    const searchMatch = !query || [item.origin_name, item.destination_name, item.route_code, item.route_key].filter(Boolean).some((value) => String(value).toLowerCase().includes(query));
    const filterMatch = activeFilter.value === "all"
        || (activeFilter.value === "active" && item.is_active)
        || (activeFilter.value === "missing" && item.status === "missing_distance")
        || (activeFilter.value === "manual" && item.source_type === "manual")
        || (activeFilter.value === "fallback" && item.source_type === "fallback");
    return searchMatch && filterMatch;
}));

const selectedRoute = computed(() => {
    if (isCreating.value) {
        return null;
    }

    return filteredRoutes.value.find((item) => item.id === selectedId.value) || filteredRoutes.value[0] || null;
});
const metrics = computed(() => [
    { label: "Active routes", value: routes.value.filter((item) => item.is_active).length, tone: "success" },
    { label: "Missing distance", value: routes.value.filter((item) => item.status === "missing_distance").length, tone: "danger" },
    { label: "Manual", value: routes.value.filter((item) => item.source_type === "manual").length, tone: "brand" },
    { label: "Fallback", value: routes.value.filter((item) => item.source_type === "fallback").length, tone: "warning" },
]);
const routeSummary = computed(() => {
    if (!selectedRoute.value) return { title: "Create a route", description: "Set a distance, ETA, and source so pricing can use this lane cleanly." };
    if (selectedRoute.value.status === "missing_distance") return { title: "Distance missing", description: "This lane is at risk of falling back to approximate pricing until the operational distance is set." };
    return { title: "Route ready", description: "This lane has distance data and can act as a primary pricing source." };
});

function populateForm(route = null) {
    validationMessage.value = "";
    form.value = route
        ? {
            id: route.id,
            origin_location_id: route.origin_location_id,
            destination_location_id: route.destination_location_id,
            distance_km: route.distance_km ?? "",
            estimated_hours: route.estimated_hours ?? "",
            base_fare: route.base_fare ?? "",
            per_km_rate: route.per_km_rate ?? "",
            minimum_price: route.minimum_price ?? "",
            distance_source: route.distance_source || "manual",
            reverse_route_enabled: !!route.reverse_route_enabled,
            is_active: !!route.is_active,
            operational_notes: route.operational_notes || "",
        }
        : {
            id: null,
            origin_location_id: "",
            destination_location_id: "",
            distance_km: "",
            estimated_hours: "",
            base_fare: "",
            per_km_rate: "",
            minimum_price: "",
            distance_source: "manual",
            reverse_route_enabled: true,
            is_active: true,
            operational_notes: "",
        };
}

async function saveRoute() {
    saving.value = true;
    validationMessage.value = "";
    try {
        const payload = { ...form.value };
        const { data } = form.value.id
            ? await api.put(route("admin.routes.update", form.value.id), payload)
            : await api.post(route("admin.routes.store"), payload);
        routes.value = data.routes || routes.value;
        selectedId.value = data.route?.id || selectedId.value;
        isCreating.value = false;
        successToast(data.message || "Route saved.", "Route operations");
    } catch (error) {
        validationMessage.value = Object.values(error.response?.data?.errors || {}).flat()[0] || "";
        errorToast(validationMessage.value || error.response?.data?.message || "Could not save route.", "Save failed");
    } finally {
        saving.value = false;
    }
}

async function createReverse() {
    if (!form.value.id) return;
    try {
        const { data } = await api.post(route("admin.routes.reverse", form.value.id), {});
        routes.value = data.routes || routes.value;
        successToast(data.message || "Reverse route created.", "Route operations");
    } catch (error) {
        errorToast(error.response?.data?.message || "Could not create reverse route.", "Action failed");
    }
}

watch(filteredRoutes, (value) => {
    if (!value.length) {
        selectedId.value = null;
        return;
    }
    if (isCreating.value) {
        return;
    }
    if (!value.some((item) => item.id === selectedId.value)) {
        selectedId.value = value[0].id;
    }
}, { immediate: true });

watch(selectedRoute, (value) => {
    if (value && !isCreating.value && value.id !== form.value.id) {
        populateForm(value);
    }
}, { immediate: true });
</script>

<template>
    <Head title="Routes" />

    <AuthenticatedLayout>
        <PageHeader eyebrow="Admin routes" title="Route Distance Management" description="Control operational lane distances, source quality, reverse-route handling, and route-specific pricing inputs." />

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <article v-for="item in metrics" :key="item.label" class="app-panel rounded-[28px] p-5">
                <div class="flex items-center justify-between gap-3"><div><div class="text-[11px] font-bold uppercase tracking-[0.18em] app-muted">{{ item.label }}</div><div class="mt-3 text-3xl font-black app-title">{{ item.value }}</div></div><StatusBadge :label="item.label" :tone="item.tone" small /></div>
            </article>
        </section>

        <section class="grid gap-6 xl:grid-cols-[390px_minmax(0,1fr)]">
            <article class="app-panel rounded-[30px] p-5 sm:p-6">
                <div class="space-y-4">
                    <div class="rounded-[24px] border p-4" style="border-color: rgba(47,46,124,0.12); background: rgba(47,46,124,0.05);">
                        <div class="text-[11px] font-bold uppercase tracking-[0.16em] app-muted">Route list</div>
                        <div class="mt-2 text-base font-black app-title">{{ filteredRoutes.length }} lanes in this view</div>
                        <div class="mt-1 text-sm app-muted">Search by city pair or filter to missing and fallback routes.</div>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button v-for="item in ['all', 'active', 'missing', 'manual', 'fallback']" :key="item" type="button" class="rounded-full px-4 py-2 text-[11px] font-bold uppercase tracking-[0.16em]" :style="activeFilter === item ? 'background:#2F2E7C;color:#FFFFFF;' : 'background:var(--app-surface-soft);color:var(--app-text);border:1px solid var(--app-border);'" @click="activeFilter = item">{{ item }}</button>
                    </div>
                    <input v-model="searchTerm" type="text" class="app-field" placeholder="Search route or code" />
                    <button type="button" class="app-outline-btn w-full" @click="isCreating = true; selectedId = null; populateForm()">Create new route</button>
                </div>

                <div class="mt-5 grid gap-3 max-h-[70vh] overflow-y-auto pr-1">
                    <button v-for="item in filteredRoutes" :key="item.id" type="button" class="rounded-[24px] border p-4 text-left transition" :style="selectedRoute?.id === item.id ? 'border-color:#2F2E7C;background:rgba(47,46,124,0.06);' : 'border-color:var(--app-border);background:var(--app-surface-soft);'" @click="isCreating = false; selectedId = item.id; populateForm(item)">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="text-base font-black app-title">{{ item.route_key }}</div>
                                <div class="mt-1 text-sm app-muted">{{ item.distance_km || 'No distance' }} km · {{ item.distance_source }}</div>
                                <div v-if="item.warnings?.length" class="mt-2 text-xs font-semibold" style="color:#991b1b;">{{ item.warnings[0] }}</div>
                            </div>
                            <StatusBadge :label="item.status" :tone="item.status === 'missing_distance' ? 'danger' : item.is_active ? 'success' : 'neutral'" small />
                        </div>
                    </button>
                </div>
            </article>

            <article class="app-panel rounded-[30px] p-6">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <div class="text-[11px] font-bold uppercase tracking-[0.18em]" style="color:#2F2E7C;">Route editor</div>
                        <h2 class="mt-2 text-3xl font-black app-title">{{ form.id ? 'Update operational route' : 'Create operational route' }}</h2>
                    </div>
                    <StatusBadge :label="form.id ? 'editing' : 'new route'" :tone="form.id ? 'brand' : 'neutral'" />
                </div>

                <div class="mt-5 rounded-[24px] border p-5" style="border-color: rgba(47,46,124,0.12); background: rgba(47,46,124,0.05);">
                    <div class="text-base font-black app-title">{{ routeSummary.title }}</div>
                    <p class="mt-1 text-sm leading-6 app-muted">{{ routeSummary.description }}</p>
                    <div v-if="selectedRoute?.warnings?.length" class="mt-3 text-sm" style="color:#991b1b;">{{ selectedRoute.warnings.join(' · ') }}</div>
                    <div v-if="validationMessage" class="mt-3 rounded-[18px] border px-4 py-3 text-sm" style="border-color: rgba(220,38,38,0.16); background: rgba(220,38,38,0.06); color:#991b1b;">{{ validationMessage }}</div>
                </div>

                <div class="mt-6 grid gap-5 md:grid-cols-2">
                    <div>
                        <label class="mb-2 block text-sm font-bold uppercase tracking-[0.16em] app-muted">Origin city</label>
                        <select v-model="form.origin_location_id" class="app-field"><option value="">Select origin</option><option v-for="item in locations" :key="item.id" :value="item.id">{{ item.name }}</option></select>
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-bold uppercase tracking-[0.16em] app-muted">Destination city</label>
                        <select v-model="form.destination_location_id" class="app-field"><option value="">Select destination</option><option v-for="item in locations" :key="item.id" :value="item.id">{{ item.name }}</option></select>
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-bold uppercase tracking-[0.16em] app-muted">Distance km</label>
                        <input v-model="form.distance_km" type="number" step="0.01" class="app-field" />
                        <p class="mt-2 text-xs app-muted">This is the primary distance source used by pricing.</p>
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-bold uppercase tracking-[0.16em] app-muted">Estimated hours</label>
                        <input v-model="form.estimated_hours" type="number" step="0.1" class="app-field" />
                        <p class="mt-2 text-xs app-muted">Keep this aligned to the operational lane, not straight-line travel.</p>
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-bold uppercase tracking-[0.16em] app-muted">Base fare</label>
                        <input v-model="form.base_fare" type="number" step="0.01" class="app-field" />
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-bold uppercase tracking-[0.16em] app-muted">Per km rate</label>
                        <input v-model="form.per_km_rate" type="number" step="0.01" class="app-field" />
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-bold uppercase tracking-[0.16em] app-muted">Minimum charge</label>
                        <input v-model="form.minimum_price" type="number" step="0.01" class="app-field" />
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-bold uppercase tracking-[0.16em] app-muted">Route source</label>
                        <select v-model="form.distance_source" class="app-field"><option value="manual">Manual</option><option value="operational">Operational</option><option value="estimated">Estimated</option><option value="fallback">Fallback</option><option value="approximate">Approximate</option></select>
                    </div>
                    <div class="md:col-span-2">
                        <label class="mb-2 block text-sm font-bold uppercase tracking-[0.16em] app-muted">Operational notes</label>
                        <textarea v-model="form.operational_notes" rows="4" class="w-full rounded-[24px] border" style="border-color: var(--app-border); background: var(--app-surface-soft);"></textarea>
                    </div>
                </div>

                <div class="mt-5 flex flex-wrap gap-4 text-sm app-muted">
                    <label class="inline-flex items-center gap-2"><input v-model="form.reverse_route_enabled" type="checkbox" /> Allow reverse route fallback</label>
                    <label class="inline-flex items-center gap-2"><input v-model="form.is_active" type="checkbox" /> Route active</label>
                </div>

                <div class="mt-6 flex flex-wrap gap-3">
                    <button type="button" class="app-primary-btn" :disabled="saving" @click="saveRoute">{{ saving ? 'Saving...' : 'Save Route' }}</button>
                    <button v-if="form.id" type="button" class="app-outline-btn" @click="createReverse">Create Reverse Route</button>
                </div>
            </article>
        </section>
    </AuthenticatedLayout>
</template>
