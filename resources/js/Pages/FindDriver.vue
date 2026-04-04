
<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from "vue";
import { Head, Link } from "@inertiajs/vue3";
import {
    ArrowRight,
    ChevronDown,
    CircleDollarSign,
    Clock3,
    MapPinned,
    ShieldCheck,
    Truck,
} from "lucide-vue-next";
import PublicLayout from "@/Layouts/PublicLayout.vue";
import EmptyState from "@/Components/AppShell/EmptyState.vue";
import StatusBadge from "@/Components/AppShell/StatusBadge.vue";
import BookingStepper from "@/Components/Booking/BookingStepper.vue";
import StickyBookingSummary from "@/Components/Booking/StickyBookingSummary.vue";
import api from "@/lib/api";
import { errorToast, infoToast, successToast } from "@/composables/useAppToast";

const props = defineProps({
    canLogin: Boolean,
    canRegister: Boolean,
    drivers: { type: Array, default: () => [] },
    locations: { type: Array, default: () => [] },
    packageTypes: { type: Array, default: () => [] },
    cityRoutes: { type: Array, default: () => [] },
});

const pickupLocation = ref("");
const deliveryLocation = ref("");
const selectedPackage = ref("");
const urgencyLevel = ref("standard");
const loadSize = ref("medium");
const weightKg = ref("");
const onlyAvailable = ref(true);
const sortBy = ref("best_match");
const selectedDriverId = ref(null);
const quote = ref(null);
const previewDrivers = ref([]);
const marketBusy = ref(false);
const marketLoaded = ref(false);
const marketError = ref("");
const expandedDriverId = ref(null);

const bookingSteps = [
    { id: 1, label: "Find Driver", icon: Truck },
    { id: 2, label: "Confirm", icon: ShieldCheck },
    { id: 3, label: "Payment", icon: CircleDollarSign },
    { id: 4, label: "Track", icon: MapPinned },
];

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

const sortOptions = [
    { value: "best_match", label: "Best Match" },
    { value: "fastest", label: "Fastest ETA" },
    { value: "cheapest", label: "Lowest Estimate" },
];

const loadSizeWeights = {
    small: 1,
    medium: 2,
    large: 3,
    heavy: 4,
    oversized: 5,
};

let previewTimer = null;
let previewToken = 0;

function numberOrZero(value) {
    return Number(value || 0);
}

function createInitials(name) {
    return String(name || "Driver")
        .split(" ")
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase() || "")
        .join("") || "D";
}

function vehicleTypeLabel(type) {
    return {
        car: "Car",
        van: "Van",
        bakkie: "Bakkie",
        truck: "Truck",
        refrigerated_truck: "Refrigerated Truck",
    }[type] || "Delivery Vehicle";
}

function formatCurrency(value) {
    return `N$ ${Number(value || 0).toFixed(0)}`;
}

function estimatePriceRange(total, vehicleType, matchScore) {
    const base = Math.max(numberOrZero(total), 450);
    const vehicleLift = { car: 0, bakkie: 0.03, van: 0.05, truck: 0.08, refrigerated_truck: 0.12 };
    const modifier = 1 + (vehicleLift[vehicleType] || 0) + ((100 - Math.min(Math.max(matchScore || 70, 45), 98)) / 500);
    const low = Math.round(base * modifier);
    const high = Math.round(low * 1.12);

    return { low, high, label: `${formatCurrency(low)} - ${formatCurrency(high)}` };
}

function estimateEta(hours, matchScore, availableNow) {
    const base = Math.max(numberOrZero(hours), 2.2);
    const urgencyFactor = matchScore >= 85 ? 0.9 : matchScore >= 70 ? 0.97 : 1.06;
    const availabilityFactor = availableNow ? 0.96 : 1.08;
    const result = Math.max(1.8, base * urgencyFactor * availabilityFactor);

    return { hours: Number(result.toFixed(1)), label: `${Number(result.toFixed(1))} hrs` };
}

function createRouteSummary(routeLocations = [], fallback = "") {
    const names = routeLocations.map((location) => location.name).filter(Boolean);
    const fallbackNames = String(fallback || "").split("->").map((part) => part.trim()).filter(Boolean);
    const stops = names.length ? names : fallbackNames;

    return {
        routeSummary: stops.join(" -> ") || fallback || "Namibia route network",
        routeStart: stops[0] || "Pickup",
        routeEnd: stops[stops.length - 1] || "Destination",
        routeMidStops: stops.slice(1, -1),
    };
}

const requestReady = computed(() => !!pickupLocation.value && !!deliveryLocation.value && !!selectedPackage.value && Number(pickupLocation.value) !== Number(deliveryLocation.value));
const selectedPickupLabel = computed(() => props.locations.find((location) => Number(location.id) === Number(pickupLocation.value))?.name || "Select pickup");
const selectedDeliveryLabel = computed(() => props.locations.find((location) => Number(location.id) === Number(deliveryLocation.value))?.name || "Select destination");
const selectedPackageLabel = computed(() => props.packageTypes.find((pkg) => Number(pkg.id) === Number(selectedPackage.value))?.name || "Select parcel type");
const selectedRoute = computed(() => props.cityRoutes.find((routeItem) => Number(routeItem.origin_location_id) === Number(pickupLocation.value) && Number(routeItem.destination_location_id) === Number(deliveryLocation.value)) || null);

function normalizePreviewDriver(driver) {
    const routeLocations = (driver.route_locations || []).map((location) => ({ id: location.id, name: location.name }));
    const routePresentation = createRouteSummary(routeLocations, driver.route_summary);
    const matchScore = numberOrZero(driver.match_score);
    const total = numberOrZero(quote.value?.total_price || selectedRoute.value?.base_fare);
    const availableNow = Boolean(driver.available_now);

    return {
        id: driver.id,
        name: driver.name || "Route Driver",
        initials: createInitials(driver.name),
        image: driver.image || "",
        vehicle: driver.vehicle || vehicleTypeLabel(driver.vehicle_type),
        vehicleType: driver.vehicle_type,
        vehicleMeta: `${vehicleTypeLabel(driver.vehicle_type)}${driver.max_load_size ? ` | ${loadSizeLabels[driver.max_load_size] || driver.max_load_size}` : ""}`,
        availableNow,
        availabilityLabel: availableNow ? "Available" : "Busy",
        matchScore,
        matchLabel: driver.match_label || (matchScore >= 85 ? "Best Match" : matchScore >= 70 ? "Good Match" : "Route Match"),
        routeSummary: routePresentation.routeSummary,
        routeStart: routePresentation.routeStart,
        routeEnd: routePresentation.routeEnd,
        routeMidStops: routePresentation.routeMidStops,
        routeLocations,
        parcelSpecialties: (driver.parcel_specialties || []).slice(0, 4),
        parcelTypes: (driver.parcel_specialties || []).map((item) => item.name).join(", ") || "General parcel handling",
        reasons: (driver.reasons || []).slice(0, 4),
        badges: (driver.badges || []).slice(0, 4),
        eta: estimateEta(quote.value?.estimated_hours, matchScore, availableNow),
        priceRange: estimatePriceRange(total, driver.vehicle_type, matchScore),
        href: route("driver.detail", driver.id),
    };
}
function localDriverScore(route, driver) {
    const locationIds = (route.locations || []).map((location) => Number(location.id));
    const supportsRoute = locationIds.includes(Number(pickupLocation.value)) && locationIds.includes(Number(deliveryLocation.value));
    if (!supportsRoute) return null;

    const packageIds = (route.packages || []).map((item) => Number(item.id));
    if (selectedPackage.value && packageIds.length && !packageIds.includes(Number(selectedPackage.value))) return null;
    if ((loadSizeWeights[route.max_load_size] || 1) < (loadSizeWeights[loadSize.value] || 1)) return null;

    let score = 60;
    if (driver.status === "active" && route.available) score += 18;
    if (packageIds.includes(Number(selectedPackage.value))) score += 12;
    if (urgencyLevel.value !== "standard" && route.available) score += 5;

    return Math.min(score, 97);
}

function normalizeLocalDriver(driver) {
    const rankedRoute = (driver.driverRoutes || [])
        .map((route) => ({ route, score: localDriverScore(route, driver) }))
        .filter((item) => item.score !== null)
        .sort((left, right) => right.score - left.score)[0];

    if (!rankedRoute) return null;

    const route = rankedRoute.route;
    const routeLocations = (route.locations || []).map((location) => ({ id: location.id, name: location.name }));
    const routePresentation = createRouteSummary(routeLocations);
    const availableNow = Boolean(route.available) && driver.status === "active";
    const total = numberOrZero(quote.value?.total_price || selectedRoute.value?.base_fare);

    return {
        id: driver.id,
        name: driver.user?.name || "Route Driver",
        initials: createInitials(driver.user?.name),
        image: driver.user?.profile_photo_url || "",
        vehicle: [route.car_make, route.car_model].filter(Boolean).join(" ") || vehicleTypeLabel(route.vehicle_type),
        vehicleType: route.vehicle_type,
        vehicleMeta: `${vehicleTypeLabel(route.vehicle_type)}${route.max_load_size ? ` | ${loadSizeLabels[route.max_load_size] || route.max_load_size}` : ""}`,
        availableNow,
        availabilityLabel: availableNow ? "Available" : "Busy",
        matchScore: rankedRoute.score,
        matchLabel: rankedRoute.score >= 85 ? "Best Match" : rankedRoute.score >= 70 ? "Good Match" : "Route Match",
        routeSummary: routePresentation.routeSummary,
        routeStart: routePresentation.routeStart,
        routeEnd: routePresentation.routeEnd,
        routeMidStops: routePresentation.routeMidStops,
        routeLocations,
        parcelSpecialties: (route.packages || []).slice(0, 4),
        parcelTypes: (route.packages || []).map((item) => item.name).join(", ") || "General parcel handling",
        reasons: ["Route aligned", availableNow ? "Available now" : "Currently busy"].filter(Boolean),
        badges: ["Route Match", "Vehicle Fit", ...(availableNow ? ["Available"] : [])].slice(0, 4),
        eta: estimateEta(selectedRoute.value?.estimated_hours, rankedRoute.score, availableNow),
        priceRange: estimatePriceRange(total, route.vehicle_type, rankedRoute.score),
        href: route("driver.detail", driver.id),
    };
}

const sourceDrivers = computed(() => marketLoaded.value ? previewDrivers.value.map(normalizePreviewDriver).filter(Boolean) : props.drivers.map(normalizeLocalDriver).filter(Boolean));

const prioritizedDrivers = computed(() => sourceDrivers.value
    .filter((driver) => !onlyAvailable.value || driver.availableNow)
    .sort((left, right) => {
        if (sortBy.value === "fastest") return left.eta.hours - right.eta.hours;
        if (sortBy.value === "cheapest") return left.priceRange.low - right.priceRange.low;
        return right.matchScore - left.matchScore;
    })
    .map((driver, index) => ({
        ...driver,
        rank: index + 1,
        isSelected: Number(selectedDriverId.value) === Number(driver.id),
        isExpanded: Number(expandedDriverId.value) === Number(driver.id),
        isTopThree: index < 3,
        isBest: index === 0,
    }))
);

const selectedDriver = computed(() => prioritizedDrivers.value.find((driver) => driver.isSelected) || null);
const bestDriver = computed(() => prioritizedDrivers.value[0] || null);
const activeSummaryDriver = computed(() => selectedDriver.value || bestDriver.value || null);

const confirmationHref = computed(() => {
    if (!selectedDriver.value || !requestReady.value) return null;

    const params = new URLSearchParams({
        pickup: String(pickupLocation.value),
        destination: String(deliveryLocation.value),
        parcel: String(selectedPackage.value),
        driver: String(selectedDriver.value.id),
        urgency: urgencyLevel.value,
        load: loadSize.value,
    });

    if (weightKg.value) params.set("weight", String(weightKg.value));
    return `${route("driver-selection.confirm")}?${params.toString()}`;
});

const summaryItems = computed(() => {
    const driver = activeSummaryDriver.value;

    return [
        {
            label: "Route",
            value: `${selectedPickupLabel.value} -> ${selectedDeliveryLabel.value}`,
            meta: selectedRoute.value ? `${selectedRoute.value.distance_km} km | ${selectedRoute.value.estimated_hours} hrs` : "Route preview becomes precise once matched",
        },
        {
            label: "Parcel",
            value: selectedPackageLabel.value,
            meta: `${loadSizeLabels[loadSize.value] || loadSize.value} load | ${urgencyLabels[urgencyLevel.value] || "Standard"}`,
        },
        {
            label: driver ? "Selected driver" : "Best match",
            value: driver?.name || "Select a driver",
            meta: driver ? `${driver.matchScore}% match | ${driver.eta.label}` : "Driver choice appears here",
        },
        {
            label: "Estimated total",
            value: driver?.priceRange.label || "Select route and parcel",
            meta: quote.value?.distance_source ? `Distance source: ${String(quote.value.distance_source).replaceAll("_", " ")}` : "Live pricing preview",
        },
    ];
});

const summaryBadges = computed(() => {
    const driver = activeSummaryDriver.value;
    return [selectedDriver.value ? "Driver Selected" : "Matches", "Tracking Included", "Transparent Pricing", ...(driver?.badges || []).slice(0, 2)].slice(0, 5);
});

function selectDriver(driver) {
    selectedDriverId.value = driver.id;
    successToast(`${driver.name} is selected for this booking.`, "Driver selected");
}

function toggleDriverExpansion(driver) {
    expandedDriverId.value = Number(expandedDriverId.value) === Number(driver.id) ? null : driver.id;
}

function clearFilters() {
    pickupLocation.value = "";
    deliveryLocation.value = "";
    selectedPackage.value = "";
    urgencyLevel.value = "standard";
    loadSize.value = "medium";
    weightKg.value = "";
    onlyAvailable.value = true;
    sortBy.value = "best_match";
    selectedDriverId.value = null;
    previewDrivers.value = [];
    quote.value = null;
    marketLoaded.value = false;
    marketError.value = "";
}

async function fetchMarket() {
    if (!requestReady.value) {
        previewDrivers.value = [];
        quote.value = null;
        marketLoaded.value = false;
        marketError.value = "";
        return;
    }

    const requestId = ++previewToken;
    marketBusy.value = true;
    marketError.value = "";

    try {
        const { data } = await api.get(route("parcel-requests.preview"), {
            params: {
                pickup_location_id: pickupLocation.value,
                dropoff_location_id: deliveryLocation.value,
                package_type_id: selectedPackage.value,
                weight_kg: weightKg.value || null,
                load_size: loadSize.value,
                urgency_level: urgencyLevel.value,
                limit: 12,
            },
        });

        if (requestId !== previewToken) return;
        previewDrivers.value = Array.isArray(data.drivers) ? data.drivers : [];
        quote.value = data.quote || null;
        marketLoaded.value = true;
    } catch (error) {
        if (requestId !== previewToken) return;
        marketError.value = error.response?.data?.message || "Could not load live driver matches right now.";
        errorToast(marketError.value, "Driver matching failed");
    } finally {
        if (requestId === previewToken) marketBusy.value = false;
    }
}

watch(() => [pickupLocation.value, deliveryLocation.value, selectedPackage.value, urgencyLevel.value, loadSize.value, weightKg.value], () => {
    window.clearTimeout(previewTimer);
    previewTimer = window.setTimeout(fetchMarket, 250);
});

watch(() => prioritizedDrivers.value.map((driver) => driver.id).join(","), () => {
    if (!prioritizedDrivers.value.some((driver) => Number(driver.id) === Number(selectedDriverId.value))) {
        selectedDriverId.value = null;
    }
}, { immediate: true });

onMounted(() => {
    const params = new URLSearchParams(window.location.search);
    pickupLocation.value = params.get("pickup") || "";
    deliveryLocation.value = params.get("destination") || "";
    selectedPackage.value = params.get("parcel") || "";
    urgencyLevel.value = params.get("urgency") || "standard";
    loadSize.value = params.get("load") || "medium";
    weightKg.value = params.get("weight") || "";
    selectedDriverId.value = params.get("driver") || params.get("select") || null;
    fetchMarket();
});

onBeforeUnmount(() => {
    window.clearTimeout(previewTimer);
});
</script>
<template>
    <Head title="Find Driver" />

    <PublicLayout>
        <div class="mx-auto w-full max-w-[1800px] px-4 py-6 sm:px-6 lg:px-10 2xl:px-12">
            <BookingStepper :steps="bookingSteps" :current="1" compact class="mb-6" />

            <section class="app-panel rounded-[30px] p-5 sm:p-6 lg:p-7">
                <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                    <div class="max-w-4xl">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="rounded-full px-3 py-1 text-[11px] font-bold uppercase tracking-[0.18em]" style="background: rgba(47,46,124,0.08); color:#2F2E7C;">Driver marketplace</span>
                            <span class="rounded-full px-3 py-1 text-[11px] font-bold uppercase tracking-[0.18em]" style="background: rgba(242,201,0,0.20); color:#1F1F1F;">Wizard step: matches</span>
                        </div>
                        <h1 class="mt-5 text-3xl font-black app-title sm:text-4xl">Pick a driver directly from your matched list</h1>
                        <p class="mt-3 max-w-3xl text-sm leading-7 app-muted">Set your route and parcel details, compare live matches, and tap any driver card to select them for confirmation.</p>
                    </div>

                    <div class="flex flex-col gap-3 sm:flex-row">
                        <Link :href="route('parcel-requests.create')" class="app-outline-btn">Booking Wizard</Link>
                        <Link v-if="confirmationHref" :href="confirmationHref" class="app-primary-btn">
                            Continue with {{ selectedDriver?.name || "driver" }}
                            <ArrowRight class="h-4 w-4" />
                        </Link>
                        <button v-else type="button" class="app-primary-btn opacity-60 cursor-not-allowed" disabled>Select a driver first</button>
                    </div>
                </div>
            </section>

            <section class="mt-6 grid gap-6 xl:grid-cols-[320px_minmax(0,1fr)_360px]">
                <aside class="space-y-5">
                    <div class="app-panel rounded-[28px] p-5">
                        <div class="text-[11px] font-bold uppercase tracking-[0.18em]" style="color:#2F2E7C;">Route filters</div>
                        <div class="mt-4 space-y-4">
                            <label class="block">
                                <span class="mb-2 block text-[11px] font-bold uppercase tracking-[0.16em] app-muted">Pickup</span>
                                <select v-model="pickupLocation" class="app-field">
                                    <option value="">Select pickup town</option>
                                    <option v-for="location in locations" :key="location.id" :value="location.id">{{ location.name }}</option>
                                </select>
                            </label>
                            <label class="block">
                                <span class="mb-2 block text-[11px] font-bold uppercase tracking-[0.16em] app-muted">Destination</span>
                                <select v-model="deliveryLocation" class="app-field">
                                    <option value="">Select destination town</option>
                                    <option v-for="location in locations" :key="location.id" :value="location.id">{{ location.name }}</option>
                                </select>
                            </label>
                            <label class="block">
                                <span class="mb-2 block text-[11px] font-bold uppercase tracking-[0.16em] app-muted">Parcel type</span>
                                <select v-model="selectedPackage" class="app-field">
                                    <option value="">Select parcel type</option>
                                    <option v-for="pkg in packageTypes" :key="pkg.id" :value="pkg.id">{{ pkg.name }}</option>
                                </select>
                            </label>
                            <label class="block">
                                <span class="mb-2 block text-[11px] font-bold uppercase tracking-[0.16em] app-muted">Weight (kg)</span>
                                <input v-model="weightKg" type="number" min="0" step="0.1" class="app-field" placeholder="Optional" />
                            </label>
                            <label class="block">
                                <span class="mb-2 block text-[11px] font-bold uppercase tracking-[0.16em] app-muted">Urgency</span>
                                <select v-model="urgencyLevel" class="app-field">
                                    <option v-for="(label, key) in urgencyLabels" :key="key" :value="key">{{ label }}</option>
                                </select>
                            </label>
                            <label class="block">
                                <span class="mb-2 block text-[11px] font-bold uppercase tracking-[0.16em] app-muted">Load size</span>
                                <select v-model="loadSize" class="app-field">
                                    <option v-for="(label, key) in loadSizeLabels" :key="key" :value="key">{{ label }}</option>
                                </select>
                            </label>
                        </div>
                    </div>

                    <div class="app-panel rounded-[28px] p-5">
                        <div class="text-[11px] font-bold uppercase tracking-[0.18em]" style="color:#2F2E7C;">Match controls</div>
                        <div class="mt-4 space-y-4">
                            <label class="flex items-center justify-between gap-3 rounded-[20px] border px-4 py-3" style="border-color: var(--app-border); background: var(--app-surface-soft);">
                                <span class="text-sm font-semibold app-title">Available only</span>
                                <input v-model="onlyAvailable" type="checkbox" class="h-4 w-4" />
                            </label>
                            <label class="block">
                                <span class="mb-2 block text-[11px] font-bold uppercase tracking-[0.16em] app-muted">Sort by</span>
                                <select v-model="sortBy" class="app-field">
                                    <option v-for="option in sortOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                                </select>
                            </label>
                            <button type="button" class="app-outline-btn w-full" @click="clearFilters">Reset Filters</button>
                        </div>
                    </div>
                </aside>

                <section class="space-y-4">
                    <div class="app-panel rounded-[28px] p-5">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <div class="text-[11px] font-bold uppercase tracking-[0.18em]" style="color:#2F2E7C;">Top drivers</div>
                                <div class="mt-2 text-sm app-muted">{{ marketBusy ? "Refreshing ranked matches..." : "Best route matches right now." }}</div>
                            </div>
                            <StatusBadge :label="marketLoaded ? `${prioritizedDrivers.length} matches` : (requestReady ? 'Live preview' : 'Awaiting route')" :tone="requestReady ? 'brand' : 'neutral'" />
                        </div>
                    </div>

                    <div v-if="marketBusy" class="grid gap-4 md:grid-cols-2">
                        <article v-for="index in 4" :key="index" class="app-panel animate-pulse rounded-[28px] overflow-hidden p-0">
                            <div class="h-48" style="background: rgba(47,46,124,0.08);" />
                            <div class="space-y-4 p-5">
                                <div class="h-6 w-32 rounded-full" style="background: rgba(31,31,31,0.08);" />
                                <div class="h-5 w-48 rounded-full" style="background: rgba(31,31,31,0.06);" />
                                <div class="h-24 rounded-[20px]" style="background: rgba(31,31,31,0.06);" />
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <div class="h-12 rounded-2xl" style="background: rgba(31,31,31,0.08);" />
                                    <div class="h-12 rounded-2xl" style="background: rgba(31,31,31,0.08);" />
                                </div>
                            </div>
                        </article>
                    </div>

                    <div v-else-if="prioritizedDrivers.length" class="grid gap-4 md:grid-cols-2">
                        <article
                            v-for="driver in prioritizedDrivers"
                            :key="driver.id"
                            class="group relative overflow-hidden rounded-[32px] border transition duration-300 hover:-translate-y-1.5 cursor-pointer"
                            :style="driver.isSelected
                                ? 'border-color: rgba(47,46,124,0.42); background: linear-gradient(180deg, rgba(255,255,255,1), rgba(248,248,248,0.98)); box-shadow: 0 28px 56px rgba(47,46,124,0.16);'
                                : 'border-color: rgba(47,46,124,0.16); background: linear-gradient(180deg, rgba(255,255,255,1), rgba(248,248,248,0.98)); box-shadow: 0 22px 50px rgba(31,31,31,0.08);'"
                            @click="selectDriver(driver)"
                        >
                            <div class="absolute inset-0 opacity-0 transition duration-300 group-hover:opacity-100" style="background: linear-gradient(180deg, rgba(242,201,0,0.08), transparent 40%, rgba(47,46,124,0.05));" />
                            <div class="relative h-48 overflow-hidden">
                                <div class="flex h-full w-full items-center justify-center" style="background: linear-gradient(180deg, rgba(47,46,124,0.10), rgba(47,46,124,0.22));">
                                    <img v-if="driver.image" :src="driver.image" :alt="driver.name" class="h-full w-full object-cover" />
                                    <div v-else class="flex h-24 w-24 items-center justify-center rounded-[28px] border text-3xl font-black text-white" style="border-color: rgba(255,255,255,0.24); background: rgba(255,255,255,0.12); backdrop-filter: blur(10px);">{{ driver.initials }}</div>
                                </div>
                                <div class="absolute inset-0" style="background: linear-gradient(180deg, rgba(20,20,20,0.04) 0%, rgba(20,20,20,0.10) 42%, rgba(20,20,20,0.72) 100%);" />
                                <div class="absolute inset-x-4 top-4 flex items-start justify-between gap-3">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="rounded-full px-3 py-1 text-[11px] font-bold uppercase tracking-[0.16em]" :style="driver.availableNow ? 'background:#DFF5E8;color:#177245;' : 'background:rgba(255,255,255,0.88);color:#1F1F1F;'">{{ driver.availabilityLabel }}</span>
                                        <span class="rounded-full px-3 py-1 text-[11px] font-bold uppercase tracking-[0.16em]" :style="driver.isSelected ? 'background:#F2C900;color:#1F1F1F;' : 'background:rgba(255,255,255,0.88);color:#2F2E7C;'">{{ driver.isSelected ? "Chosen" : driver.matchLabel }}</span>
                                    </div>
                                    <div class="flex flex-col items-end gap-2">
                                        <div class="rounded-[18px] border px-3 py-2 text-right" style="border-color: rgba(255,255,255,0.18); background: rgba(255,255,255,0.12); backdrop-filter: blur(10px);">
                                            <div class="text-[10px] font-bold uppercase tracking-[0.18em] text-white/70">Match</div>
                                            <div class="mt-1 text-sm font-black text-white">{{ driver.matchScore }}%</div>
                                        </div>
                                        <button type="button" class="rounded-full px-3 py-1.5 text-[11px] font-bold uppercase tracking-[0.16em] transition" :style="driver.isSelected ? 'background:#1F1F1F;color:#FFFFFF;' : 'background:rgba(255,255,255,0.92);color:#1F1F1F;'" @click.stop="selectDriver(driver)">{{ driver.isSelected ? "Selected" : "Pick Driver" }}</button>
                                    </div>
                                </div>
                                <div class="absolute inset-x-5 bottom-5">
                                    <div class="text-2xl font-black text-white">{{ driver.name }}</div>
                                    <div class="mt-1 text-sm text-white/80">{{ driver.vehicle }}</div>
                                </div>
                            </div>

                            <div class="relative space-y-5 p-5 sm:p-6">
                                <div class="grid gap-3 sm:grid-cols-3">
                                    <div class="rounded-[22px] border px-4 py-3" style="border-color: rgba(47,46,124,0.10); background: var(--app-surface-soft);">
                                        <div class="text-[10px] font-bold uppercase tracking-[0.18em] app-muted">Route</div>
                                        <div class="mt-2 text-sm font-black app-title">{{ driver.routeStart }} -> {{ driver.routeEnd }}</div>
                                    </div>
                                    <div class="rounded-[22px] border px-4 py-3" style="border-color: rgba(47,46,124,0.10); background: var(--app-surface-soft);">
                                        <div class="text-[10px] font-bold uppercase tracking-[0.18em] app-muted">ETA</div>
                                        <div class="mt-2 text-sm font-black app-title">{{ driver.eta.label }}</div>
                                    </div>
                                    <div class="rounded-[22px] border px-4 py-3" style="border-color: rgba(47,46,124,0.10); background: var(--app-surface-soft);">
                                        <div class="text-[10px] font-bold uppercase tracking-[0.18em] app-muted">Estimate</div>
                                        <div class="mt-2 text-sm font-black app-title">{{ driver.priceRange.label }}</div>
                                    </div>
                                </div>

                                <div class="rounded-[24px] border p-4" style="border-color: rgba(47,46,124,0.12); background: linear-gradient(180deg, rgba(242,201,0,0.10), rgba(255,255,255,0.75));">
                                    <div class="text-[11px] font-bold uppercase tracking-[0.16em]" style="color:#2F2E7C;">Route</div>
                                    <div class="mt-2 text-sm font-black app-title">{{ driver.routeSummary }}</div>
                                    <div v-if="driver.routeMidStops.length" class="mt-3 flex flex-wrap gap-2">
                                        <span v-for="stop in driver.routeMidStops.slice(0, 4)" :key="`${driver.id}-${stop}`" class="rounded-full border px-3 py-1.5 text-[11px] font-bold" style="border-color: var(--app-border); background: rgba(255,255,255,0.9); color: var(--app-text);">{{ stop }}</span>
                                    </div>
                                </div>

                                <div class="flex flex-wrap gap-2">
                                    <span v-for="badge in [...driver.badges, ...driver.reasons].slice(0, 5)" :key="`${driver.id}-${badge}`" class="rounded-full border px-3 py-1.5 text-[11px] font-bold uppercase tracking-[0.14em]" style="border-color: var(--app-border); background: var(--app-surface-soft); color: var(--app-text);">{{ badge }}</span>
                                </div>

                                <div class="flex items-center justify-between border-t pt-4" style="border-color: rgba(47,46,124,0.10);">
                                    <div class="text-sm app-muted">{{ driver.isSelected ? "Selected for this booking wizard." : "Tap this card to pick this driver." }}</div>
                                    <div class="text-sm font-black uppercase tracking-[0.14em]" style="color:#2F2E7C;">{{ driver.matchScore }}% match</div>
                                </div>

                                <div v-if="driver.isExpanded" class="rounded-[24px] border px-4 py-4" style="border-color: rgba(47,46,124,0.12); background: rgba(47,46,124,0.03);">
                                    <div class="text-[11px] font-bold uppercase tracking-[0.16em]" style="color:#2F2E7C;">Parcel specialties</div>
                                    <div class="mt-3 flex flex-wrap gap-2">
                                        <span v-for="specialty in driver.parcelSpecialties" :key="`${driver.id}-${specialty.id}`" class="rounded-full border px-3 py-1.5 text-[11px] font-bold uppercase tracking-[0.14em]" style="border-color: var(--app-border); background: rgba(255,255,255,0.9); color: var(--app-text);">{{ specialty.name }}</span>
                                    </div>
                                </div>

                                <div class="grid gap-3 sm:grid-cols-2">
                                    <button type="button" class="app-primary-btn w-full" :style="driver.isSelected ? 'background:#1F1F1F;' : ''" @click.stop="selectDriver(driver)">{{ driver.isSelected ? "Selected" : "Select Driver" }}</button>
                                    <button type="button" class="app-outline-btn w-full" @click.stop="toggleDriverExpansion(driver)">{{ driver.isExpanded ? "Hide Details" : "Expand Details" }}<ChevronDown class="h-4 w-4 transition" :class="driver.isExpanded ? 'rotate-180' : ''" /></button>
                                </div>

                                <Link :href="driver.href" class="app-outline-btn w-full" @click.stop>View Full Details</Link>
                            </div>
                        </article>
                    </div>

                    <div v-else>
                        <EmptyState :title="requestReady ? 'No drivers found' : 'Start your route search'" :description="requestReady ? 'No drivers match this route and parcel combination right now. Adjust the route, parcel type, or availability filter and try again.' : 'Choose the route and parcel details first to unlock ranked driver matches.'" icon="!">
                            <template #action>
                                <button type="button" class="app-outline-btn" @click="clearFilters">Reset Filters</button>
                            </template>
                        </EmptyState>
                    </div>

                    <div v-if="marketError" class="rounded-[24px] border px-4 py-4 text-sm" style="border-color: rgba(220,38,38,0.18); background: rgba(220,38,38,0.06); color:#b91c1c;">{{ marketError }}</div>
                </section>
                <aside class="space-y-6">
                    <StickyBookingSummary title="Wizard summary" :items="summaryItems" :badges="summaryBadges">
                        <div class="mt-4 grid gap-3">
                            <Link v-if="confirmationHref" :href="confirmationHref" class="app-primary-btn w-full">Continue to Confirm<ArrowRight class="h-4 w-4" /></Link>
                            <button v-else type="button" class="app-outline-btn w-full" disabled>Select a driver to continue</button>
                            <button type="button" class="app-outline-btn w-full" @click="infoToast('Select any match card to lock that driver into the next wizard step.', 'Driver choice')">How selection works</button>
                        </div>
                    </StickyBookingSummary>

                    <div class="app-panel rounded-[28px] p-5">
                        <div class="text-[11px] font-bold uppercase tracking-[0.18em]" style="color:#2F2E7C;">Live pricing</div>
                        <div class="mt-4 grid gap-3">
                            <div class="rounded-[22px] border p-4" style="border-color: var(--app-border); background: var(--app-surface-soft);">
                                <div class="inline-flex items-center gap-2 text-[11px] font-bold uppercase tracking-[0.16em] app-muted"><MapPinned class="h-4 w-4" />Distance</div>
                                <div class="mt-2 text-lg font-black app-title">{{ quote?.distance_km ? `${Number(quote.distance_km).toFixed(0)} km` : "Pending route" }}</div>
                            </div>
                            <div class="rounded-[22px] border p-4" style="border-color: var(--app-border); background: var(--app-surface-soft);">
                                <div class="inline-flex items-center gap-2 text-[11px] font-bold uppercase tracking-[0.16em] app-muted"><Clock3 class="h-4 w-4" />ETA</div>
                                <div class="mt-2 text-lg font-black app-title">{{ quote?.estimated_hours ? `${Number(quote.estimated_hours).toFixed(1)} hrs` : "Pending route" }}</div>
                            </div>
                            <div class="rounded-[22px] border p-4" style="border-color: var(--app-border); background: var(--app-surface-soft);">
                                <div class="inline-flex items-center gap-2 text-[11px] font-bold uppercase tracking-[0.16em] app-muted"><CircleDollarSign class="h-4 w-4" />Estimate</div>
                                <div class="mt-2 text-lg font-black app-title">{{ quote?.total_price ? `N$ ${Number(quote.total_price).toFixed(2)}` : "Pending route" }}</div>
                            </div>
                        </div>
                    </div>

                    <div class="app-panel rounded-[28px] p-5">
                        <div class="text-[11px] font-bold uppercase tracking-[0.18em]" style="color:#2F2E7C;">Route notes</div>
                        <div class="mt-4 space-y-3 text-sm app-muted">
                            <div class="rounded-[20px] border px-4 py-3" style="border-color: var(--app-border); background: var(--app-surface-soft);">Live quote updates when route, weight, load size, or urgency changes.</div>
                            <div class="rounded-[20px] border px-4 py-3" style="border-color: var(--app-border); background: var(--app-surface-soft);">Your selected driver carries into confirmation, quotation, and invoice generation.</div>
                        </div>
                    </div>
                </aside>
            </section>

            <div v-if="selectedDriver" class="fixed inset-x-0 bottom-0 z-30 border-t px-4 py-3 sm:px-6 lg:px-10 2xl:px-12" style="border-color: var(--app-border); background: rgba(255,255,255,0.94); backdrop-filter: blur(18px);">
                <div class="mx-auto flex w-full max-w-[1800px] flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <div class="min-w-0">
                        <div class="text-[11px] font-bold uppercase tracking-[0.18em]" style="color:#2F2E7C;">Selected driver</div>
                        <div class="mt-1 text-lg font-black app-title">{{ selectedDriver.name }}</div>
                        <div class="mt-1 text-sm app-muted">{{ selectedDriver.matchScore }}% match | {{ selectedDriver.priceRange.label }} | {{ selectedDriver.eta.label }}</div>
                    </div>
                    <div class="flex flex-col gap-3 sm:flex-row">
                        <button type="button" class="app-outline-btn" @click="infoToast('Your selection is saved in this wizard. Continue whenever you are ready.', 'Selection saved')">Keep Comparing</button>
                        <Link :href="confirmationHref || selectedDriver.href" class="app-primary-btn">Continue to Confirmation<ArrowRight class="h-4 w-4" /></Link>
                    </div>
                </div>
            </div>
        </div>
    </PublicLayout>
</template>
