<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from "vue";
import { Head, Link, useForm, usePage } from "@inertiajs/vue3";
import {
    ArrowRight,
    CircleDollarSign,
    Clock3,
    MapPinned,
    PackageCheck,
    Phone,
    ShieldCheck,
    Truck,
    UserRound,
    Weight,
    Zap,
} from "lucide-vue-next";
import PublicLayout from "@/Layouts/PublicLayout.vue";
import EmptyState from "@/Components/AppShell/EmptyState.vue";
import StatusBadge from "@/Components/AppShell/StatusBadge.vue";
import BookingStepper from "@/Components/Booking/BookingStepper.vue";
import PaymentStatusCard from "@/Components/Booking/PaymentStatusCard.vue";
import PriceBreakdown from "@/Components/Booking/PriceBreakdown.vue";
import StickyBookingSummary from "@/Components/Booking/StickyBookingSummary.vue";
import SuccessStateCard from "@/Components/Booking/SuccessStateCard.vue";
import api from "@/lib/api";
import { errorToast, successToast } from "@/composables/useAppToast";

const props = defineProps({
    selection: { type: Object, required: true },
    routeLabels: { type: Object, required: true },
});

const page = usePage();
const isAuthenticated = computed(() => !!page.props.auth?.user);
const loading = ref(true);
const previewError = ref("");
const selectedDriver = ref(null);
const alternatives = ref([]);
const quote = ref(null);
const quoteExpiresAt = ref("");
const nowTs = ref(Date.now());
const flowStage = ref("confirm");
const bookingResult = ref(null);
const nextActions = ref({});
const submissionError = ref("");
let quoteTimer = null;

const urgencyLabels = { standard: "Standard", express: "Express", same_day: "Same Day" };
const loadLabels = { small: "Small", medium: "Medium", large: "Large", heavy: "Heavy", oversized: "Oversized" };
const bookingSteps = [
    { id: 1, label: "Find Driver", icon: Truck },
    { id: 2, label: "Confirm", icon: ShieldCheck },
    { id: 3, label: "Payment", icon: CircleDollarSign },
    { id: 4, label: "Track", icon: MapPinned },
];
const currentStep = computed(() => (flowStage.value === "confirm" ? 2 : 4));

const form = useForm({
    pickup_location_id: props.selection.pickup_location_id,
    dropoff_location_id: props.selection.dropoff_location_id,
    package_type_id: props.selection.package_type_id,
    selected_driver_id: props.selection.selected_driver_id,
    weight_kg: props.selection.weight_kg || "",
    load_size: props.selection.load_size || "medium",
    urgency_level: props.selection.urgency_level || "standard",
    pickup_address: "",
    dropoff_address: "",
    receiver_name: "",
    receiver_phone: "",
    client_offer_price: "",
    declared_value: "",
    notes: "",
    confirmation_flow: "driver_selection",
});

const routeLabel = computed(
    () => `${props.routeLabels.pickup?.name || "Pickup"} -> ${props.routeLabels.dropoff?.name || "Destination"}`
);

function createInitials(name) {
    return String(name || "Driver")
        .split(" ")
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase() || "")
        .join("") || "D";
}

const summaryItems = computed(() => [
    { label: "Route", value: routeLabel.value, meta: `${props.routeLabels.packageType?.name || "Parcel"} delivery` },
    { label: "Weight", value: form.weight_kg ? `${form.weight_kg} kg` : "Not set", meta: `${loadLabels[form.load_size] || form.load_size} load` },
    { label: "Urgency", value: urgencyLabels[form.urgency_level] || "Standard", meta: "Tracking included" },
]);

const previewDriverSummaryItems = computed(() => [
    { label: "Selected driver", value: selectedDriver.value?.name || "Driver unavailable", meta: selectedDriver.value?.route_summary || "Choose another driver" },
    { label: "Vehicle", value: selectedDriver.value?.vehicle || "Vehicle pending", meta: `${selectedDriver.value?.max_load_size || form.load_size} load ready` },
    { label: "Estimated arrival", value: `${Number(quote.value?.estimated_hours || 0).toFixed(1)} hrs`, meta: selectedDriver.value?.available_now ? "Available now" : "Availability may shift" },
]);

const breakdownRows = computed(() => [
    { label: "Base fare", value: `N$ ${Number(quote.value?.base_price || 0).toFixed(2)}` },
    { label: "Weight fee", value: `N$ ${Number(quote.value?.weight_surcharge || 0).toFixed(2)}` },
    { label: "Urgency fee", value: `N$ ${Number(quote.value?.urgency_surcharge || 0).toFixed(2)}` },
    {
        label: "Your offer",
        value: form.client_offer_price ? `N$ ${Number(form.client_offer_price).toFixed(2)}` : "Using platform estimate",
        highlight: Boolean(form.client_offer_price),
    },
]);

const estimatedTotal = computed(() => `N$ ${Number(form.client_offer_price || quote.value?.total_price || 0).toFixed(2)}`);
const activeParcel = computed(() => bookingResult.value || null);
const confirmedDriver = computed(() => activeParcel.value?.assigned_driver || activeParcel.value?.preferred_driver || selectedDriver.value || null);
const confirmedDriverName = computed(() => confirmedDriver.value?.name || "Matched driver");
const confirmedDriverRoute = computed(() => confirmedDriver.value?.route_summary || selectedDriver.value?.route_summary || routeLabel.value);
const confirmedDriverVehicle = computed(() => {
    const vehicle = confirmedDriver.value?.vehicle;
    if (!vehicle) return selectedDriver.value?.vehicle || "Vehicle pending";
    if (typeof vehicle === "string") return vehicle;
    return [vehicle.car_make, vehicle.car_model].filter(Boolean).join(" ") || vehicle.vehicle_type || "Vehicle pending";
});
const confirmedSummaryItems = computed(() => [
    {
        label: "Selected driver",
        value: confirmedDriverName.value,
        meta: confirmedDriverRoute.value,
    },
    {
        label: "Vehicle",
        value: confirmedDriverVehicle.value,
        meta: activeParcel.value?.booking_status_label || "Booking confirmed",
    },
    {
        label: "Tracking",
        value: activeParcel.value?.tracking_number || "Generating",
        meta: activeParcel.value?.booking_reference || "Booking reference live",
    },
]);
const paymentMethods = computed(() => activeParcel.value?.payment_methods || ["mobile_money", "bank_transfer", "card", "cash_on_pickup", "cash_on_delivery"]);
const paymentMethodLabels = computed(() =>
    paymentMethods.value.map((method) =>
        method
            .replaceAll("_", " ")
            .replace(/\b\w/g, (char) => char.toUpperCase())
    )
);
const confirmedTotal = computed(() => `N$ ${Number(activeParcel.value?.client_offer_price || activeParcel.value?.final_price || activeParcel.value?.estimated_price || activeParcel.value?.total_price || 0).toFixed(2)}`);
const confirmedBreakdownRows = computed(() => [
    { label: "Base fare", value: `N$ ${Number(activeParcel.value?.base_price || 0).toFixed(2)}` },
    { label: "Distance / route", value: `${Number(activeParcel.value?.distance_km || 0).toFixed(0)} km` },
    { label: "Weight fee", value: `N$ ${Number(activeParcel.value?.weight_surcharge || 0).toFixed(2)}` },
    { label: "Urgency fee", value: `N$ ${Number(activeParcel.value?.urgency_surcharge || 0).toFixed(2)}` },
    { label: "Payment status", value: activeParcel.value?.payment_status?.replaceAll("_", " ") || "pending", highlight: true },
]);

const quoteExpired = computed(() => {
    if (!quoteExpiresAt.value) return false;
    const expires = new Date(quoteExpiresAt.value).getTime();
    if (Number.isNaN(expires)) return false;
    return nowTs.value >= expires;
});

const paymentNextStep = computed(() =>
    isAuthenticated.value
        ? "Ready to reserve and continue"
        : "Sign in to finish booking"
);

const previewTrustBadges = computed(() => {
    const reasons = (selectedDriver.value?.reasons || []).slice(0, 2);
    return ["Secure Booking", "Tracking Included", "Transparent Pricing", ...reasons].slice(0, 5);
});
const confirmedTrustBadges = computed(() => [
    "Secure Booking",
    "Tracking Included",
    "Transparent Pricing",
    activeParcel.value?.payment_status === "ready" ? "Payment Ready" : "Payment Pending",
    activeParcel.value?.booking_status_label || "Booking Confirmed",
].filter(Boolean).slice(0, 5));
const sidebarSummaryItems = computed(() => (flowStage.value === "confirm" ? previewDriverSummaryItems.value : confirmedSummaryItems.value));
const sidebarBadges = computed(() => (flowStage.value === "confirm" ? previewTrustBadges.value : confirmedTrustBadges.value));
const confirmDisabled = computed(() => form.processing || loading.value || !selectedDriver.value || !isAuthenticated.value || quoteExpired.value);

const backToDriversHref = computed(() => {
    const params = new URLSearchParams({
        pickup: String(form.pickup_location_id),
        destination: String(form.dropoff_location_id),
        parcel: String(form.package_type_id),
        urgency: form.urgency_level,
        load: form.load_size,
    });

    if (form.weight_kg) params.set("weight", String(form.weight_kg));

    return `${route("find.Driver")}?${params.toString()}`;
});

function formatQuoteExpiry(value) {
    if (!value) return "Live estimate";

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return "Live estimate";

    if (quoteExpired.value) {
        return "Estimate expired - refresh required";
    }

    return `Estimate refreshes around ${date.toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" })}`;
}

async function loadPreview() {
    loading.value = true;
    previewError.value = "";
    submissionError.value = "";

    try {
        const { data } = await api.get(route("parcel-requests.preview"), {
            params: {
                pickup_location_id: form.pickup_location_id,
                dropoff_location_id: form.dropoff_location_id,
                package_type_id: form.package_type_id,
                selected_driver_id: form.selected_driver_id,
                weight_kg: form.weight_kg || null,
                load_size: form.load_size,
                urgency_level: form.urgency_level,
                limit: 12,
            },
        });

        quote.value = data.quote || null;
        quoteExpiresAt.value = data.quote_expires_at || "";
        selectedDriver.value = data.selected_driver || (data.drivers || []).find((driver) => Number(driver.id) === Number(form.selected_driver_id)) || null;
        alternatives.value = (data.drivers || []).filter((driver) => Number(driver.id) !== Number(form.selected_driver_id)).slice(0, 3);

        if (selectedDriver.value?.id) {
            form.selected_driver_id = Number(selectedDriver.value.id);
        }

        if (!selectedDriver.value) {
            previewError.value = "This driver is no longer available for the current request. Please choose another match.";
            form.selected_driver_id = "";
        }
    } catch (error) {
        previewError.value = error.response?.data?.message || "Could not load the selected driver confirmation.";
    } finally {
        loading.value = false;
    }
}

async function submitSelection() {
    if (confirmDisabled.value) return;

    if (!isAuthenticated.value) {
        errorToast("Sign in to continue with this driver and create the parcel request.", "Login required");
        return;
    }

    if (!selectedDriver.value?.id) {
        submissionError.value = "Please go back and select a driver before confirming this booking.";
        errorToast(submissionError.value, "Driver required");
        return;
    }

    form.processing = true;
    form.clearErrors();
    submissionError.value = "";

    try {
        const { data } = await api.post(route("parcel-requests.store"), {
            ...form.data(),
            selected_driver_id: Number(selectedDriver.value.id),
            confirmation_flow: "driver_selection",
        });

        if (!data.parcel) {
            submissionError.value = "The booking was saved, but the payment-ready details did not arrive. Please retry.";
            errorToast(submissionError.value, "Booking details missing");
            return;
        }

        bookingResult.value = data.parcel;
        nextActions.value = data.next_actions || {};
        flowStage.value = "payment";
        successToast(data.message || "Booking confirmed and ready for payment.", "Booking confirmed");
    } catch (error) {
        const errors = error.response?.data?.errors || {};
        form.setError(errors);
        const firstError = Object.values(errors || {}).flat().find(Boolean);
        submissionError.value = firstError || error.response?.data?.message || "Please check the confirmation details and try again.";
        errorToast(submissionError.value, "Could not continue");
    } finally {
        form.processing = false;
    }
}

onMounted(() => {
    loadPreview();
    quoteTimer = window.setInterval(() => {
        nowTs.value = Date.now();
    }, 15000);
});

onBeforeUnmount(() => {
    window.clearInterval(quoteTimer);
});
</script>

<template>
    <Head title="Confirm Booking" />

    <PublicLayout>
        <div class="w-full px-4 py-6 sm:px-6 lg:px-10 2xl:px-12">
            <BookingStepper :steps="bookingSteps" :current="currentStep" compact class="mb-6 lg:hidden" />

            <section class="app-panel rounded-[30px] p-5 sm:p-6 lg:p-7">
                <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                    <div class="max-w-4xl">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="rounded-full px-3 py-1 text-[11px] font-bold uppercase tracking-[0.18em]" style="background: rgba(47,46,124,0.08); color:#2F2E7C;">
                                Driver selection
                            </span>
                            <span class="rounded-full px-3 py-1 text-[11px] font-bold uppercase tracking-[0.18em]" style="background: rgba(242,201,0,0.22); color:#1F1F1F;">
                                {{ flowStage === "confirm" ? "Review before booking" : "Payment-ready booking" }}
                            </span>
                        </div>

                        <BookingStepper :steps="bookingSteps" :current="currentStep" class="mt-4 hidden lg:block" />

                        <h1 class="mt-5 text-3xl font-black app-title sm:text-4xl">
                            {{ flowStage === "confirm" ? "Step 2: Confirm your booking details" : "Step 4: Booking confirmed and tracking-ready" }}
                        </h1>
                        <p class="mt-3 max-w-3xl text-sm leading-7 app-muted">
                            {{ flowStage === "confirm"
                                ? "Review the selected driver, route, receiver details, and price before we move the request into the payment-ready stage."
                                : "Your booking reference, tracking number, and payment-ready status are now live. Continue to tracking or review your requests." }}
                        </p>
                    </div>

                    <div class="flex flex-col gap-3 sm:flex-row">
                        <Link v-if="flowStage === 'confirm'" :href="backToDriversHref" class="app-outline-btn">Change Driver</Link>
                        <Link v-if="flowStage !== 'confirm'" :href="route('user.parcels.index')" class="app-outline-btn">View My Requests</Link>
                        <Link v-if="!isAuthenticated && flowStage === 'confirm'" :href="route('login')" class="app-primary-btn">Sign in to continue</Link>
                    </div>
                </div>
            </section>

            <section v-if="flowStage === 'confirm'" class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
                <div class="space-y-6">
                    <div class="app-panel rounded-[30px] p-6">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <div class="text-[11px] font-bold uppercase tracking-[0.18em]" style="color:#2F2E7C;">Booking review</div>
                                <h2 class="mt-2 text-2xl font-black app-title">Driver, route, and delivery summary</h2>
                            </div>
                            <StatusBadge :label="formatQuoteExpiry(quoteExpiresAt)" :tone="quoteExpired ? 'warning' : 'info'" />
                        </div>

                        <div v-if="loading" class="mt-5 rounded-[26px] border p-5 animate-pulse" style="border-color: var(--app-border); background: var(--app-surface-soft);">
                            <div class="h-6 w-48 rounded-full" style="background: rgba(31,31,31,0.08);" />
                            <div class="mt-4 grid gap-3 md:grid-cols-3">
                                <div class="h-24 rounded-[20px]" style="background: rgba(31,31,31,0.06);" />
                                <div class="h-24 rounded-[20px]" style="background: rgba(31,31,31,0.06);" />
                                <div class="h-24 rounded-[20px]" style="background: rgba(31,31,31,0.06);" />
                            </div>
                        </div>

                        <div v-else-if="selectedDriver" class="mt-5 space-y-5">
                            <div class="rounded-[26px] border p-5" style="border-color: rgba(47,46,124,0.22); background: rgba(47,46,124,0.05);">
                                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <StatusBadge :label="selectedDriver.available_now ? 'Available now' : 'Busy'" :tone="selectedDriver.available_now ? 'success' : 'dark'" />
                                            <StatusBadge :label="selectedDriver.match_label || 'Strong match'" tone="brand" />
                                            <StatusBadge label="Verified Driver" tone="info" />
                                        </div>

                                        <h3 class="mt-3 text-2xl font-black app-title">{{ selectedDriver.name }}</h3>
                                        <div class="mt-1 text-sm app-muted">{{ selectedDriver.route_summary }}</div>

                                        <div class="mt-4 grid gap-3 sm:grid-cols-3">
                                            <div class="rounded-[20px] border p-4" style="border-color: var(--app-border); background: rgba(255,255,255,0.84);">
                                                <div class="inline-flex items-center gap-2 text-[11px] font-bold uppercase tracking-[0.16em] app-muted">
                                                    <Truck class="h-4 w-4" />
                                                    Vehicle
                                                </div>
                                                <div class="mt-2 text-sm font-black app-title">{{ selectedDriver.vehicle }}</div>
                                            </div>
                                            <div class="rounded-[20px] border p-4" style="border-color: var(--app-border); background: rgba(255,255,255,0.84);">
                                                <div class="inline-flex items-center gap-2 text-[11px] font-bold uppercase tracking-[0.16em] app-muted">
                                                    <Clock3 class="h-4 w-4" />
                                                    Delivery
                                                </div>
                                                <div class="mt-2 text-sm font-black app-title">{{ Number(quote?.estimated_hours || 0).toFixed(1) }} hrs</div>
                                            </div>
                                            <div class="rounded-[20px] border p-4" style="border-color: var(--app-border); background: rgba(255,255,255,0.84);">
                                                <div class="inline-flex items-center gap-2 text-[11px] font-bold uppercase tracking-[0.16em] app-muted">
                                                    <ShieldCheck class="h-4 w-4" />
                                                    Trust
                                                </div>
                                                <div class="mt-2 text-sm font-black app-title">{{ selectedDriver.match_score }}% match</div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="rounded-[22px] border p-4 text-sm" style="border-color: var(--app-border); background: rgba(255,255,255,0.86);">
                                        <div class="text-[11px] font-bold uppercase tracking-[0.16em] app-muted">Pricing</div>
                                        <div class="mt-2 text-xl font-black app-title">{{ estimatedTotal }}</div>
                                        <div class="mt-1 app-muted">Tracking included</div>
                                    </div>
                                </div>

                                <div class="mt-5">
                                    <div class="text-[11px] font-bold uppercase tracking-[0.16em] app-muted">Match reasons</div>
                                    <div class="mt-3 flex flex-wrap gap-2">
                                        <span
                                            v-for="reason in selectedDriver.reasons || []"
                                            :key="reason"
                                            class="rounded-full border px-3 py-1.5 text-[11px] font-bold"
                                            style="border-color: var(--app-border); background: rgba(255,255,255,0.9); color: var(--app-text);"
                                        >
                                            {{ reason }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div class="grid gap-3 md:grid-cols-3">
                                <div
                                    v-for="item in summaryItems"
                                    :key="item.label"
                                    class="rounded-[22px] border p-4"
                                    style="border-color: var(--app-border); background: var(--app-surface-soft);"
                                >
                                    <div class="text-[11px] font-bold uppercase tracking-[0.16em] app-muted">{{ item.label }}</div>
                                    <div class="mt-2 text-sm font-black app-title">{{ item.value }}</div>
                                    <div class="mt-1 text-sm app-muted">{{ item.meta }}</div>
                                </div>
                            </div>
                        </div>

                        <EmptyState
                            v-else
                            title="Driver no longer available"
                            description="This selected driver is no longer available for the current request. Go back and pick another match."
                            icon="!"
                        >
                            <template #action>
                                <Link :href="backToDriversHref" class="app-primary-btn">Back to Drivers</Link>
                            </template>
                        </EmptyState>
                    </div>

                    <form class="app-panel rounded-[30px] p-6" @submit.prevent="submitSelection">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <div class="text-[11px] font-bold uppercase tracking-[0.18em]" style="color:#2F2E7C;">Parcel confirmation</div>
                                <h2 class="mt-2 text-2xl font-black app-title">Receiver and handoff details</h2>
                            </div>
                            <StatusBadge label="Secure booking" tone="brand" />
                        </div>

                        <div class="mt-5 grid gap-4 md:grid-cols-2">
                            <label class="space-y-2">
                                <span class="app-label app-field-label"><UserRound class="h-4 w-4" />Receiver name</span>
                                <input v-model="form.receiver_name" type="text" class="app-field" />
                                <div v-if="form.errors.receiver_name" class="text-sm text-red-600">{{ form.errors.receiver_name }}</div>
                            </label>

                            <label class="space-y-2">
                                <span class="app-label app-field-label"><Phone class="h-4 w-4" />Receiver phone</span>
                                <input v-model="form.receiver_phone" type="text" class="app-field" />
                                <div v-if="form.errors.receiver_phone" class="text-sm text-red-600">{{ form.errors.receiver_phone }}</div>
                            </label>

                            <label class="space-y-2">
                                <span class="app-label app-field-label"><MapPinned class="h-4 w-4" />Pickup note</span>
                                <input v-model="form.pickup_address" type="text" class="app-field" placeholder="Street, area, landmark" />
                                <div v-if="form.errors.pickup_address" class="text-sm text-red-600">{{ form.errors.pickup_address }}</div>
                            </label>

                            <label class="space-y-2">
                                <span class="app-label app-field-label"><MapPinned class="h-4 w-4" />Dropoff note</span>
                                <input v-model="form.dropoff_address" type="text" class="app-field" placeholder="Street, area, landmark" />
                                <div v-if="form.errors.dropoff_address" class="text-sm text-red-600">{{ form.errors.dropoff_address }}</div>
                            </label>

                            <label class="space-y-2">
                                <span class="app-label app-field-label"><CircleDollarSign class="h-4 w-4" />Offer amount</span>
                                <input v-model="form.client_offer_price" type="number" min="1" step="0.01" class="app-field" placeholder="Optional" />
                                <div v-if="form.errors.client_offer_price" class="text-sm text-red-600">{{ form.errors.client_offer_price }}</div>
                            </label>

                            <label class="space-y-2">
                                <span class="app-label app-field-label"><PackageCheck class="h-4 w-4" />Declared value</span>
                                <input v-model="form.declared_value" type="number" min="0" step="0.01" class="app-field" placeholder="Optional" />
                                <div v-if="form.errors.declared_value" class="text-sm text-red-600">{{ form.errors.declared_value }}</div>
                            </label>
                        </div>

                        <label class="mt-4 block space-y-2">
                            <span class="app-label app-field-label"><Zap class="h-4 w-4" />Handling notes</span>
                            <textarea v-model="form.notes" rows="4" class="app-field !h-auto !py-3" placeholder="Fragile cargo, access notes, special handling..." />
                            <div v-if="form.errors.notes" class="text-sm text-red-600">{{ form.errors.notes }}</div>
                        </label>

                        <div v-if="form.errors.selected_driver_id" class="mt-4 rounded-[20px] border px-4 py-3 text-sm" style="border-color: rgba(220,38,38,0.18); background: rgba(220,38,38,0.06); color:#b91c1c;">
                            {{ form.errors.selected_driver_id }}
                        </div>

                        <div v-if="quoteExpired" class="mt-4 rounded-[20px] border px-4 py-3 text-sm" style="border-color: rgba(242,201,0,0.35); background: rgba(242,201,0,0.14); color:#5b4a00;">
                            Your price estimate expired. Refresh the booking details before confirming.
                            <button type="button" class="ml-3 font-bold underline" :disabled="loading || form.processing" @click="loadPreview">Refresh estimate</button>
                        </div>

                        <div v-if="previewError" class="mt-4 rounded-[20px] border px-4 py-3 text-sm" style="border-color: rgba(220,38,38,0.18); background: rgba(220,38,38,0.06); color:#b91c1c;">
                            {{ previewError }}
                        </div>

                        <div v-if="submissionError" class="mt-4 rounded-[20px] border px-4 py-3 text-sm" style="border-color: rgba(220,38,38,0.18); background: rgba(220,38,38,0.06); color:#b91c1c;">
                            {{ submissionError }}
                        </div>

                        <div class="mt-6 flex flex-col gap-3 border-t pt-5 sm:flex-row sm:items-center sm:justify-between" style="border-color: var(--app-border);">
                            <p class="max-w-2xl text-sm app-muted">
                                Confirming now reserves this booking in a payment-ready state and keeps the selected driver prioritized for the request.
                            </p>

                            <div class="flex flex-col gap-3 sm:flex-row">
                                <Link :href="backToDriversHref" class="app-outline-btn">Back to Drivers</Link>
                                <button type="submit" class="app-primary-btn" :disabled="confirmDisabled">
                                    {{ form.processing ? "Confirming..." : "Confirm Booking" }}
                                    <ArrowRight class="h-4 w-4" />
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <aside class="space-y-6">
                    <StickyBookingSummary title="Booking summary" :items="sidebarSummaryItems" :badges="sidebarBadges">
                        <div class="mt-4">
                            <PaymentStatusCard
                                booking-reference="Preview"
                                tracking-number="Generated after confirmation"
                                payment-status="ready"
                                booking-status="confirmed"
                                :total="estimatedTotal"
                                :next-step="paymentNextStep"
                            />
                        </div>
                    </StickyBookingSummary>

                    <PriceBreakdown
                        :rows="breakdownRows"
                        total-label="Estimated total"
                        :total-value="estimatedTotal"
                        :note="formatQuoteExpiry(quoteExpiresAt)"
                    />

                    <div class="app-panel rounded-[30px] p-6">
                        <div class="text-[11px] font-bold uppercase tracking-[0.18em]" style="color:#2F2E7C;">Alternate drivers</div>
                        <div class="mt-4 space-y-3">
                            <Link
                                v-for="driver in alternatives"
                                :key="driver.id"
                                :href="`${route('driver-selection.confirm')}?pickup=${form.pickup_location_id}&destination=${form.dropoff_location_id}&parcel=${form.package_type_id}&driver=${driver.id}&urgency=${form.urgency_level}&load=${form.load_size}${form.weight_kg ? `&weight=${form.weight_kg}` : ''}`"
                                class="block rounded-[22px] border p-4 transition hover:-translate-y-0.5"
                                style="border-color: var(--app-border); background: var(--app-surface-soft);"
                            >
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <div class="font-black app-title">{{ driver.name }}</div>
                                        <div class="mt-1 text-sm app-muted">{{ driver.route_summary }}</div>
                                    </div>
                                    <StatusBadge :label="`${driver.match_score}%`" tone="info" small />
                                </div>
                            </Link>

                            <div v-if="!alternatives.length" class="rounded-[22px] border p-4 text-sm app-muted" style="border-color: var(--app-border); background: var(--app-surface-soft);">
                                No alternate ranked drivers for this exact request right now.
                            </div>
                        </div>
                    </div>
                </aside>
            </section>

            <section v-else class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
                <div class="space-y-6">
                    <SuccessStateCard
                        eyebrow="Booking confirmed"
                        title="Driver assigned and tracking is ready"
                        description="Your request is booked, payment-ready, and visible in your parcel history. You can move straight into tracking or review the booking details below."
                        :booking-reference="activeParcel?.booking_reference || activeParcel?.tracking_number"
                        :tracking-number="activeParcel?.tracking_number"
                        :driver-name="confirmedDriverName"
                        :route-label="`${activeParcel?.pickup_location?.name || routeLabels.pickup?.name || 'Pickup'} -> ${activeParcel?.dropoff_location?.name || routeLabels.dropoff?.name || 'Destination'}`"
                        :eta-label="`${Number(activeParcel?.estimated_hours || quote?.estimated_hours || 0).toFixed(1)} hrs`"
                    />

                    <div class="app-panel rounded-[30px] p-6">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <div class="text-[11px] font-bold uppercase tracking-[0.18em]" style="color:#2F2E7C;">Payment-ready state</div>
                                <h2 class="mt-2 text-2xl font-black app-title">Booking reference, tracking, and payment status are live</h2>
                            </div>
                            <StatusBadge :label="activeParcel?.booking_status_label || 'Booking Confirmed'" tone="brand" />
                        </div>

                        <div class="mt-5 grid gap-3 md:grid-cols-3">
                            <div class="rounded-[22px] p-4" style="background: var(--app-surface-soft);">
                                <div class="inline-flex items-center gap-2 text-[11px] font-bold uppercase tracking-[0.16em] app-muted">
                                    <CircleDollarSign class="h-4 w-4" />
                                    Estimated total
                                </div>
                                <div class="mt-2 text-xl font-black app-title">{{ confirmedTotal }}</div>
                            </div>
                            <div class="rounded-[22px] p-4" style="background: var(--app-surface-soft);">
                                <div class="inline-flex items-center gap-2 text-[11px] font-bold uppercase tracking-[0.16em] app-muted">
                                    <Clock3 class="h-4 w-4" />
                                    Delivery timeline
                                </div>
                                <div class="mt-2 text-xl font-black app-title">{{ Number(activeParcel?.estimated_hours || 0).toFixed(1) }} hrs</div>
                            </div>
                            <div class="rounded-[22px] p-4" style="background: var(--app-surface-soft);">
                                <div class="inline-flex items-center gap-2 text-[11px] font-bold uppercase tracking-[0.16em] app-muted">
                                    <ShieldCheck class="h-4 w-4" />
                                    Payment status
                                </div>
                                <div class="mt-2 text-xl font-black app-title">{{ activeParcel?.payment_status?.replaceAll("_", " ") || "ready" }}</div>
                            </div>
                        </div>
                    </div>

                    <div class="app-panel rounded-[30px] p-6">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <div class="text-[11px] font-bold uppercase tracking-[0.18em]" style="color:#2F2E7C;">Selected driver</div>
                                <h2 class="mt-2 text-2xl font-black app-title">{{ confirmedDriverName }}</h2>
                                <div class="mt-2 text-sm app-muted">{{ confirmedDriverRoute }}</div>
                            </div>
                            <StatusBadge :label="activeParcel?.booking_status_label || 'Booking Confirmed'" tone="brand" />
                        </div>

                        <div class="mt-5 flex items-start gap-4">
                            <img
                                v-if="confirmedDriver?.image"
                                :src="confirmedDriver.image"
                                :alt="confirmedDriverName"
                                class="h-20 w-20 rounded-[22px] object-cover"
                            />
                            <div
                                v-else
                                class="flex h-20 w-20 items-center justify-center rounded-[22px] text-xl font-black"
                                style="background:#2F2E7C;color:#FFFFFF;"
                            >
                                {{ createInitials(confirmedDriverName) }}
                            </div>

                            <div class="grid min-w-0 flex-1 gap-3 md:grid-cols-3">
                                <div class="rounded-[20px] border p-4" style="border-color: var(--app-border); background: var(--app-surface-soft);">
                                    <div class="text-[11px] font-bold uppercase tracking-[0.16em] app-muted">Vehicle</div>
                                    <div class="mt-2 text-sm font-black app-title">{{ confirmedDriverVehicle }}</div>
                                </div>
                                <div class="rounded-[20px] border p-4" style="border-color: var(--app-border); background: var(--app-surface-soft);">
                                    <div class="text-[11px] font-bold uppercase tracking-[0.16em] app-muted">Booking ref</div>
                                    <div class="mt-2 text-sm font-black app-title">{{ activeParcel?.booking_reference || "Pending" }}</div>
                                </div>
                                <div class="rounded-[20px] border p-4" style="border-color: var(--app-border); background: var(--app-surface-soft);">
                                    <div class="text-[11px] font-bold uppercase tracking-[0.16em] app-muted">Tracking</div>
                                    <div class="mt-2 text-sm font-black app-title">{{ activeParcel?.tracking_number || "Pending" }}</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="app-panel rounded-[30px] p-6">
                        <div class="text-[11px] font-bold uppercase tracking-[0.18em]" style="color:#2F2E7C;">Next actions</div>
                        <div class="mt-4 grid gap-3 md:grid-cols-2">
                            <Link :href="nextActions.track || route('user.parcels.index')" class="rounded-[22px] border px-5 py-4 transition hover:-translate-y-0.5" style="border-color: rgba(47,46,124,0.12); background:#2F2E7C; color:#FFFFFF; box-shadow:0 18px 30px rgba(47,46,124,0.16);">
                                <div class="text-base font-black">Track Parcel</div>
                                <div class="mt-1 text-sm text-white/72">Open your tracking hub and follow the request from booking to delivery.</div>
                            </Link>

                            <Link :href="nextActions.requests || route('user.parcels.index')" class="rounded-[22px] border px-5 py-4 transition hover:-translate-y-0.5" style="border-color: var(--app-border); background: var(--app-surface-soft); color: var(--app-text);">
                                <div class="text-base font-black app-title">View My Requests</div>
                                <div class="mt-1 text-sm app-muted">See this booking together with your active parcel requests.</div>
                            </Link>

                            <Link :href="nextActions.book_another || route('welcome')" class="rounded-[22px] border px-5 py-4 transition hover:-translate-y-0.5" style="border-color: var(--app-border); background: var(--app-surface-soft); color: var(--app-text);">
                                <div class="text-base font-black app-title">Book Another Delivery</div>
                                <div class="mt-1 text-sm app-muted">Start another request without leaving the marketplace.</div>
                            </Link>

                            <div class="rounded-[22px] border px-5 py-4" style="border-color: var(--app-border); background: var(--app-surface-soft);">
                                <div class="text-base font-black app-title">Contact Support</div>
                                <div class="mt-1 text-sm app-muted">Support can help with payment setup, handoff notes, or route questions.</div>
                            </div>
                        </div>
                    </div>
                </div>

                <aside class="space-y-6">
                    <StickyBookingSummary title="Booking details" :items="sidebarSummaryItems" :badges="sidebarBadges">
                        <div class="mt-4">
                            <PaymentStatusCard
                                :booking-reference="activeParcel?.booking_reference || activeParcel?.tracking_number"
                                :tracking-number="activeParcel?.tracking_number"
                                :payment-status="activeParcel?.payment_status || 'ready'"
                                :booking-status="activeParcel?.booking_status || 'confirmed'"
                                :total="confirmedTotal"
                                next-step="Tracking ready"
                            />
                        </div>
                    </StickyBookingSummary>

                    <PriceBreakdown
                        :rows="confirmedBreakdownRows"
                        total-label="Confirmed total"
                        :total-value="confirmedTotal"
                        note="Payment integration is ready for mobile money, bank transfer, card, or manual collection."
                    />

                    <div class="app-panel rounded-[30px] p-6">
                        <div class="text-[11px] font-bold uppercase tracking-[0.18em]" style="color:#2F2E7C;">Payment options</div>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <span
                                v-for="method in paymentMethodLabels"
                                :key="method"
                                class="rounded-full border px-3 py-1.5 text-[11px] font-bold uppercase tracking-[0.14em]"
                                style="border-color: var(--app-border); background: var(--app-surface-soft); color: var(--app-text);"
                            >
                                {{ method }}
                            </span>
                        </div>
                    </div>
                </aside>
            </section>

            <div v-if="flowStage === 'confirm'" class="fixed inset-x-0 bottom-0 z-30 border-t px-4 py-3 sm:px-6 xl:hidden" style="border-color: var(--app-border); background: rgba(255,255,255,0.94); backdrop-filter: blur(18px);">
                <div class="flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <div class="text-[11px] font-bold uppercase tracking-[0.16em]" style="color:#2F2E7C;">Confirm booking</div>
                        <div class="mt-1 truncate text-sm font-black app-title">{{ selectedDriver?.name || "Selected driver" }} | {{ estimatedTotal }}</div>
                    </div>
                    <button type="button" class="app-primary-btn shrink-0" :disabled="confirmDisabled" @click="submitSelection">
                        {{ form.processing ? "Confirming..." : "Confirm" }}
                    </button>
                </div>
            </div>
        </div>
    </PublicLayout>
</template>
