<script setup>
import { computed, ref } from "vue";
import { Head, Link, usePage } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import PageHeader from "@/Components/AppShell/PageHeader.vue";
import EmptyState from "@/Components/AppShell/EmptyState.vue";
import DriverStatCard from "@/Components/DriverWorkspace/DriverStatCard.vue";
import DriverRequestCard from "@/Components/DriverWorkspace/DriverRequestCard.vue";
import DriverComplianceCard from "@/Components/DriverWorkspace/DriverComplianceCard.vue";
import DriverActivityTimeline from "@/Components/DriverWorkspace/DriverActivityTimeline.vue";
import DriverAvailabilityBadge from "@/Components/DriverWorkspace/DriverAvailabilityBadge.vue";
import DriverVerificationBadge from "@/Components/DriverWorkspace/DriverVerificationBadge.vue";
import ActiveDeliveryCard from "@/Components/DriverDashboard/ActiveDeliveryCard.vue";
import RequestDetailsDrawer from "@/Components/DriverDashboard/RequestDetailsDrawer.vue";
import api from "@/lib/api";
import { errorToast, successToast } from "@/composables/useAppToast";
import { emitAppRefresh, usePolling } from "@/composables/useLivePage";

const props = defineProps({
    user: { type: Object, default: () => ({}) },
    vehicle: { type: Object, default: () => ({}) },
    availableRequests: { type: Array, default: () => [] },
    activeDeliveries: { type: Array, default: () => [] },
    dashboardSummary: { type: Object, default: () => ({}) },
    profileSnapshot: { type: Object, default: () => ({}) },
    driverStatusPanel: { type: Object, default: () => ({}) },
    activityFeed: { type: Array, default: () => [] },
    workspaceHero: { type: Object, default: () => ({}) },
    earningsSummary: { type: Object, default: () => ({}) },
    complianceSummary: { type: Object, default: () => ({}) },
    quickActions: { type: Array, default: () => [] },
    marketSignals: { type: Object, default: () => ({}) },
});

const page = usePage();
const user = computed(() => props.user || page.props.auth?.user || {});

const vehicle = ref({ ...(props.vehicle || {}) });
const availableRequests = ref([...(props.availableRequests || [])]);
const activeDeliveries = ref([...(props.activeDeliveries || [])]);
const dashboardSummary = ref({ ...(props.dashboardSummary || {}) });
const profileSnapshot = ref({ ...(props.profileSnapshot || {}) });
const driverStatusPanel = ref({ ...(props.driverStatusPanel || {}) });
const activityFeed = ref([...(props.activityFeed || [])]);
const workspaceHero = ref({ ...(props.workspaceHero || {}) });
const earningsSummary = ref({ ...(props.earningsSummary || {}) });
const complianceSummary = ref({ ...(props.complianceSummary || {}) });
const marketSignals = ref({ ...(props.marketSignals || {}) });
const quickActions = ref([]);
const selectedRequest = ref(null);
const availabilityBusy = ref(false);
const actionBusy = ref({});
const syncBusy = ref(false);
const lastSyncedAt = ref("Just now");
const feedFocused = computed(() => selectedRequest.value?.id || null);
const filters = ref({
    quickFilter: "best_match",
    parcelType: "",
    urgency: "",
    sort: "best_match",
    query: "",
});

function normalizeQuickActions(items = []) {
    return (Array.isArray(items) ? items : [])
        .filter((item) => item && typeof item === "object")
        .map((item, index) => ({
            ...item,
            href: typeof item.href === "string" ? item.href.trim() : "",
            label: item.label || `Action ${index + 1}`,
        }));
}

quickActions.value = normalizeQuickActions(props.quickActions);

function stampSync() {
    lastSyncedAt.value = new Date().toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" });
}

function hydrateDashboard(payload = {}) {
    vehicle.value = { ...(payload.vehicle || {}) };
    availableRequests.value = Array.isArray(payload.availableRequests) ? payload.availableRequests : [];
    activeDeliveries.value = Array.isArray(payload.activeDeliveries) ? payload.activeDeliveries : [];
    dashboardSummary.value = { ...(payload.dashboardSummary || {}) };
    profileSnapshot.value = { ...(payload.profileSnapshot || {}) };
    driverStatusPanel.value = { ...(payload.driverStatusPanel || {}) };
    activityFeed.value = Array.isArray(payload.activityFeed) ? payload.activityFeed : [];
    workspaceHero.value = { ...(payload.workspaceHero || {}) };
    earningsSummary.value = { ...(payload.earningsSummary || {}) };
    complianceSummary.value = { ...(payload.complianceSummary || {}) };
    marketSignals.value = { ...(payload.marketSignals || {}) };
    quickActions.value = normalizeQuickActions(payload.quickActions);
    stampSync();
}

async function fetchDashboard({ silent = true } = {}) {
    if (syncBusy.value) return;
    syncBusy.value = true;

    try {
        const { data } = await api.get("/api/driver/dashboard");
        hydrateDashboard(data || {});
    } catch (error) {
        if (!silent) {
            errorToast(error.response?.data?.message || "Could not refresh the driver workspace.", "Sync failed");
        }
    } finally {
        syncBusy.value = false;
    }
}

usePolling(() => fetchDashboard(), 15000, { enabled: () => true });

async function toggleAvailability() {
    if (availabilityBusy.value) return;

    const nextAvailable = !vehicle.value.available;
    const previousVehicle = { ...vehicle.value };
    const previousSummary = { ...dashboardSummary.value };
    const previousPanel = { ...driverStatusPanel.value };
    availabilityBusy.value = true;

    vehicle.value = { ...vehicle.value, available: nextAvailable };
    dashboardSummary.value = { ...dashboardSummary.value, availability_label: nextAvailable ? "Online" : "Offline" };
    driverStatusPanel.value = { ...driverStatusPanel.value, available: nextAvailable, availability_label: nextAvailable ? "Online" : "Offline" };
    workspaceHero.value = { ...workspaceHero.value, status: nextAvailable ? "Online" : "Offline" };
    quickActions.value = quickActions.value.map((action) => action.action === "toggle_availability" ? { ...action, label: nextAvailable ? "Go Offline" : "Go Online" } : action);

    try {
        await api.post(route("driver.availability.update"), { available: nextAvailable });
        successToast(nextAvailable ? "You are now visible for new route matches." : "You are offline for new matching.", "Availability updated");
        emitAppRefresh({ only: ["appNotifications"] });
        fetchDashboard();
    } catch (error) {
        vehicle.value = previousVehicle;
        dashboardSummary.value = previousSummary;
        driverStatusPanel.value = previousPanel;
        workspaceHero.value = { ...workspaceHero.value, status: previousVehicle.available ? "Online" : "Offline" };
        quickActions.value = quickActions.value.map((action) => action.action === "toggle_availability" ? { ...action, label: previousVehicle.available ? "Go Offline" : "Go Online" } : action);
        errorToast(error.response?.data?.message || "Failed to update availability.", "Update failed");
    } finally {
        availabilityBusy.value = false;
    }
}

function nextStepsFor(status) {
    return {
        accepted: ["picked_up"],
        picked_up: ["in_transit"],
        in_transit: ["arrived"],
        arrived: ["delivered"],
    }[status] || [];
}

function updateDeliveryLocally(parcelId, patch) {
    activeDeliveries.value = activeDeliveries.value.map((delivery) => delivery.id === parcelId ? { ...delivery, ...patch } : delivery);
}

async function acceptParcel(parcelId) {
    if (actionBusy.value[`accept:${parcelId}`]) return;

    const request = availableRequests.value.find((item) => item.id === parcelId);
    const previousRequests = [...availableRequests.value];
    const previousActive = [...activeDeliveries.value];
    const previousSummary = { ...dashboardSummary.value };
    const previousEarnings = { ...earningsSummary.value };
    const previousSignals = { ...marketSignals.value };
    actionBusy.value = { ...actionBusy.value, [`accept:${parcelId}`]: true };
    availableRequests.value = availableRequests.value.filter((item) => item.id !== parcelId);
    selectedRequest.value = null;

    if (request) {
        activeDeliveries.value = [{
            ...request,
            status: "accepted",
            status_label: "Accepted",
            can_accept: false,
            next_steps: ["picked_up"],
            next_action: "picked_up",
            accepted_time: "just now",
            timeline: [{ id: `accepted-${request.id}`, title: "Accepted", status: "accepted", time: "just now" }],
        }, ...activeDeliveries.value];
        dashboardSummary.value = {
            ...dashboardSummary.value,
            available_requests: Math.max(0, Number(dashboardSummary.value.available_requests || 0) - 1),
            active_deliveries: Number(dashboardSummary.value.active_deliveries || 0) + 1,
        };
        earningsSummary.value = {
            ...earningsSummary.value,
            accepted_today: Number(earningsSummary.value.accepted_today || 0) + 1,
            active_jobs: Number(earningsSummary.value.active_jobs || 0) + 1,
        };
        marketSignals.value = {
            ...marketSignals.value,
            urgent_requests: ["express", "same_day"].includes(request.urgency_level) ? Math.max(0, Number(marketSignals.value.urgent_requests || 0) - 1) : Number(marketSignals.value.urgent_requests || 0),
            high_value_requests: Number(request.estimated_price || 0) >= 1200 ? Math.max(0, Number(marketSignals.value.high_value_requests || 0) - 1) : Number(marketSignals.value.high_value_requests || 0),
            active_deliveries: Number(marketSignals.value.active_deliveries || 0) + 1,
        };
    }

    try {
        await api.post(route("driver.parcels.accept", parcelId), {});
        successToast("Request accepted. It has moved into active deliveries.", "Job accepted");
        emitAppRefresh({ only: ["appNotifications"] });
        fetchDashboard();
    } catch (error) {
        availableRequests.value = previousRequests;
        activeDeliveries.value = previousActive;
        dashboardSummary.value = previousSummary;
        earningsSummary.value = previousEarnings;
        marketSignals.value = previousSignals;
        errorToast(error.response?.data?.message || "Could not accept this request.", "Accept failed");
    } finally {
        const next = { ...actionBusy.value };
        delete next[`accept:${parcelId}`];
        actionBusy.value = next;
    }
}

async function updateParcelStatus(parcelId, status) {
    if (actionBusy.value[`status:${parcelId}`]) return;

    const previousDeliveries = activeDeliveries.value.map((item) => ({ ...item }));
    const previousSummary = { ...dashboardSummary.value };
    const previousEarnings = { ...earningsSummary.value };
    const previousSignals = { ...marketSignals.value };
    actionBusy.value = { ...actionBusy.value, [`status:${parcelId}`]: true };

    updateDeliveryLocally(parcelId, {
        status,
        status_label: status.replaceAll("_", " "),
        next_steps: nextStepsFor(status),
        next_action: nextStepsFor(status)[0] ?? null,
        timeline: [
            { id: `local-${parcelId}-${status}`, title: status.replaceAll("_", " "), status, time: "just now" },
            ...((activeDeliveries.value.find((item) => item.id === parcelId)?.timeline) || []),
        ],
    });

    if (status === "delivered") {
        activeDeliveries.value = activeDeliveries.value.filter((item) => item.id !== parcelId);
        dashboardSummary.value = {
            ...dashboardSummary.value,
            active_deliveries: Math.max(0, Number(dashboardSummary.value.active_deliveries || 0) - 1),
            completed_today: Number(dashboardSummary.value.completed_today || 0) + 1,
        };
        earningsSummary.value = {
            ...earningsSummary.value,
            active_jobs: Math.max(0, Number(earningsSummary.value.active_jobs || 0) - 1),
            completed_today: Number(earningsSummary.value.completed_today || 0) + 1,
        };
        marketSignals.value = {
            ...marketSignals.value,
            active_deliveries: Math.max(0, Number(marketSignals.value.active_deliveries || 0) - 1),
        };
    }

    try {
        await api.post(route("driver.parcels.status", parcelId), { status });
        successToast(`Delivery marked ${status.replaceAll("_", " ")}.`, "Status updated");
        emitAppRefresh({ only: ["appNotifications"] });
        fetchDashboard();
    } catch (error) {
        activeDeliveries.value = previousDeliveries;
        dashboardSummary.value = previousSummary;
        earningsSummary.value = previousEarnings;
        marketSignals.value = previousSignals;
        errorToast(error.response?.data?.message || "Could not update delivery status.", "Update failed");
    } finally {
        const next = { ...actionBusy.value };
        delete next[`status:${parcelId}`];
        actionBusy.value = next;
    }
}

function matchesQuickFilter(parcel) {
    const quick = filters.value.quickFilter;
    const score = Number(parcel.match_context?.match_score || 0);

    return {
        best_match: score >= 85,
        high_value: Number(parcel.estimated_payout || 0) >= 950,
        urgent: ["express", "same_day"].includes(parcel.urgency_level),
        heavy: ["heavy", "oversized"].includes(parcel.load_size),
        all: true,
    }[quick] ?? true;
}

const filteredRequests = computed(() => {
    const term = filters.value.query.trim().toLowerCase();
    const next = availableRequests.value.filter((parcel) => {
        const matchesParcel = !filters.value.parcelType || String(parcel.package_type) === filters.value.parcelType;
        const matchesUrgency = !filters.value.urgency || parcel.urgency_level === filters.value.urgency;
        const matchesQuick = matchesQuickFilter(parcel);
        const matchesTerm = !term || [parcel.pickup_location, parcel.dropoff_location, parcel.package_type, parcel.tracking_number]
            .some((value) => String(value || "").toLowerCase().includes(term));

        return matchesParcel && matchesUrgency && matchesQuick && matchesTerm;
    });

    return next.sort((left, right) => {
        if (filters.value.sort === "payout") return Number(right.estimated_payout || 0) - Number(left.estimated_payout || 0);
        if (filters.value.sort === "urgency") return String(left.urgency_level).localeCompare(String(right.urgency_level));
        return Number(right.match_context?.match_score || 0) - Number(left.match_context?.match_score || 0);
    });
});

const linkQuickActions = computed(() => normalizeQuickActions(quickActions.value).filter((action) => action.href));
const instantQuickActions = computed(() => normalizeQuickActions(quickActions.value).filter((action) => !action.href));
const parcelTypes = computed(() => [...new Set(availableRequests.value.map((item) => item.package_type).filter(Boolean))]);
const requestStats = computed(() => [
    { label: "Matching requests", value: dashboardSummary.value.available_requests || 0, meta: `Synced ${lastSyncedAt.value}`, icon: "RQ", tone: "brand" },
    { label: "Active deliveries", value: dashboardSummary.value.active_deliveries || 0, meta: "Loads currently moving", icon: "DV", tone: "default" },
    { label: "Completed today", value: dashboardSummary.value.completed_today || 0, meta: "Drop-offs closed today", icon: "OK", tone: "success" },
    { label: "Today's value", value: `N$ ${Number(earningsSummary.value.today_value || 0).toFixed(2)}`, meta: "Current closed value", icon: "N$", tone: "accent" },
    { label: "Week value", value: `N$ ${Number(earningsSummary.value.week_value || 0).toFixed(2)}`, meta: `${earningsSummary.value.completed_today || 0} completed today`, icon: "WK", tone: "default" },
    { label: "Verification", value: String(driverStatusPanel.value.verification_status || "unverified").replace("_", " "), meta: driverStatusPanel.value.verification_expiry_state === "expiring" ? "Licence review needed soon" : "Compliance status", icon: "VR", tone: driverStatusPanel.value.verification_status === "verified" ? "success" : "warning" },
]);
</script>

<template>
    <AuthenticatedLayout :user="user">
        <Head title="Driver Dashboard" />

        <PageHeader eyebrow="Driver dashboard" :title="workspaceHero.driver_name || user.name || 'Driver workspace'" description="Run your route book, manage active jobs, and stay ready for the next match.">
            <template #actions>
                <button type="button" class="app-outline-btn" :disabled="syncBusy" @click="fetchDashboard({ silent: false })">{{ syncBusy ? "Syncing..." : "Sync now" }}</button>
                <button type="button" class="app-primary-btn" :disabled="availabilityBusy" @click="toggleAvailability">{{ availabilityBusy ? "Updating..." : vehicle.available ? "Go Offline" : "Go Online" }}</button>
            </template>
        </PageHeader>

        <div class="space-y-6">
            <section class="overflow-hidden rounded-[34px] border shadow-[0_24px_80px_rgba(15,23,42,0.08)]" style="border-color: rgba(242,201,0,0.35); background: linear-gradient(135deg, rgba(242,201,0,0.94), rgba(255,244,204,0.96) 52%, rgba(255,255,255,0.98) 100%);">
                <div class="grid gap-6 p-6 sm:p-8 xl:grid-cols-[1.2fr_0.8fr] xl:items-end">
                    <div>
                        <div class="flex flex-wrap gap-2">
                            <DriverAvailabilityBadge :status="vehicle.available ? 'Online' : 'Offline'" />
                            <DriverVerificationBadge :status="driverStatusPanel.verification_status" />
                        </div>
                        <h2 class="mt-4 text-4xl font-black" style="color: #1F1F1F;">Keep the best lanes moving.</h2>
                        <p class="mt-3 max-w-2xl text-base" style="color: rgba(31,31,31,0.72);">Your dashboard prioritises the next available loads, live delivery actions, and readiness items that affect payouts and trust.</p>

                        <div class="mt-6 grid gap-3 sm:grid-cols-3">
                            <div class="rounded-[24px] border px-4 py-4" style="border-color: rgba(31,31,31,0.08); background: rgba(255,255,255,0.72);">
                                <div class="text-[11px] font-bold uppercase tracking-[0.16em]" style="color: rgba(31,31,31,0.56);">Vehicle</div>
                                <div class="mt-2 text-sm font-black" style="color: #1F1F1F;">{{ workspaceHero.vehicle_label || profileSnapshot.vehicle || "Delivery vehicle" }}</div>
                            </div>
                            <div class="rounded-[24px] border px-4 py-4" style="border-color: rgba(31,31,31,0.08); background: rgba(255,255,255,0.72);">
                                <div class="text-[11px] font-bold uppercase tracking-[0.16em]" style="color: rgba(31,31,31,0.56);">Route summary</div>
                                <div class="mt-2 text-sm font-black" style="color: #1F1F1F;">{{ workspaceHero.route_summary || "Add routes" }}</div>
                            </div>
                            <div class="rounded-[24px] border px-4 py-4" style="border-color: rgba(31,31,31,0.08); background: rgba(255,255,255,0.72);">
                                <div class="text-[11px] font-bold uppercase tracking-[0.16em]" style="color: rgba(31,31,31,0.56);">Urgent opportunities</div>
                                <div class="mt-2 text-sm font-black" style="color: #1F1F1F;">{{ workspaceHero.urgent_opportunities || 0 }} live requests</div>
                            </div>
                        </div>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <article class="rounded-[26px] border px-4 py-4" style="border-color: rgba(255,255,255,0.12); background: rgba(255,255,255,0.74);">
                            <div class="text-[11px] font-bold uppercase tracking-[0.16em]" style="color: rgba(31,31,31,0.56);">Accepted jobs</div>
                            <div class="mt-2 text-3xl font-black" style="color: #1F1F1F;">{{ earningsSummary.accepted_today || 0 }}</div>
                            <div class="mt-2 text-sm" style="color: rgba(31,31,31,0.72);">Loads secured today</div>
                        </article>
                        <article class="rounded-[26px] border px-4 py-4" style="border-color: rgba(255,255,255,0.12); background: rgba(255,255,255,0.74);">
                            <div class="text-[11px] font-bold uppercase tracking-[0.16em]" style="color: rgba(31,31,31,0.56);">Live demand</div>
                            <div class="mt-2 text-3xl font-black" style="color: #1F1F1F;">{{ marketSignals.urgent_requests || 0 }}</div>
                            <div class="mt-2 text-sm" style="color: rgba(31,31,31,0.72);">Urgent requests on your lanes</div>
                        </article>
                        <article class="rounded-[26px] border px-4 py-4" style="border-color: rgba(255,255,255,0.12); background: rgba(255,255,255,0.74);">
                            <div class="text-[11px] font-bold uppercase tracking-[0.16em]" style="color: rgba(31,31,31,0.56);">High value loads</div>
                            <div class="mt-2 text-3xl font-black" style="color: #1F1F1F;">{{ marketSignals.high_value_requests || 0 }}</div>
                            <div class="mt-2 text-sm" style="color: rgba(31,31,31,0.72);">Requests worth prioritising</div>
                        </article>
                        <article class="rounded-[26px] border px-4 py-4" style="border-color: rgba(255,255,255,0.12); background: rgba(255,255,255,0.74);">
                            <div class="text-[11px] font-bold uppercase tracking-[0.16em]" style="color: rgba(31,31,31,0.56);">Availability</div>
                            <div class="mt-2 text-3xl font-black" style="color: #1F1F1F;">{{ dashboardSummary.availability_label || "Offline" }}</div>
                            <div class="mt-2 text-sm" style="color: rgba(31,31,31,0.72);">Turn on to keep the feed active</div>
                        </article>
                    </div>
                </div>
            </section>

            <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-6">
                <DriverStatCard v-for="card in requestStats" :key="card.label" :label="card.label" :value="card.value" :meta="card.meta" :icon="card.icon" :tone="card.tone" />
            </section>

            <section class="grid gap-6 2xl:grid-cols-[1.32fr_0.68fr]">
                <div class="space-y-6">
                    <section class="rounded-[32px] border p-5 shadow-[0_18px_48px_rgba(15,23,42,0.06)] sm:p-6" style="border-color: var(--app-border); background: linear-gradient(145deg, rgba(255,255,255,0.98), rgba(246,243,237,0.94));">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                            <div>
                                <p class="text-[11px] font-bold uppercase tracking-[0.18em] app-muted">Matching requests</p>
                                <h2 class="mt-2 text-3xl font-black app-title">Live route feed</h2>
                            </div>
                            <div class="text-sm app-muted">Updated {{ lastSyncedAt }}</div>
                        </div>

                        <div class="mt-5 grid gap-3 lg:grid-cols-[1fr_0.8fr_0.8fr_0.9fr]">
                            <input v-model="filters.query" type="text" class="w-full rounded-[20px] border px-4 py-3 text-sm" style="border-color: var(--app-border); background: rgba(255,255,255,0.86);" placeholder="Search route, parcel type, or tracking" />
                            <select v-model="filters.parcelType" class="w-full rounded-[20px] border px-4 py-3 text-sm" style="border-color: var(--app-border); background: rgba(255,255,255,0.86);">
                                <option value="">All parcel types</option>
                                <option v-for="parcelType in parcelTypes" :key="parcelType" :value="parcelType">{{ parcelType }}</option>
                            </select>
                            <select v-model="filters.urgency" class="w-full rounded-[20px] border px-4 py-3 text-sm" style="border-color: var(--app-border); background: rgba(255,255,255,0.86);">
                                <option value="">All urgency</option>
                                <option value="standard">Standard</option>
                                <option value="express">Express</option>
                                <option value="same_day">Same day</option>
                            </select>
                            <select v-model="filters.sort" class="w-full rounded-[20px] border px-4 py-3 text-sm" style="border-color: var(--app-border); background: rgba(255,255,255,0.86);">
                                <option value="best_match">Sort by best match</option>
                                <option value="payout">Sort by payout</option>
                                <option value="urgency">Sort by urgency</option>
                            </select>
                        </div>

                        <div class="mt-4 flex flex-wrap gap-2">
                            <button type="button" class="rounded-full border px-4 py-2 text-[11px] font-bold uppercase tracking-[0.16em]" :style="filters.quickFilter === 'best_match' ? 'border-color:#2F2E7C;background:#2F2E7C;color:#1F1F1F;' : 'border-color:var(--app-border);background:var(--app-surface-soft);color:var(--app-text);'" @click="filters.quickFilter = 'best_match'">Best match</button>
                            <button type="button" class="rounded-full border px-4 py-2 text-[11px] font-bold uppercase tracking-[0.16em]" :style="filters.quickFilter === 'urgent' ? 'border-color:#2F2E7C;background:#2F2E7C;color:#1F1F1F;' : 'border-color:var(--app-border);background:var(--app-surface-soft);color:var(--app-text);'" @click="filters.quickFilter = 'urgent'">Urgent</button>
                            <button type="button" class="rounded-full border px-4 py-2 text-[11px] font-bold uppercase tracking-[0.16em]" :style="filters.quickFilter === 'high_value' ? 'border-color:#2F2E7C;background:#2F2E7C;color:#1F1F1F;' : 'border-color:var(--app-border);background:var(--app-surface-soft);color:var(--app-text);'" @click="filters.quickFilter = 'high_value'">High payout</button>
                            <button type="button" class="rounded-full border px-4 py-2 text-[11px] font-bold uppercase tracking-[0.16em]" :style="filters.quickFilter === 'heavy' ? 'border-color:#2F2E7C;background:#2F2E7C;color:#1F1F1F;' : 'border-color:var(--app-border);background:var(--app-surface-soft);color:var(--app-text);'" @click="filters.quickFilter = 'heavy'">Heavy loads</button>
                            <button type="button" class="rounded-full border px-4 py-2 text-[11px] font-bold uppercase tracking-[0.16em]" :style="filters.quickFilter === 'all' ? 'border-color:#2F2E7C;background:#2F2E7C;color:#1F1F1F;' : 'border-color:var(--app-border);background:var(--app-surface-soft);color:var(--app-text);'" @click="filters.quickFilter = 'all'">All live</button>
                        </div>

                        <div class="mt-4 rounded-[24px] border px-4 py-4 text-sm app-muted" style="border-color: rgba(47,46,124,0.08); background: rgba(47,46,124,0.03);">
                            Use this board to rank work by route fit, payout, or urgency. Tap a card to focus it, then accept once the route and value make sense.
                        </div>

                        <div v-if="!vehicle.available" class="mt-6">
                            <EmptyState title="You are offline" description="Go online to receive high-fit requests and urgent route opportunities." icon="OF">
                                <template #action>
                                    <button type="button" class="app-primary-btn" :disabled="availabilityBusy" @click="toggleAvailability">{{ availabilityBusy ? "Updating..." : "Go Online" }}</button>
                                </template>
                            </EmptyState>
                        </div>
                        <div v-else class="mt-6 grid gap-4 xl:grid-cols-2">
                            <DriverRequestCard v-for="parcel in filteredRequests" :key="parcel.id" :request="parcel" :busy="!!actionBusy[`accept:${parcel.id}`]" :selected="feedFocused === parcel.id" @view="selectedRequest = $event" @accept="acceptParcel" />
                        </div>

                        <EmptyState v-if="vehicle.available && !filteredRequests.length" class="mt-6" title="No matching requests" description="Stay online or widen your route fit to unlock more work." icon="RQ" />
                    </section>

                    <section class="rounded-[32px] border p-5 shadow-[0_18px_48px_rgba(15,23,42,0.06)] sm:p-6" style="border-color: var(--app-border); background: linear-gradient(145deg, rgba(255,255,255,0.98), rgba(246,243,237,0.94));">
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <p class="text-[11px] font-bold uppercase tracking-[0.18em] app-muted">Active deliveries</p>
                                <h2 class="mt-2 text-3xl font-black app-title">Jobs in motion</h2>
                            </div>
                            <div class="text-sm app-muted">Fast status updates keep tracking accurate.</div>
                        </div>

                        <div class="mt-4 rounded-[24px] border px-4 py-4 text-sm app-muted" style="border-color: rgba(47,46,124,0.08); background: rgba(47,46,124,0.03);">
                            Keep deliveries moving from this lane manager. Each card highlights current status, next action, and recent timeline updates without extra navigation.
                        </div>

                        <div class="mt-6 grid gap-4 xl:grid-cols-2">
                            <ActiveDeliveryCard v-for="delivery in activeDeliveries" :key="delivery.id" :delivery="delivery" :busy="!!actionBusy[`status:${delivery.id}`]" @advance="updateParcelStatus" />
                        </div>

                        <EmptyState v-if="!activeDeliveries.length" class="mt-6" title="No active deliveries" description="Accepted work appears here with the next action clearly highlighted." icon="DV" />
                    </section>
                </div>

                <div class="space-y-6">
                    <section class="grid gap-4 sm:grid-cols-2 2xl:grid-cols-1">
                        <DriverStatCard label="Today value" :value="`N$ ${Number(earningsSummary.today_value || 0).toFixed(2)}`" meta="Closed deliveries today" icon="TV" tone="accent" />
                        <DriverStatCard label="Week value" :value="`N$ ${Number(earningsSummary.week_value || 0).toFixed(2)}`" meta="Current week delivery value" icon="WK" tone="default" />
                    </section>

                    <DriverComplianceCard :compliance="complianceSummary" />
                    <DriverActivityTimeline title="Operational activity" :items="activityFeed" />

                    <section class="rounded-[30px] border p-5 shadow-[0_18px_48px_rgba(15,23,42,0.06)]" style="border-color: var(--app-border); background: linear-gradient(150deg, rgba(255,255,255,0.98), rgba(246,243,237,0.94));">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-[11px] font-bold uppercase tracking-[0.18em] app-muted">Driver profile</p>
                                <h3 class="mt-2 text-2xl font-black app-title">Trust and readiness</h3>
                            </div>
                            <Link :href="route('driver.profile')" class="app-outline-btn">Open</Link>
                        </div>

                        <div class="mt-5 rounded-[24px] border p-4" style="border-color: rgba(47,46,124,0.08); background: rgba(255,255,255,0.82);">
                            <div class="flex items-center gap-4">
                                <img v-if="profileSnapshot.avatar" :src="profileSnapshot.avatar" alt="Driver avatar" class="h-16 w-16 rounded-[22px] object-cover" />
                                <div v-else class="app-avatar h-16 w-16 rounded-[22px] text-lg">{{ (profileSnapshot.name || "D").slice(0, 1) }}</div>
                                <div class="min-w-0">
                                    <div class="text-lg font-black app-title">{{ profileSnapshot.name || user.name }}</div>
                                    <div class="mt-1 text-sm app-muted">{{ profileSnapshot.vehicle || "Vehicle profile pending" }}</div>
                                    <div class="mt-3 flex flex-wrap gap-2">
                                        <DriverVerificationBadge :status="driverStatusPanel.verification_status" small />
                                        <DriverAvailabilityBadge :status="vehicle.available ? 'Online' : 'Offline'" small />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 grid gap-3 sm:grid-cols-2">
                            <Link v-for="action in linkQuickActions" :key="action.label" :href="action.href" class="app-outline-btn w-full justify-between">
                                {{ action.label }}
                                <span class="text-xs app-muted">Open</span>
                            </Link>
                            <button v-for="action in instantQuickActions" :key="`${action.label}-button`" type="button" class="app-primary-btn w-full justify-between" @click="toggleAvailability">
                                {{ action.label }}
                                <span class="text-xs text-white/72">Live</span>
                            </button>
                        </div>
                    </section>
                </div>
            </section>
        </div>

        <RequestDetailsDrawer :open="!!selectedRequest" :request="selectedRequest" :busy="!!actionBusy[`accept:${selectedRequest?.id}`]" @close="selectedRequest = null" @accept="acceptParcel" />
    </AuthenticatedLayout>
</template>
