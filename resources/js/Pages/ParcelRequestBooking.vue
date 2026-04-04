<script setup>
import { computed, ref, watch } from "vue";
import { Head, useForm } from "@inertiajs/vue3";
import PublicLayout from "@/Layouts/PublicLayout.vue";
import StatusBadge from "@/Components/AppShell/StatusBadge.vue";
import api from "@/lib/api";
import { errorToast, successToast } from "@/composables/useAppToast";

const props = defineProps({
    locations: Array,
    packageTypes: Array,
    cityRoutes: Array,
    canLogin: Boolean,
    canRegister: Boolean,
});

const form = useForm({
    pickup_location_id: "",
    dropoff_location_id: "",
    package_type_id: "",
    pickup_address: "",
    dropoff_address: "",
    receiver_name: "",
    receiver_phone: "",
    weight_kg: "",
    load_size: "small",
    urgency_level: "standard",
    client_offer_price: "",
    declared_value: "",
    notes: "",
    selected_driver_id: "",
});
const quoteBusy = ref(false);
const generatedQuotation = ref(null);
const asyncPricingPreview = ref(null);
const suggestedDrivers = ref([]);
let previewTimer = null;
let previewRequestToken = 0;

const urgencyLabels = {
    standard: "Standard",
    express: "Express",
    same_day: "Same Day",
};

const loadSizeLabels = {
    small: "Small",
    medium: "Medium",
    large: "Large",
    heavy: "Heavy",
    oversized: "Oversized",
};

const selectedRoute = computed(() =>
    (props.cityRoutes || []).find(
        (route) =>
            Number(route.origin_location_id) === Number(form.pickup_location_id) &&
            Number(route.destination_location_id) === Number(form.dropoff_location_id)
    )
);

const selectedPickup = computed(() =>
    (props.locations || []).find((location) => Number(location.id) === Number(form.pickup_location_id))
);

const selectedDropoff = computed(() =>
    (props.locations || []).find((location) => Number(location.id) === Number(form.dropoff_location_id))
);

const localPricingPreview = computed(() => {
    const distance = Number(selectedRoute.value?.distance_km ?? 0);
    const basePrice = Number(selectedRoute.value?.base_fare ?? 0);
    const weight = Math.max(Number(form.weight_kg || 0), 0);
    const weightSurcharge =
        weight <= 5
            ? 0
            : weight <= 15
              ? 55
              : weight <= 30
                ? 120
                : weight <= 60
                  ? 240
                  : 240 + (weight - 60) * 4.8;

    const urgencySurcharge =
        form.urgency_level === "same_day"
            ? (basePrice + weightSurcharge) * 0.5
            : form.urgency_level === "express"
              ? (basePrice + weightSurcharge) * 0.25
              : 0;

    const totalPrice = basePrice + weightSurcharge + urgencySurcharge;
    const clientOffer = form.client_offer_price === "" ? null : Number(form.client_offer_price || 0);

    return {
        distance,
        estimatedHours: Number(selectedRoute.value?.estimated_hours ?? 0),
        basePrice,
        weightSurcharge,
        urgencySurcharge,
        totalPrice,
        clientOffer,
        offerGap: clientOffer === null ? null : totalPrice - clientOffer,
    };
});

const pricingPreview = computed(() => {
    if (!asyncPricingPreview.value) {
        return localPricingPreview.value;
    }

    const remote = asyncPricingPreview.value;

    return {
        distance: Number(remote.distance_km || 0),
        estimatedHours: Number(remote.estimated_hours || 0),
        basePrice: Number(remote.base_price || 0),
        distance_fee: Number(remote.distance_fee || 0),
        weightSurcharge: Number(remote.weight_surcharge || 0),
        urgencySurcharge: Number(remote.urgency_surcharge || 0),
        special_handling_fee: Number(remote.special_handling_fee || 0),
        totalPrice: Number(remote.total_price || 0),
        clientOffer: form.client_offer_price === "" ? null : Number(form.client_offer_price || 0),
        offerGap: form.client_offer_price === "" ? null : Number(remote.total_price || 0) - Number(form.client_offer_price || 0),
        pricing_breakdown: remote.pricing_breakdown || {},
    };
});
const selectedSuggestedDriver = computed(() =>
    suggestedDrivers.value.find((driver) => Number(driver.id) === Number(form.selected_driver_id)) || null
);
const pricingBreakdownRows = computed(() => [
    { label: "Base dispatch fee", value: `N$ ${pricingPreview.value.basePrice.toFixed(2)}`, detail: "Lane activation and booking setup" },
    { label: "Distance charge", value: `N$ ${Number(pricingPreview.value.distance_fee || 0).toFixed(2)}`, detail: `${pricingPreview.value.distance} km route distance` },
    { label: "Weight adjustment", value: `N$ ${pricingPreview.value.weightSurcharge.toFixed(2)}`, detail: `${form.weight_kg || 0} kg declared weight` },
    { label: "Urgency surcharge", value: `N$ ${pricingPreview.value.urgencySurcharge.toFixed(2)}`, detail: urgencyLabels[form.urgency_level] || "Standard" },
    { label: "Special handling", value: `N$ ${Number(pricingPreview.value.special_handling_fee || 0).toFixed(2)}`, detail: form.notes ? "Triggered from handling notes" : "No extra handling detected" },
]);
const distanceSourceLabel = computed(() => {
    const source = pricingPreview.value.pricing_breakdown?.distance_source || "operational";
    return String(source).replaceAll("_", " ").replace(/\b\w/g, (char) => char.toUpperCase());
});
const quoteReadinessLabel = computed(() => {
    if (!canPreview.value) {
        return "Select route and parcel details to activate pricing";
    }

    if (quoteBusy.value) {
        return "Refreshing route-aware pricing";
    }

    return "Live estimate ready";
});

const canPreview = computed(() =>
    !!form.pickup_location_id &&
    !!form.dropoff_location_id &&
    !!form.package_type_id &&
    form.pickup_location_id !== form.dropoff_location_id
);

function applyRoutePreset(route) {
    form.pickup_location_id = route.origin_location_id;
    form.dropoff_location_id = route.destination_location_id;
}

watch(
    () => [form.pickup_location_id, form.dropoff_location_id, form.package_type_id, form.weight_kg, form.load_size, form.urgency_level, form.notes, form.selected_driver_id],
    () => {
        window.clearTimeout(previewTimer);
        if (!canPreview.value) {
            asyncPricingPreview.value = null;
            suggestedDrivers.value = [];
            return;
        }

        asyncPricingPreview.value = null;
        previewTimer = window.setTimeout(loadPreview, 300);
    }
);

async function loadPreview() {
    if (!canPreview.value) return;

    const requestToken = ++previewRequestToken;
    quoteBusy.value = true;
    try {
        const { data } = await api.get(route("parcel-requests.preview"), {
            params: {
                pickup_location_id: form.pickup_location_id,
                dropoff_location_id: form.dropoff_location_id,
                package_type_id: form.package_type_id,
                weight_kg: form.weight_kg || null,
                load_size: form.load_size,
                urgency_level: form.urgency_level,
                notes: form.notes || null,
                selected_driver_id: form.selected_driver_id || null,
            },
        });

        if (requestToken !== previewRequestToken) {
            return;
        }

        asyncPricingPreview.value = data.quote || null;
        suggestedDrivers.value = Array.isArray(data.drivers) ? data.drivers.slice(0, 4) : [];
        if (form.selected_driver_id && !suggestedDrivers.value.some((driver) => Number(driver.id) === Number(form.selected_driver_id))) {
            form.selected_driver_id = "";
        }
    } catch (error) {
        if (requestToken !== previewRequestToken) {
            return;
        }

        errorToast(error.response?.data?.message || "Could not refresh the live estimate.", "Quote preview failed");
    } finally {
        if (requestToken === previewRequestToken) {
            quoteBusy.value = false;
        }
    }
}

async function generateQuotation() {
    if (!canPreview.value) return;

    quoteBusy.value = true;
    try {
        const { data } = await api.post(route("quotations.preview"), {
            pickup_location_id: form.pickup_location_id,
            dropoff_location_id: form.dropoff_location_id,
            package_type_id: form.package_type_id,
            pickup_address: form.pickup_address,
            dropoff_address: form.dropoff_address,
            receiver_name: form.receiver_name,
            receiver_phone: form.receiver_phone,
            weight_kg: form.weight_kg || null,
            load_size: form.load_size,
            urgency_level: form.urgency_level,
            notes: form.notes,
            selected_driver_id: form.selected_driver_id || null,
        });

        generatedQuotation.value = data.quotation;
        successToast("Quotation generated and ready to download.", "Quote ready");
    } catch (error) {
        errorToast(error.response?.data?.message || "Could not generate quotation.", "Quote failed");
    } finally {
        quoteBusy.value = false;
    }
}

const submit = () => form.post(route("parcel-requests.store"));
</script>

<template>
    <Head title="Book a Parcel" />

    <PublicLayout>
        <main class="py-8 sm:py-10">
            <div class="mx-auto max-w-[1680px] px-4 sm:px-6 lg:px-8">
                <section class="grid gap-8 xl:grid-cols-[1.5fr_0.95fr]">
                    <div class="space-y-6">
                        <div class="app-panel px-6 py-8 md:px-8 md:py-10">
                            <div class="inline-flex rounded-full px-4 py-2 text-xs font-bold uppercase tracking-[0.3em]" style="background: #F2C900; color: #1F1F1F;">
                                Smart Parcel Dispatch
                            </div>
                            <h1 class="mt-5 max-w-3xl text-4xl font-black leading-tight app-title sm:text-5xl">
                                Book intercity delivery with route-aware pricing, live tracking, and driver alerts.
                            </h1>
                            <p class="mt-4 max-w-2xl text-base leading-7 app-muted sm:text-lg">
                                Choose a city route, define weight and urgency, and optionally send your own price offer to matching drivers.
                            </p>

                            <div class="mt-8 grid gap-4 md:grid-cols-3">
                                <div class="rounded-3xl border p-5" style="border-color: #D9D9D9; background: var(--app-surface-soft);">
                                    <div class="text-sm font-semibold uppercase tracking-wide" style="color: #2F2E7C;">Driver Alerts</div>
                                    <p class="mt-2 text-sm app-muted">Matching drivers get route alerts with urgency, estimate, and your offer when you add one.</p>
                                </div>
                                <div class="rounded-3xl border p-5" style="border-color: #D9D9D9; background: var(--app-surface-soft);">
                                    <div class="text-sm font-semibold uppercase tracking-wide" style="color: #2F2E7C;">Pricing Engine</div>
                                    <p class="mt-2 text-sm app-muted">Distance, weight, and urgency are all calculated before you submit.</p>
                                </div>
                                <div class="rounded-3xl border p-5" style="border-color: #D9D9D9; background: var(--app-surface-soft);">
                                    <div class="text-sm font-semibold uppercase tracking-wide" style="color: #2F2E7C;">Verified Drivers</div>
                                    <p class="mt-2 text-sm app-muted">Verified drivers are surfaced with stronger trust cues during matching and booking review.</p>
                                </div>
                            </div>
                        </div>

                        <div class="app-panel p-6">
                            <div class="flex items-center justify-between gap-4">
                                <div>
                                    <h2 class="text-2xl font-black app-title">Featured City Routes</h2>
                                    <p class="mt-1 text-sm app-muted">Popular Namibia corridors with preset distance and estimated travel time.</p>
                                </div>
                                <div class="rounded-full px-4 py-2 text-xs font-semibold uppercase tracking-[0.25em]" style="background: var(--app-surface-soft); color: var(--app-text);">
                                    Route Matrix
                                </div>
                            </div>

                            <div class="mt-5 grid gap-4 md:grid-cols-2">
                                <button
                                    v-for="routePreset in cityRoutes?.slice(0, 6) || []"
                                    :key="routePreset.id"
                                    type="button"
                                    @click="applyRoutePreset(routePreset)"
                                    class="group rounded-[24px] border p-5 text-left transition hover:-translate-y-0.5"
                                    style="border-color: #D9D9D9; background: var(--app-surface-soft);"
                                >
                                    <div class="text-sm font-semibold uppercase tracking-[0.2em]" style="color: #2F2E7C;">Featured route</div>
                                    <div class="mt-2 text-xl font-black app-title">{{ routePreset.origin_name }} -> {{ routePreset.destination_name }}</div>
                                    <div class="mt-3 flex flex-wrap gap-2 text-sm app-muted">
                                        <span class="rounded-full px-3 py-1" style="background: var(--app-surface);">{{ routePreset.distance_km }} km</span>
                                        <span class="rounded-full px-3 py-1" style="background: var(--app-surface);">{{ routePreset.estimated_hours }} hrs</span>
                                        <span class="rounded-full px-3 py-1" style="background: var(--app-surface);">From N$ {{ Number(routePreset.base_fare).toFixed(2) }}</span>
                                    </div>
                                </button>
                            </div>
                        </div>

                        <form @submit.prevent="submit" class="app-panel p-6 md:p-8">
                            <div class="flex flex-col gap-2 border-b pb-6 md:flex-row md:items-end md:justify-between" style="border-color: var(--app-border);">
                                <div>
                                    <h2 class="text-3xl font-black app-title">Shipment Details</h2>
                                    <p class="mt-1 app-muted">Complete the trip, parcel, receiver, urgency, and budget details.</p>
                                </div>
                                <div class="rounded-full px-4 py-2 text-xs font-semibold uppercase tracking-[0.25em]" style="background: var(--app-surface-soft); color: var(--app-text);">
                                    4-step intake
                                </div>
                            </div>

                            <div class="mt-6 grid gap-6 md:grid-cols-2">
                                <div>
                                    <label class="mb-2 block text-sm font-bold uppercase tracking-[0.16em] app-muted">Pickup town</label>
                                    <select v-model="form.pickup_location_id" class="w-full rounded-2xl border" style="border-color: var(--app-border);">
                                        <option value="">Select pickup town</option>
                                        <option v-for="location in locations" :key="location.id" :value="location.id">{{ location.name }}</option>
                                    </select>
                                    <div class="mt-2 text-xs leading-5 app-muted">Choose the operational origin city for this lane.</div>
                                    <div class="mt-1 text-sm text-red-600">{{ form.errors.pickup_location_id }}</div>
                                </div>

                                <div>
                                    <label class="mb-2 block text-sm font-bold uppercase tracking-[0.16em] app-muted">Destination town</label>
                                    <select v-model="form.dropoff_location_id" class="w-full rounded-2xl border" style="border-color: var(--app-border);">
                                        <option value="">Select destination town</option>
                                        <option v-for="location in locations" :key="location.id" :value="location.id">{{ location.name }}</option>
                                    </select>
                                    <div class="mt-2 text-xs leading-5 app-muted">Destination pricing is based on the stored route distance for this pair.</div>
                                    <div class="mt-1 text-sm text-red-600">{{ form.errors.dropoff_location_id }}</div>
                                </div>

                                <div>
                                    <label class="mb-2 block text-sm font-bold uppercase tracking-[0.16em] app-muted">Parcel type</label>
                                    <select v-model="form.package_type_id" class="w-full rounded-2xl border" style="border-color: var(--app-border);">
                                        <option value="">Select parcel type</option>
                                        <option v-for="pkg in packageTypes" :key="pkg.id" :value="pkg.id">{{ pkg.name }}</option>
                                    </select>
                                    <div class="mt-2 text-xs leading-5 app-muted">Parcel category helps tune handling and pricing rules accurately.</div>
                                    <div class="mt-1 text-sm text-red-600">{{ form.errors.package_type_id }}</div>
                                </div>

                                <div>
                                    <label class="mb-2 block text-sm font-bold uppercase tracking-[0.16em] app-muted">Load size</label>
                                    <select v-model="form.load_size" class="w-full rounded-2xl border" style="border-color: var(--app-border);">
                                        <option v-for="(label, key) in loadSizeLabels" :key="key" :value="key">{{ label }}</option>
                                    </select>
                                </div>

                                <div>
                                    <label class="mb-2 block text-sm font-bold uppercase tracking-[0.16em] app-muted">Pickup address</label>
                                    <input v-model="form.pickup_address" class="w-full rounded-2xl border" style="border-color: var(--app-border);" placeholder="Street, area, landmark" />
                                </div>

                                <div>
                                    <label class="mb-2 block text-sm font-bold uppercase tracking-[0.16em] app-muted">Dropoff address</label>
                                    <input v-model="form.dropoff_address" class="w-full rounded-2xl border" style="border-color: var(--app-border);" placeholder="Street, area, landmark" />
                                </div>

                                <div>
                                    <label class="mb-2 block text-sm font-bold uppercase tracking-[0.16em] app-muted">Receiver name</label>
                                    <input v-model="form.receiver_name" class="w-full rounded-2xl border" style="border-color: var(--app-border);" />
                                    <div class="mt-1 text-sm text-red-600">{{ form.errors.receiver_name }}</div>
                                </div>

                                <div>
                                    <label class="mb-2 block text-sm font-bold uppercase tracking-[0.16em] app-muted">Receiver phone</label>
                                    <input v-model="form.receiver_phone" class="w-full rounded-2xl border" style="border-color: var(--app-border);" />
                                    <div class="mt-2 text-xs leading-5 app-muted">Include the best direct number for handoff and delivery confirmation.</div>
                                    <div class="mt-1 text-sm text-red-600">{{ form.errors.receiver_phone }}</div>
                                </div>

                                <div>
                                    <label class="mb-2 block text-sm font-bold uppercase tracking-[0.16em] app-muted">Weight (kg)</label>
                                    <input v-model="form.weight_kg" type="number" step="0.1" min="0" class="w-full rounded-2xl border" style="border-color: var(--app-border);" />
                                    <div class="mt-2 text-xs leading-5 app-muted">Weight directly affects the transport tier and quote accuracy.</div>
                                </div>

                                <div>
                                    <label class="mb-2 block text-sm font-bold uppercase tracking-[0.16em] app-muted">Declared value</label>
                                    <input v-model="form.declared_value" type="number" step="0.01" min="0" class="w-full rounded-2xl border" style="border-color: var(--app-border);" />
                                    <div class="mt-2 text-xs leading-5 app-muted">Optional, but useful for higher-value parcels and support handling.</div>
                                </div>
                            </div>

                            <div class="mt-6">
                                <label class="mb-3 block text-sm font-bold uppercase tracking-[0.16em] app-muted">Urgency</label>
                                <div class="grid gap-3 md:grid-cols-3">
                                    <button
                                        v-for="(label, key) in urgencyLabels"
                                        :key="key"
                                        type="button"
                                        @click="form.urgency_level = key"
                                        class="rounded-[24px] border p-4 text-left transition"
                                        :style="form.urgency_level === key ? 'border-color:#2F2E7C;background:rgba(47,46,124,0.08);' : 'border-color:#D9D9D9;background:var(--app-surface-soft);'"
                                    >
                                        <div class="text-base font-black app-title">{{ label }}</div>
                                        <div class="mt-1 text-sm app-muted">
                                            {{
                                                key === 'same_day'
                                                    ? 'Fastest dispatch and highest priority matching.'
                                                    : key === 'express'
                                                      ? 'Quicker handoff and accelerated assignment.'
                                                      : 'Balanced pricing and standard dispatch speed.'
                                            }}
                                        </div>
                                    </button>
                                </div>
                                <div class="mt-1 text-sm text-red-600">{{ form.errors.urgency_level }}</div>
                            </div>

                            <div class="mt-6 grid gap-6 md:grid-cols-2">
                                <div>
                                    <label class="mb-2 block text-sm font-bold uppercase tracking-[0.16em] app-muted">Your offer (optional)</label>
                                    <input v-model="form.client_offer_price" type="number" step="0.01" min="1" class="w-full rounded-2xl border" style="border-color: var(--app-border);" placeholder="e.g. 950.00" />
                                    <p class="mt-2 text-sm app-muted">If you add an offer, matching drivers will see both the route estimate and your proposed amount.</p>
                                    <div class="mt-1 text-sm text-red-600">{{ form.errors.client_offer_price }}</div>
                                </div>

                                <div class="rounded-[24px] border p-5" style="border-color: var(--app-border); background: var(--app-surface-soft);">
                                    <div class="text-xs font-bold uppercase tracking-[0.18em] app-muted">Pricing note</div>
                                    <div class="mt-2 text-lg font-black app-title">
                                        {{ pricingPreview.clientOffer === null ? 'No client offer yet' : `Offer N$ ${pricingPreview.clientOffer.toFixed(2)}` }}
                                    </div>
                                    <p class="mt-2 text-sm app-muted">
                                        {{ pricingPreview.clientOffer === null
                                            ? 'Leave it blank if you want drivers to work from the platform estimate only.'
                                            : pricingPreview.offerGap >= 0
                                              ? `Your offer is N$ ${pricingPreview.offerGap.toFixed(2)} below the current estimate.`
                                              : `Your offer is N$ ${Math.abs(pricingPreview.offerGap).toFixed(2)} above the current estimate.` }}
                                    </p>
                                </div>
                            </div>

                            <div class="mt-6">
                                <label class="mb-2 block text-sm font-bold uppercase tracking-[0.16em] app-muted">Notes</label>
                                <textarea v-model="form.notes" rows="4" class="w-full rounded-[24px] border" style="border-color: var(--app-border);" placeholder="Fragile cargo, forklift needed, mining site access, gate times, special handling..."></textarea>
                            </div>

                            <div class="mt-8 flex flex-col gap-4 border-t pt-6 md:flex-row md:items-center md:justify-between" style="border-color: var(--app-border);">
                                <p class="max-w-2xl text-sm leading-6 app-muted">
                                    Your estimate is calculated before dispatch. Once submitted, the platform creates a tracking number, scores the best matching drivers on the selected route, and includes your offer when one is added.
                                </p>
                                <button type="submit" :disabled="form.processing" class="app-primary-btn disabled:opacity-60">
                                    {{ form.processing ? "Submitting..." : "Request Smart Match" }}
                                </button>
                            </div>
                        </form>
                    </div>

                    <aside class="space-y-6">
                        <div class="app-panel p-6">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <div class="text-sm font-bold uppercase tracking-[0.25em]" style="color: #2F2E7C;">Live Quote</div>
                                    <div class="mt-2">
                                        <StatusBadge :label="quoteReadinessLabel" :tone="canPreview ? 'brand' : 'neutral'" small />
                                    </div>
                                </div>
                                <button type="button" class="app-outline-btn !px-4 !py-3 !text-xs" :disabled="quoteBusy || !canPreview" @click="generateQuotation">
                                    {{ quoteBusy ? "Working..." : "Generate Quote" }}
                                </button>
                            </div>
                            <h2 class="mt-3 text-3xl font-black app-title">N$ {{ pricingPreview.totalPrice.toFixed(2) }}</h2>
                            <p class="mt-2 text-sm leading-6 app-muted">
                                Based on {{ selectedPickup?.name || "pickup" }} -> {{ selectedDropoff?.name || "destination" }}, {{ urgencyLabels[form.urgency_level] }}, and {{ form.weight_kg || 0 }} kg.
                            </p>

                            <div class="mt-6 grid gap-3">
                                <div v-for="row in pricingBreakdownRows" :key="row.label" class="rounded-2xl px-4 py-3" style="background: var(--app-surface-soft);">
                                    <div class="flex items-center justify-between gap-3">
                                        <span class="text-sm font-semibold app-muted">{{ row.label }}</span>
                                        <span class="font-black app-title">{{ row.value }}</span>
                                    </div>
                                    <div class="mt-1 text-xs leading-5 app-muted">{{ row.detail }}</div>
                                </div>
                                <div class="flex items-center justify-between rounded-2xl px-4 py-3 border" style="background: var(--app-surface); border-color: var(--app-border);">
                                    <span class="text-sm font-semibold app-muted">Your offer</span>
                                    <span class="font-black app-title">{{ pricingPreview.clientOffer === null ? 'Optional' : `N$ ${pricingPreview.clientOffer.toFixed(2)}` }}</span>
                                </div>
                            </div>

                            <div v-if="generatedQuotation" class="mt-6 rounded-[24px] border p-5" style="border-color: rgba(47,46,124,0.12); background: rgba(47,46,124,0.05);">
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                    <div>
                                        <div class="text-[11px] font-bold uppercase tracking-[0.18em] app-muted">Generated quotation</div>
                                        <div class="mt-2 text-xl font-black app-title">{{ generatedQuotation.quotation_number }}</div>
                                        <div class="mt-1 text-sm app-muted">Expires {{ generatedQuotation.expires_at }}</div>
                                        <div v-if="generatedQuotation.driver_snapshot?.name" class="mt-1 text-sm app-muted">Driver {{ generatedQuotation.driver_snapshot.name }} included on quote</div>
                                    </div>
                                    <StatusBadge :label="String(generatedQuotation.status || 'issued').replaceAll('_', ' ')" tone="brand" />
                                </div>
                                <div class="mt-4 rounded-[20px] border px-4 py-4" style="border-color: rgba(47,46,124,0.12); background: rgba(255,255,255,0.72);">
                                    <div class="text-sm font-black app-title">Ready to share or save</div>
                                    <p class="mt-1 text-sm leading-6 app-muted">This quotation uses the same pricing breakdown that carries into booking review and invoicing.</p>
                                    <a :href="route('quotations.download', generatedQuotation.id)" class="mt-4 inline-flex items-center gap-2 text-sm font-bold uppercase tracking-[0.14em]" style="color:#2F2E7C;">
                                        Download Quote PDF
                                    </a>
                                </div>
                            </div>

                            <div v-if="suggestedDrivers.length" class="mt-6 rounded-[24px] border p-5" style="border-color: rgba(47,46,124,0.12); background: rgba(255,255,255,0.72);">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <div class="text-[11px] font-bold uppercase tracking-[0.18em] app-muted">Suggested drivers</div>
                                        <div class="mt-2 text-xl font-black app-title">Select a preferred driver</div>
                                        <p class="mt-1 text-sm leading-6 app-muted">Choose from ranked route matches to carry that driver into confirmation, quotation, and invoice details.</p>
                                    </div>
                                    <StatusBadge :label="selectedSuggestedDriver ? 'Driver selected' : 'Optional'" :tone="selectedSuggestedDriver ? 'success' : 'neutral'" small />
                                </div>

                                <div class="mt-4 grid gap-3">
                                    <button
                                        v-for="driver in suggestedDrivers"
                                        :key="driver.id"
                                        type="button"
                                        class="rounded-[20px] border px-4 py-4 text-left transition"
                                        :style="Number(form.selected_driver_id) === Number(driver.id) ? 'border-color:#2F2E7C;background:rgba(47,46,124,0.08);' : 'border-color:var(--app-border);background:var(--app-surface-soft);'"
                                        @click="form.selected_driver_id = Number(driver.id) === Number(form.selected_driver_id) ? '' : driver.id"
                                    >
                                        <div class="flex items-start justify-between gap-3">
                                            <div>
                                                <div class="text-base font-black app-title">{{ driver.name }}</div>
                                                <div class="mt-1 text-sm app-muted">{{ driver.route_summary || `${driver.pickup_location} -> ${driver.dropoff_location}` }}</div>
                                                <div class="mt-1 text-sm app-muted">{{ driver.vehicle || driver.vehicle_type || 'Delivery vehicle' }}</div>
                                            </div>
                                            <div class="text-right">
                                                <div class="text-sm font-black app-title">{{ Number(driver.match_score || 0) }}%</div>
                                                <div class="mt-1 text-xs app-muted">{{ driver.available_now ? 'Available now' : 'Busy' }}</div>
                                            </div>
                                        </div>
                                    </button>
                                </div>
                            </div>

                            <div class="mt-6 rounded-[24px] p-5 text-white" style="background: #2F2E7C;">
                                <div class="text-xs font-bold uppercase tracking-[0.2em]" style="color: #F2C900;">Route intelligence</div>
                                <div class="mt-3 grid gap-3 sm:grid-cols-2 xl:grid-cols-1">
                                    <div class="rounded-2xl p-4" style="background: rgba(255,255,255,0.08);">
                                        <div class="text-xs uppercase tracking-wide" style="color: rgba(255,255,255,0.78);">Distance</div>
                                        <div class="mt-1 text-2xl font-black">{{ pricingPreview.distance }} km</div>
                                    </div>
                                    <div class="rounded-2xl p-4" style="background: rgba(255,255,255,0.08);">
                                        <div class="text-xs uppercase tracking-wide" style="color: rgba(255,255,255,0.78);">Estimated travel</div>
                                        <div class="mt-1 text-2xl font-black">{{ pricingPreview.estimatedHours.toFixed(1) }} hrs</div>
                                    </div>
                                    <div class="rounded-2xl p-4" style="background: rgba(255,255,255,0.08);">
                                        <div class="text-xs uppercase tracking-wide" style="color: rgba(255,255,255,0.78);">Distance source</div>
                                        <div class="mt-1 text-lg font-black">{{ distanceSourceLabel }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="app-panel p-6">
                            <div class="text-sm font-bold uppercase tracking-[0.25em]" style="color: #2F2E7C;">Dispatch Sequence</div>
                            <ol class="mt-5 space-y-4">
                                <li class="rounded-2xl p-4" style="background: var(--app-surface-soft);">
                                    <div class="text-xs font-bold uppercase tracking-[0.18em] app-muted">Step 1</div>
                                    <div class="mt-1 text-lg font-black app-title">Tracking number issued</div>
                                    <p class="mt-1 text-sm app-muted">Every request starts with a traceable parcel ID.</p>
                                </li>
                                <li class="rounded-2xl p-4" style="background: var(--app-surface-soft);">
                                    <div class="text-xs font-bold uppercase tracking-[0.18em] app-muted">Step 2</div>
                                    <div class="mt-1 text-lg font-black app-title">Route match scored</div>
                                    <p class="mt-1 text-sm app-muted">Drivers are ranked against the selected city route and cargo type.</p>
                                </li>
                                <li class="rounded-2xl p-4" style="background: var(--app-surface-soft);">
                                    <div class="text-xs font-bold uppercase tracking-[0.18em] app-muted">Step 3</div>
                                    <div class="mt-1 text-lg font-black app-title">Offer shared instantly</div>
                                    <p class="mt-1 text-sm app-muted">When you set a price, matched drivers can review it before accepting.</p>
                                </li>
                            </ol>
                        </div>
                    </aside>
                </section>
            </div>
        </main>
    </PublicLayout>
</template>
