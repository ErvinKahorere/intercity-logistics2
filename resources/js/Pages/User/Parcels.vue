<script setup>
import { computed, ref } from "vue";
import { Head, Link } from "@inertiajs/vue3";
import { ArrowRight, ChevronDown, CircleDollarSign, RefreshCw, Route, Search, ShieldCheck, Truck, UserRound } from "lucide-vue-next";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import StatusBadge from "@/Components/AppShell/StatusBadge.vue";
import PageHeader from "@/Components/AppShell/PageHeader.vue";
import EmptyState from "@/Components/AppShell/EmptyState.vue";
import api from "@/lib/api";
import { usePolling } from "@/composables/useLivePage";
import { errorToast } from "@/composables/useAppToast";

const props = defineProps({ parcelRequests: { type: Array, default: () => [] } });
const parcelRequests = ref([...(props.parcelRequests || [])]);
const syncing = ref(false);
const syncedAt = ref("Just now");
const activeFilter = ref("all");
const searchTerm = ref("");
const expandedParcelId = ref(null);

const filters = [
    { id: "all", label: "All" },
    { id: "active", label: "Active" },
    { id: "awaiting", label: "Awaiting Driver" },
    { id: "payment", label: "Payment Ready" },
    { id: "transit", label: "In Transit" },
    { id: "done", label: "Delivered" },
];

function statusTone(status) {
    return { pending: "warning", matched: "brand", accepted: "success", picked_up: "warning", in_transit: "info", arrived: "brand", delivered: "success", cancelled: "error" }[status] || "neutral";
}

function paymentTone(status) {
    return { pending: "warning", ready: "brand", manual: "dark", paid: "success", failed: "error" }[status] || "neutral";
}
function documentTone(status) {
    return { draft: "neutral", issued: "brand", accepted: "warning", converted: "success", expired: "danger", unpaid: "warning", paid: "success", cancelled: "danger" }[status] || "neutral";
}

function matchTone(score) {
    if (score >= 90) return "success";
    if (score >= 80) return "brand";
    if (score >= 65) return "info";
    return "neutral";
}

function formatCurrency(value) {
    return `N$ ${Number(value || 0).toFixed(2)}`;
}

function formatLabel(value) {
    return String(value || "").replaceAll("_", " ").replace(/\b\w/g, (char) => char.toUpperCase());
}

function createInitials(name) {
    return String(name || "Driver").split(" ").filter(Boolean).slice(0, 2).map((part) => part[0]?.toUpperCase() || "").join("") || "D";
}

function isPaymentReady(parcel) {
    return ["ready", "manual", "paid"].includes(parcel.payment_status);
}

function isInTransit(parcel) {
    return ["picked_up", "in_transit", "arrived"].includes(parcel.status);
}

function passesFilter(parcel) {
    if (activeFilter.value === "all") return true;
    if (activeFilter.value === "active") return !["delivered", "cancelled"].includes(parcel.status);
    if (activeFilter.value === "awaiting") return ["pending", "matched"].includes(parcel.status);
    if (activeFilter.value === "payment") return isPaymentReady(parcel);
    if (activeFilter.value === "transit") return isInTransit(parcel);
    if (activeFilter.value === "done") return parcel.status === "delivered";
    return true;
}

function passesSearch(parcel) {
    const query = String(searchTerm.value || "").trim().toLowerCase();
    if (!query) return true;
    return [
        parcel.tracking_number,
        parcel.booking_reference,
        parcel.pickup_location?.name,
        parcel.dropoff_location?.name,
        parcel.receiver_name,
        parcel.package_type?.name,
        parcel.assigned_driver?.name,
        parcel.preferred_driver?.name,
    ].filter(Boolean).some((value) => String(value).toLowerCase().includes(query));
}

function toggleExpanded(id) {
    expandedParcelId.value = expandedParcelId.value === id ? null : id;
}

async function acceptQuote(parcel) {
    if (!parcel?.quotation?.id) return;

    try {
        const { data } = await api.post(route("quotations.accept", parcel.quotation.id), {});
        const invoice = data.invoice || null;
        const quotation = data.quotation || null;

        parcelRequests.value = parcelRequests.value.map((item) =>
            item.id === parcel.id
                ? { ...item, quotation, invoice }
                : item
        );

        refreshParcels();
    } catch (error) {
        errorToast(error.response?.data?.message || "Could not accept quotation.", "Action failed");
    }
}

async function refreshParcels({ silent = true } = {}) {
    if (syncing.value) return;
    syncing.value = true;
    try {
        const { data } = await api.get("/api/user/parcels");
        parcelRequests.value = Array.isArray(data?.parcelRequests) ? data.parcelRequests : [];
        syncedAt.value = new Date().toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" });
    } catch (error) {
        if (!silent) errorToast(error.response?.data?.message || "Could not refresh your parcels.", "Sync failed");
    } finally {
        syncing.value = false;
    }
}

usePolling(() => refreshParcels(), 15000);

const metrics = computed(() => [
    { label: "Active", value: parcelRequests.value.filter((parcel) => !["delivered", "cancelled"].includes(parcel.status)).length, meta: "Open shipments" },
    { label: "Payment Ready", value: parcelRequests.value.filter((parcel) => isPaymentReady(parcel)).length, meta: "Ready to pay" },
    { label: "In Transit", value: parcelRequests.value.filter((parcel) => isInTransit(parcel)).length, meta: "On the move" },
    { label: "Delivered", value: parcelRequests.value.filter((parcel) => parcel.status === "delivered").length, meta: "Completed" },
]);

const parcelCards = computed(() =>
    parcelRequests.value
        .filter((parcel) => passesFilter(parcel) && passesSearch(parcel))
        .map((parcel) => ({
            ...parcel,
            leadDriver: parcel.assigned_driver || parcel.preferred_driver || null,
            routeLabel: `${parcel.pickup_location?.name || "Pickup"} -> ${parcel.dropoff_location?.name || "Destination"}`,
            paymentLabel: formatLabel(parcel.payment_status || "pending"),
            bookingLabel: parcel.booking_status_label || formatLabel(parcel.booking_status || parcel.status_label || parcel.status),
            priceNow: parcel.final_price || parcel.estimated_total || parcel.total_price || 0,
            isExpanded: expandedParcelId.value === parcel.id,
            timelinePreview: (parcel.timeline || []).slice(0, 2),
            topMatch: (parcel.matched_drivers_preview || [])[0] || null,
            driverVerified: (parcel.assigned_driver || parcel.preferred_driver || null)?.verification_status === "verified",
        }))
);

const hasParcels = computed(() => parcelRequests.value.length > 0);
</script>

<template>
    <Head title="My Parcels" />

    <AuthenticatedLayout>
        <PageHeader eyebrow="Tracking hub" title="My Parcel Requests" description="Track booking progress, payment readiness, and live parcel movement in one place.">
            <template #actions>
                <button type="button" class="app-outline-btn" :disabled="syncing" @click="refreshParcels({ silent: false })">
                    <RefreshCw class="h-4 w-4" :class="syncing ? 'animate-spin' : ''" />
                    {{ syncing ? "Syncing..." : `Updated ${syncedAt}` }}
                </button>
                <Link :href="route('parcel-requests.create')" class="app-primary-btn">
                    New Request
                    <ArrowRight class="h-4 w-4" />
                </Link>
            </template>
        </PageHeader>

        <div class="grid gap-6">
            <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <article v-for="metric in metrics" :key="metric.label" class="app-panel rounded-[28px] p-5">
                    <div class="text-[11px] font-bold uppercase tracking-[0.18em] app-muted">{{ metric.label }}</div>
                    <div class="mt-3 text-3xl font-black app-title">{{ metric.value }}</div>
                    <div class="mt-2 text-sm app-muted">{{ metric.meta }}</div>
                </article>
            </section>

            <section class="app-panel rounded-[30px] p-5 sm:p-6">
                <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="filter in filters"
                            :key="filter.id"
                            type="button"
                            class="rounded-full px-4 py-2 text-[11px] font-bold uppercase tracking-[0.16em]"
                            :style="activeFilter === filter.id ? 'background:#2F2E7C;color:#FFFFFF;' : 'background:var(--app-surface-soft);color:var(--app-text);border:1px solid var(--app-border);'"
                            @click="activeFilter = filter.id"
                        >
                            {{ filter.label }}
                        </button>
                    </div>

                    <label class="relative block w-full xl:w-[22rem]">
                        <Search class="pointer-events-none absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 app-muted" />
                        <input v-model="searchTerm" type="text" class="app-field pl-11" placeholder="Search tracking, route, receiver, or driver" />
                    </label>
                </div>
            </section>

            <section v-if="parcelCards.length" class="grid gap-6">
                <article v-for="parcel in parcelCards" :key="parcel.id" class="app-panel overflow-hidden rounded-[32px]">
                    <div class="grid gap-6 p-5 sm:p-6 xl:grid-cols-[minmax(0,1.25fr)_340px]">
                        <div class="space-y-5">
                            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                <div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <StatusBadge :tone="statusTone(parcel.status)" :label="parcel.status_label" />
                                        <StatusBadge :tone="paymentTone(parcel.payment_status)" :label="parcel.paymentLabel" />
                                        <span v-if="parcel.booking_reference" class="rounded-full border px-3 py-1 text-[11px] font-bold uppercase tracking-[0.16em]" style="border-color: var(--app-border); background: var(--app-surface-soft); color: var(--app-text);">
                                            {{ parcel.booking_reference }}
                                        </span>
                                    </div>
                                    <div class="mt-3 text-[11px] font-bold uppercase tracking-[0.22em] app-muted">{{ parcel.tracking_number }}</div>
                                    <h2 class="mt-2 text-2xl font-black app-title sm:text-3xl">{{ parcel.routeLabel }}</h2>
                                    <p class="mt-2 text-sm app-muted">
                                        {{ parcel.package_type?.name }} | {{ parcel.load_size }} load | {{ parcel.urgency_level.replaceAll("_", " ") }}
                                        <span v-if="parcel.weight_kg"> | {{ parcel.weight_kg }} kg</span>
                                    </p>
                                </div>

                                <div class="rounded-[24px] border p-4 text-right" style="border-color: rgba(47,46,124,0.12); background: rgba(47,46,124,0.05);">
                                    <div class="text-[11px] font-bold uppercase tracking-[0.16em] app-muted">Current total</div>
                                    <div class="mt-2 text-2xl font-black app-title">{{ formatCurrency(parcel.priceNow) }}</div>
                                    <div class="mt-1 text-sm app-muted">{{ parcel.bookingLabel }}</div>
                                </div>
                            </div>

                            <div class="grid gap-3 md:grid-cols-4">
                                <div class="rounded-[22px] p-4" style="background: var(--app-surface-soft);">
                                    <div class="text-[11px] font-bold uppercase tracking-[0.16em] app-muted">Route</div>
                                    <div class="mt-2 text-base font-black app-title">{{ parcel.distance_km || 0 }} km</div>
                                    <div class="mt-1 text-sm app-muted">{{ parcel.estimated_hours || 0 }} hrs estimated</div>
                                </div>
                                <div class="rounded-[22px] p-4" style="background: var(--app-surface-soft);">
                                    <div class="text-[11px] font-bold uppercase tracking-[0.16em] app-muted">Estimate</div>
                                    <div class="mt-2 text-base font-black app-title">{{ formatCurrency(parcel.total_price) }}</div>
                                    <div class="mt-1 text-sm app-muted">Platform quote</div>
                                </div>
                                <div class="rounded-[22px] p-4" :style="parcel.client_offer_price ? 'background: rgba(242,201,0,0.18);' : 'background: var(--app-surface-soft);'">
                                    <div class="text-[11px] font-bold uppercase tracking-[0.16em] app-muted">Your offer</div>
                                    <div class="mt-2 text-base font-black app-title">{{ parcel.client_offer_price ? formatCurrency(parcel.client_offer_price) : "Not set" }}</div>
                                    <div class="mt-1 text-sm app-muted">Shared with drivers</div>
                                </div>
                                <div class="rounded-[22px] p-4" style="background: var(--app-surface-soft);">
                                    <div class="text-[11px] font-bold uppercase tracking-[0.16em] app-muted">Payment</div>
                                    <div class="mt-2 text-base font-black app-title">{{ parcel.paymentLabel }}</div>
                                    <div class="mt-1 text-sm app-muted">{{ parcel.bookingLabel }}</div>
                                </div>
                            </div>

                            <div class="grid gap-3 lg:grid-cols-3">
                                <div class="rounded-[24px] border p-4" style="border-color: var(--app-border); background: var(--app-surface-soft);">
                                    <div class="inline-flex items-center gap-2 text-[11px] font-bold uppercase tracking-[0.16em] app-muted">
                                        <UserRound class="h-4 w-4" />
                                        Receiver
                                    </div>
                                    <div class="mt-2 text-base font-black app-title">{{ parcel.receiver_name }}</div>
                                    <div class="mt-1 text-sm app-muted">{{ parcel.receiver_phone }}</div>
                                    <div v-if="parcel.dropoff_address" class="mt-2 text-sm app-muted">{{ parcel.dropoff_address }}</div>
                                </div>

                                <div class="rounded-[24px] border p-4" style="border-color: var(--app-border); background: var(--app-surface-soft);">
                                    <div class="inline-flex items-center gap-2 text-[11px] font-bold uppercase tracking-[0.16em] app-muted">
                                        <Truck class="h-4 w-4" />
                                        Driver
                                    </div>
                                    <div class="mt-3 flex items-center gap-3">
                                        <img v-if="parcel.leadDriver?.image" :src="parcel.leadDriver.image" :alt="parcel.leadDriver.name" class="h-14 w-14 rounded-[18px] object-cover" />
                                        <div v-else class="flex h-14 w-14 items-center justify-center rounded-[18px] text-sm font-black" style="background:#2F2E7C;color:#FFFFFF;">
                                            {{ createInitials(parcel.leadDriver?.name) }}
                                        </div>
                                        <div class="min-w-0">
                                            <div class="text-base font-black app-title">{{ parcel.leadDriver?.name || "Awaiting driver" }}</div>
                                            <div class="mt-1 text-sm app-muted">{{ parcel.assigned_driver?.vehicle?.vehicle_type || parcel.preferred_driver?.vehicle?.vehicle_type || "Vehicle pending" }}</div>
                                            <div class="mt-2">
                                                <StatusBadge :label="parcel.driverVerified ? 'Verified driver' : 'Verification pending'" :tone="parcel.driverVerified ? 'success' : 'neutral'" small />
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="rounded-[24px] border p-4" :style="isPaymentReady(parcel) ? 'border-color: rgba(47,46,124,0.16); background: rgba(47,46,124,0.05);' : 'border-color: var(--app-border); background: var(--app-surface-soft);'">
                                    <div class="inline-flex items-center gap-2 text-[11px] font-bold uppercase tracking-[0.16em] app-muted">
                                        <ShieldCheck class="h-4 w-4" />
                                        Payment step
                                    </div>
                                    <div class="mt-2 text-base font-black app-title">{{ parcel.paymentLabel }}</div>
                                    <div class="mt-1 text-sm app-muted">{{ isPaymentReady(parcel) ? "Booking is ready for payment." : "Payment opens once booking advances." }}</div>
                                    <Link v-if="parcel.payment_state" :href="route('parcel-requests.payment-ready', parcel.id)" class="mt-3 inline-flex items-center gap-2 text-sm font-bold uppercase tracking-[0.14em]" style="color:#2F2E7C;">
                                        Open payment-ready page
                                        <ArrowRight class="h-4 w-4" />
                                    </Link>
                                </div>
                            </div>

                            <div class="rounded-[24px] border p-4" style="border-color: var(--app-border);">
                                <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                                    <div>
                                        <div class="text-[11px] font-bold uppercase tracking-[0.16em] app-muted">Shipment activity</div>
                                        <div class="mt-1 text-sm app-muted">{{ parcel.matched_driver_count || 0 }} driver{{ (parcel.matched_driver_count || 0) === 1 ? "" : "s" }} alerted</div>
                                    </div>
                                    <button type="button" class="app-outline-btn" @click="toggleExpanded(parcel.id)">
                                        {{ parcel.isExpanded ? "Hide Details" : "View Details" }}
                                        <ChevronDown class="h-4 w-4 transition" :class="parcel.isExpanded ? 'rotate-180' : ''" />
                                    </button>
                                </div>

                                <div v-if="parcel.topMatch" class="mt-4 rounded-[22px] border p-4" style="border-color: var(--app-border); background: var(--app-surface-soft);">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <div class="text-[11px] font-bold uppercase tracking-[0.16em] app-muted">Top match</div>
                                            <div class="mt-2 text-base font-black app-title">{{ parcel.topMatch.name }}</div>
                                            <div class="mt-1 text-sm app-muted">{{ parcel.topMatch.route_summary || "Matched on your route" }}</div>
                                        </div>
                                        <StatusBadge :tone="matchTone(parcel.topMatch.score)" :label="`${parcel.topMatch.score}%`" small />
                                    </div>
                                </div>
                            </div>

                            <div v-if="parcel.isExpanded" class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_320px]">
                                <div class="space-y-4">
                                    <div class="rounded-[24px] border p-4" style="border-color: var(--app-border); background: var(--app-surface-soft);">
                                        <div class="text-[11px] font-bold uppercase tracking-[0.16em] app-muted">Price breakdown</div>
                                        <div class="mt-4 grid gap-3 md:grid-cols-2">
                                            <div class="rounded-[20px] px-4 py-3" style="background: rgba(255,255,255,0.86);"><div class="text-[10px] font-bold uppercase tracking-[0.16em] app-muted">Base route</div><div class="mt-2 text-sm font-black app-title">{{ formatCurrency(parcel.base_price) }}</div><div class="mt-1 text-xs app-muted">Booking setup and lane allocation</div></div>
                                            <div class="rounded-[20px] px-4 py-3" style="background: rgba(255,255,255,0.86);"><div class="text-[10px] font-bold uppercase tracking-[0.16em] app-muted">Distance</div><div class="mt-2 text-sm font-black app-title">{{ formatCurrency(parcel.distance_fee) }}</div><div class="mt-1 text-xs app-muted">{{ parcel.distance_km || 0 }} km route charge</div></div>
                                            <div class="rounded-[20px] px-4 py-3" style="background: rgba(255,255,255,0.86);"><div class="text-[10px] font-bold uppercase tracking-[0.16em] app-muted">Weight</div><div class="mt-2 text-sm font-black app-title">{{ formatCurrency(parcel.weight_surcharge) }}</div><div class="mt-1 text-xs app-muted">{{ parcel.weight_kg || 0 }} kg shipment</div></div>
                                            <div class="rounded-[20px] px-4 py-3" style="background: rgba(255,255,255,0.86);"><div class="text-[10px] font-bold uppercase tracking-[0.16em] app-muted">Urgency</div><div class="mt-2 text-sm font-black app-title">{{ formatCurrency(parcel.urgency_surcharge) }}</div><div class="mt-1 text-xs app-muted">{{ formatLabel(parcel.urgency_level) }}</div></div>
                                            <div class="rounded-[20px] px-4 py-3" style="background: rgba(255,255,255,0.86);"><div class="text-[10px] font-bold uppercase tracking-[0.16em] app-muted">Handling</div><div class="mt-2 text-sm font-black app-title">{{ formatCurrency(parcel.special_handling_fee) }}</div><div class="mt-1 text-xs app-muted">Notes and cargo handling adjustments</div></div>
                                            <div class="rounded-[20px] px-4 py-3" style="background: rgba(255,255,255,0.86);"><div class="text-[10px] font-bold uppercase tracking-[0.16em] app-muted">Confirmed</div><div class="mt-2 text-sm font-black app-title">{{ formatCurrency(parcel.priceNow) }}</div><div class="mt-1 text-xs app-muted">Current commercial total</div></div>
                                        </div>
                                    </div>

                                    <div class="rounded-[24px] border p-4" style="border-color: var(--app-border); background: var(--app-surface-soft);">
                                        <div class="text-[11px] font-bold uppercase tracking-[0.16em] app-muted">Matched drivers</div>
                                        <div v-if="(parcel.matched_drivers_preview || []).length" class="mt-4 grid gap-3 md:grid-cols-2">
                                            <Link v-for="driver in parcel.matched_drivers_preview" :key="driver.id" :href="route('driver.detail', driver.id)" class="rounded-[22px] border p-4" style="border-color: var(--app-border); background: rgba(255,255,255,0.86);">
                                                <div class="flex items-start justify-between gap-3">
                                                    <div><div class="text-base font-black app-title">{{ driver.name }}</div><div class="mt-1 text-sm app-muted">{{ driver.vehicle_type || "Driver profile" }}</div></div>
                                                    <StatusBadge :tone="matchTone(driver.score)" :label="`${driver.score}%`" small />
                                                </div>
                                                <div class="mt-3 text-sm app-muted">{{ driver.route_summary || "Matched on your route" }}</div>
                                            </Link>
                                        </div>
                                        <div v-else class="mt-4 rounded-[22px] p-4 text-sm app-muted" style="background: rgba(255,255,255,0.86);">
                                            Matching drivers will appear here once route scoring finishes for this request.
                                        </div>
                                    </div>
                                </div>

                                <div class="rounded-[26px] p-5 text-white" style="background: #2F2E7C;">
                                    <div class="text-[11px] font-bold uppercase tracking-[0.22em] text-[#f7df58]">Tracking timeline</div>
                                    <div class="mt-5 space-y-4">
                                        <div v-for="update in parcel.timeline || []" :key="update.id" class="rounded-[22px] p-4" style="background: rgba(255,255,255,0.08);">
                                            <div class="flex items-start justify-between gap-3">
                                                <div>
                                                    <div class="text-[10px] font-bold uppercase tracking-[0.16em] text-white/70">{{ update.status.replaceAll("_", " ") }}</div>
                                                    <div class="mt-1 text-base font-black">{{ update.title }}</div>
                                                    <p v-if="update.message" class="mt-1 text-sm leading-6 text-white/80">{{ update.message }}</p>
                                                </div>
                                                <div class="text-[10px] font-bold uppercase tracking-[0.16em] text-white/60">{{ update.time }}</div>
                                            </div>
                                        </div>
                                        <div v-if="!(parcel.timeline || []).length" class="rounded-[22px] p-4 text-sm text-white/80" style="background: rgba(255,255,255,0.08);">
                                            Tracking updates will appear here as your delivery moves forward.
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <aside class="rounded-[28px] border p-5" style="border-color: rgba(47,46,124,0.12); background: rgba(47,46,124,0.04);">
                            <div class="text-[11px] font-bold uppercase tracking-[0.18em]" style="color:#2F2E7C;">Shipment snapshot</div>
                            <div class="mt-4 space-y-3">
                                <div class="rounded-[20px] p-4" style="background: rgba(255,255,255,0.88);"><div class="text-[10px] font-bold uppercase tracking-[0.16em] app-muted">Booking</div><div class="mt-2 text-sm font-black app-title">{{ parcel.booking_reference || "Pending" }}</div><div class="mt-1 text-sm app-muted">{{ parcel.bookingLabel }}</div></div>
                                <div class="rounded-[20px] p-4" style="background: rgba(255,255,255,0.88);"><div class="text-[10px] font-bold uppercase tracking-[0.16em] app-muted">Tracking</div><div class="mt-2 text-sm font-black app-title">{{ parcel.tracking_number }}</div><div class="mt-1 text-sm app-muted">Live reference</div></div>
                                <div class="rounded-[20px] p-4" style="background: rgba(255,255,255,0.88);">
                                    <div class="text-[10px] font-bold uppercase tracking-[0.16em] app-muted">Documents</div>
                                    <div class="mt-3 space-y-3">
                                        <div v-if="parcel.quotation?.id" class="rounded-[16px] border px-3 py-3" style="border-color: var(--app-border); background: var(--app-surface-soft);">
                                            <div class="flex items-center justify-between gap-2">
                                                <a :href="route('quotations.download', parcel.quotation.id)" class="text-sm font-black" style="color:#2F2E7C;">{{ parcel.quotation.quotation_number }}</a>
                                                <StatusBadge :label="formatLabel(parcel.quotation.status)" :tone="documentTone(parcel.quotation.status)" small />
                                            </div>
                                            <button v-if="!parcel.invoice?.id" type="button" class="mt-2 text-left text-xs font-bold uppercase tracking-[0.14em]" style="color:#2F2E7C;" @click="acceptQuote(parcel)">Accept quote</button>
                                        </div>
                                        <div v-if="parcel.invoice?.id" class="rounded-[16px] border px-3 py-3" style="border-color: var(--app-border); background: var(--app-surface-soft);">
                                            <div class="flex items-center justify-between gap-2">
                                                <a :href="route('invoices.download', parcel.invoice.id)" class="text-sm font-black" style="color:#2F2E7C;">{{ parcel.invoice.invoice_number }}</a>
                                                <StatusBadge :label="formatLabel(parcel.invoice.payment_status || parcel.invoice.status)" :tone="documentTone(parcel.invoice.payment_status || parcel.invoice.status)" small />
                                            </div>
                                        </div>
                                        <div v-if="!parcel.quotation?.id && !parcel.invoice?.id" class="text-sm app-muted">Documents will appear here once quotation and billing are generated.</div>
                                    </div>
                                </div>
                                <div class="rounded-[20px] p-4" style="background: rgba(255,255,255,0.88);"><div class="text-[10px] font-bold uppercase tracking-[0.16em] app-muted">Driver</div><div class="mt-2 text-sm font-black app-title">{{ parcel.leadDriver?.name || "Awaiting assignment" }}</div><div class="mt-1 text-sm app-muted">{{ parcel.leadDriver?.route_summary || "Route details appear here once a driver is locked in." }}</div></div>
                                <div class="rounded-[20px] p-4" style="background: rgba(255,255,255,0.88);">
                                    <div class="text-[10px] font-bold uppercase tracking-[0.16em] app-muted">Recent activity</div>
                                    <div class="mt-3 space-y-3">
                                        <div v-for="update in parcel.timelinePreview" :key="update.id" class="rounded-[16px] border px-3 py-3" style="border-color: var(--app-border); background: var(--app-surface-soft);">
                                            <div class="text-[10px] font-bold uppercase tracking-[0.16em] app-muted">{{ update.status.replaceAll("_", " ") }}</div>
                                            <div class="mt-1 text-sm font-black app-title">{{ update.title }}</div>
                                            <div class="mt-1 text-xs app-muted">{{ update.time }}</div>
                                        </div>
                                        <div v-if="!parcel.timelinePreview.length" class="text-sm app-muted">Activity will appear here as the booking progresses.</div>
                                    </div>
                                </div>
                            </div>
                        </aside>
                    </div>
                </article>
            </section>

            <EmptyState v-else-if="!hasParcels" title="No active parcels" description="You have not created any parcel requests yet. Start a new request and your tracked deliveries will appear here." icon="PK">
                <template #action>
                    <Link :href="route('parcel-requests.create')" class="app-primary-btn">Create first request</Link>
                </template>
            </EmptyState>

            <EmptyState v-else title="No parcels match this view" description="Try a different filter or clear your search to bring your parcel requests back into view." icon="FL">
                <template #action>
                    <button type="button" class="app-primary-btn" @click="activeFilter = 'all'; searchTerm = ''">Reset filters</button>
                </template>
            </EmptyState>
        </div>
    </AuthenticatedLayout>
</template>
