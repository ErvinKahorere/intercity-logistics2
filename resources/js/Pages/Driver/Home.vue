<script setup>
import { computed, ref } from "vue";
import { Head, Link, usePage } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import DriverStatCard from "@/Components/DriverWorkspace/DriverStatCard.vue";
import DriverRequestCard from "@/Components/DriverWorkspace/DriverRequestCard.vue";
import DriverComplianceCard from "@/Components/DriverWorkspace/DriverComplianceCard.vue";
import DriverActivityTimeline from "@/Components/DriverWorkspace/DriverActivityTimeline.vue";
import DriverAvailabilityBadge from "@/Components/DriverWorkspace/DriverAvailabilityBadge.vue";
import DriverVerificationBadge from "@/Components/DriverWorkspace/DriverVerificationBadge.vue";
import ActiveDeliveryCard from "@/Components/DriverDashboard/ActiveDeliveryCard.vue";
import RequestDetailsDrawer from "@/Components/DriverDashboard/RequestDetailsDrawer.vue";
import EmptyState from "@/Components/AppShell/EmptyState.vue";
import api from "@/lib/api";
import { emitAppRefresh, usePolling } from "@/composables/useLivePage";
import { errorToast, successToast } from "@/composables/useAppToast";

const props = defineProps({
    user: { type: Object, default: () => ({}) },
    vehicle: { type: Object, default: () => ({}) },
    availableRequests: { type: Array, default: () => [] },
    activeDeliveries: { type: Array, default: () => [] },
    activityFeed: { type: Array, default: () => [] },
    workspaceHero: { type: Object, default: () => ({}) },
    dashboardSummary: { type: Object, default: () => ({}) },
    earningsSummary: { type: Object, default: () => ({}) },
    complianceSummary: { type: Object, default: () => ({}) },
    quickActions: { type: Array, default: () => [] },
    marketSignals: { type: Object, default: () => ({}) },
    driverStatusPanel: { type: Object, default: () => ({}) },
});

const page = usePage();
const user = computed(() => props.user || page.props.auth?.user || {});

const vehicle = ref({ ...(props.vehicle || {}) });
const availableRequests = ref([...(props.availableRequests || [])]);
const activeDeliveries = ref([...(props.activeDeliveries || [])]);
const activityFeed = ref([...(props.activityFeed || [])]);
const workspaceHero = ref({ ...(props.workspaceHero || {}) });
const earningsSummary = ref({ ...(props.earningsSummary || {}) });
const complianceSummary = ref({ ...(props.complianceSummary || {}) });
const marketSignals = ref({ ...(props.marketSignals || {}) });
const driverStatusPanel = ref({ ...(props.driverStatusPanel || {}) });
const dashboardSummary = ref({ ...(props.dashboardSummary || {}) });
const quickActions = ref([]);
const selectedRequest = ref(null);
const syncBusy = ref(false);
const availabilityBusy = ref(false);
const actionBusy = ref({});
const homeReady = computed(() => Number(complianceSummary.value.score || 0) >= 80);

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

function hydrate(payload = {}) {
    vehicle.value = { ...(payload.vehicle || {}) };
    availableRequests.value = Array.isArray(payload.availableRequests) ? payload.availableRequests : [];
    activeDeliveries.value = Array.isArray(payload.activeDeliveries) ? payload.activeDeliveries : [];
    activityFeed.value = Array.isArray(payload.activityFeed) ? payload.activityFeed : [];
    workspaceHero.value = { ...(payload.workspaceHero || {}) };
    earningsSummary.value = { ...(payload.earningsSummary || {}) };
    complianceSummary.value = { ...(payload.complianceSummary || {}) };
    marketSignals.value = { ...(payload.marketSignals || {}) };
    driverStatusPanel.value = { ...(payload.driverStatusPanel || {}) };
    dashboardSummary.value = { ...(payload.dashboardSummary || {}) };
    quickActions.value = normalizeQuickActions(payload.quickActions);
}

async function fetchHome({ silent = true } = {}) {
    if (syncBusy.value) return;
    syncBusy.value = true;

    try {
        const { data } = await api.get("/api/driver/dashboard");
        hydrate(data || {});
    } catch (error) {
        if (!silent) {
            errorToast(error.response?.data?.message || "Could not refresh the driver home.", "Sync failed");
        }
    } finally {
        syncBusy.value = false;
    }
}

usePolling(() => fetchHome(), 15000, { enabled: () => true });

async function toggleAvailability() {
    if (availabilityBusy.value) return;

    const previous = vehicle.value.available;
    availabilityBusy.value = true;
    vehicle.value = { ...vehicle.value, available: !previous };
    workspaceHero.value = { ...workspaceHero.value, status: !previous ? "Online" : "Offline" };
    dashboardSummary.value = { ...dashboardSummary.value, availability_label: !previous ? "Online" : "Offline" };
    quickActions.value = quickActions.value.map((action) => action.action === "toggle_availability" ? { ...action, label: !previous ? "Go Offline" : "Go Online" } : action);

    try {
        await api.post(route("driver.availability.update"), { available: !previous });
        successToast(!previous ? "You are online for new delivery requests." : "You are offline for new matching.", "Availability updated");
        emitAppRefresh({ only: ["appNotifications"] });
        fetchHome();
    } catch (error) {
        vehicle.value = { ...vehicle.value, available: previous };
        workspaceHero.value = { ...workspaceHero.value, status: previous ? "Online" : "Offline" };
        dashboardSummary.value = { ...dashboardSummary.value, availability_label: previous ? "Online" : "Offline" };
        quickActions.value = quickActions.value.map((action) => action.action === "toggle_availability" ? { ...action, label: previous ? "Go Offline" : "Go Online" } : action);
        errorToast(error.response?.data?.message || "Failed to update availability.", "Update failed");
    } finally {
        availabilityBusy.value = false;
    }
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
        successToast("Request accepted and moved into your active jobs.", "Job accepted");
        emitAppRefresh({ only: ["appNotifications"] });
        fetchHome();
    } catch (error) {
        availableRequests.value = previousRequests;
        activeDeliveries.value = previousActive;
        dashboardSummary.value = previousSummary;
        earningsSummary.value = previousEarnings;
        marketSignals.value = previousSignals;
        errorToast(error.response?.data?.message || "Could not accept the request.", "Accept failed");
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

    activeDeliveries.value = activeDeliveries.value
        .map((delivery) => delivery.id === parcelId
            ? {
                ...delivery,
                status,
                status_label: status.replaceAll("_", " "),
                next_steps: {
                    accepted: ["picked_up"],
                    picked_up: ["in_transit"],
                    in_transit: ["arrived"],
                    arrived: ["delivered"],
                }[status] || [],
                next_action: ({
                    accepted: "picked_up",
                    picked_up: "in_transit",
                    in_transit: "arrived",
                    arrived: "delivered",
                })[status] || null,
                timeline: [{ id: `local-${parcelId}-${status}`, title: status.replaceAll("_", " "), status, time: "just now" }, ...(delivery.timeline || [])],
            }
            : delivery)
        .filter((delivery) => !(delivery.id === parcelId && status === "delivered"));

    if (status === "delivered") {
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
        fetchHome();
    } catch (error) {
        activeDeliveries.value = previousDeliveries;
        dashboardSummary.value = previousSummary;
        earningsSummary.value = previousEarnings;
        marketSignals.value = previousSignals;
        errorToast(error.response?.data?.message || "Could not update the delivery.", "Update failed");
    } finally {
        const next = { ...actionBusy.value };
        delete next[`status:${parcelId}`];
        actionBusy.value = next;
    }
}

const linkQuickActions = computed(() => normalizeQuickActions(quickActions.value).filter((action) => action.href));
const instantQuickActions = computed(() => normalizeQuickActions(quickActions.value).filter((action) => !action.href));

const homeStats = computed(() => [
    { label: "Matching requests", value: dashboardSummary.value.available_requests || 0, meta: "Requests fitting your current setup", icon: "RQ", tone: "brand" },
    { label: "Active deliveries", value: dashboardSummary.value.active_deliveries || 0, meta: "Jobs in motion right now", icon: "DV", tone: "default" },
    { label: "Completed today", value: dashboardSummary.value.completed_today || 0, meta: "Closed handovers logged today", icon: "OK", tone: "success" },
    { label: "Today value", value: `N$ ${Number(earningsSummary.value.today_value || 0).toFixed(2)}`, meta: "Value closed today", icon: "N$", tone: "accent" },
    { label: "Verification", value: String(driverStatusPanel.value.verification_status || "unverified").replace("_", " "), meta: driverStatusPanel.value.verification_expiry_state === "expiring" ? "Licence expiry approaching" : "Profile trust state", icon: "VR", tone: driverStatusPanel.value.verification_status === "verified" ? "success" : "warning" },
    { label: "Urgent opportunities", value: marketSignals.value.urgent_requests || 0, meta: "Fast-turn work on your lanes", icon: "UP", tone: "default" },
]);
</script>

<template>
    <AuthenticatedLayout :user="user">
        <Head title="Driver Home" />

        <div class="space-y-6">
            <section class="overflow-hidden rounded-[36px] border shadow-[0_24px_80px_rgba(15,23,42,0.08)]" style="border-color: rgba(242,201,0,0.35); background: linear-gradient(135deg, rgba(242,201,0,0.94), rgba(255,244,204,0.96) 52%, rgba(255,255,255,0.98) 100%);">
                <div class="grid gap-6 p-6 sm:p-8 xl:grid-cols-[1.18fr_0.82fr]">
                    <div>
                        <div class="flex flex-wrap gap-2">
                            <DriverAvailabilityBadge :status="vehicle.available ? 'Online' : 'Offline'" />
                            <DriverVerificationBadge :status="driverStatusPanel.verification_status" />
                        </div>
                        <h1 class="mt-4 text-4xl font-black" style="color: #1F1F1F;">{{ workspaceHero.driver_name || user.name || "Driver" }}</h1>
                        <p class="mt-2 text-base" style="color: rgba(31,31,31,0.72);">This is your operations surface: live requests, active deliveries, payout visibility, and compliance status in one place.</p>

                        <div class="mt-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                            <div class="rounded-[24px] border px-4 py-4" style="border-color: rgba(31,31,31,0.08); background: rgba(255,255,255,0.66);">
                                <div class="text-[11px] font-bold uppercase tracking-[0.16em]" style="color: rgba(31,31,31,0.56);">Vehicle</div>
                                <div class="mt-2 text-sm font-black" style="color: #1F1F1F;">{{ workspaceHero.vehicle_label || "Vehicle profile pending" }}</div>
                            </div>
                            <div class="rounded-[24px] border px-4 py-4" style="border-color: rgba(31,31,31,0.08); background: rgba(255,255,255,0.66);">
                                <div class="text-[11px] font-bold uppercase tracking-[0.16em]" style="color: rgba(31,31,31,0.56);">Route focus</div>
                                <div class="mt-2 text-sm font-black" style="color: #1F1F1F;">{{ workspaceHero.route_summary || "Configure routes" }}</div>
                            </div>
                            <div class="rounded-[24px] border px-4 py-4" style="border-color: rgba(31,31,31,0.08); background: rgba(255,255,255,0.66);">
                                <div class="text-[11px] font-bold uppercase tracking-[0.16em]" style="color: rgba(31,31,31,0.56);">Active routes</div>
                                <div class="mt-2 text-sm font-black" style="color: #1F1F1F;">{{ workspaceHero.active_routes_count || 0 }} lanes</div>
                            </div>
                            <div class="rounded-[24px] border px-4 py-4" style="border-color: rgba(31,31,31,0.08); background: rgba(255,255,255,0.66);">
                                <div class="text-[11px] font-bold uppercase tracking-[0.16em]" style="color: rgba(31,31,31,0.56);">Urgent work</div>
                                <div class="mt-2 text-sm font-black" style="color: #1F1F1F;">{{ workspaceHero.urgent_opportunities || 0 }} requests</div>
                            </div>
                        </div>

                        <div class="mt-6 flex flex-wrap gap-3">
                            <button type="button" class="app-primary-btn" :disabled="availabilityBusy" @click="toggleAvailability">{{ availabilityBusy ? "Updating..." : vehicle.available ? "Go Offline" : "Go Online" }}</button>
                            <Link :href="route('driver.dashboard')" class="app-outline-btn">Open full dashboard</Link>
                            <Link :href="route('driver.profile')" class="app-outline-btn">Profile and compliance</Link>
                        </div>

                        <div class="mt-6 flex flex-wrap gap-2">
                            <span class="rounded-full border px-3 py-1.5 text-[11px] font-bold uppercase tracking-[0.14em]" style="border-color: rgba(31,31,31,0.1); background: rgba(255,255,255,0.7); color:#1F1F1F;">
                                {{ homeReady ? "Payout ready" : "Action needed" }}
                            </span>
                            <span class="rounded-full border px-3 py-1.5 text-[11px] font-bold uppercase tracking-[0.14em]" style="border-color: rgba(31,31,31,0.1); background: rgba(255,255,255,0.7); color:#1F1F1F;">
                                {{ availableRequests.length }} live request{{ availableRequests.length === 1 ? "" : "s" }}
                            </span>
                            <span class="rounded-full border px-3 py-1.5 text-[11px] font-bold uppercase tracking-[0.14em]" style="border-color: rgba(31,31,31,0.1); background: rgba(255,255,255,0.7); color:#1F1F1F;">
                                {{ activeDeliveries.length }} active deliver{{ activeDeliveries.length === 1 ? "y" : "ies" }}
                            </span>
                        </div>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <DriverStatCard label="Today value" :value="`N$ ${Number(earningsSummary.today_value || 0).toFixed(2)}`" meta="Closed delivery value today" icon="TV" tone="accent" />
                        <DriverStatCard label="Week value" :value="`N$ ${Number(earningsSummary.week_value || 0).toFixed(2)}`" meta="Week-to-date route value" icon="WK" tone="default" />
                        <DriverStatCard label="Accepted jobs" :value="earningsSummary.accepted_today || 0" meta="Jobs secured today" icon="AC" tone="brand" />
                        <DriverStatCard label="Active jobs" :value="earningsSummary.active_jobs || 0" meta="Currently in motion" icon="DV" tone="default" />
                    </div>
                </div>
            </section>

            <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-6">
                <DriverStatCard v-for="card in homeStats" :key="card.label" :label="card.label" :value="card.value" :meta="card.meta" :icon="card.icon" :tone="card.tone" />
            </section>

            <section class="grid gap-6 2xl:grid-cols-[1.34fr_0.66fr]">
                <div class="space-y-6">
                    <section class="rounded-[32px] border p-5 shadow-[0_18px_48px_rgba(15,23,42,0.06)] sm:p-6" style="border-color: var(--app-border); background: linear-gradient(145deg, rgba(255,255,255,0.98), rgba(246,243,237,0.94));">
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <p class="text-[11px] font-bold uppercase tracking-[0.18em] app-muted">Live matching requests</p>
                                <h2 class="mt-2 text-3xl font-black app-title">Work ready to accept</h2>
                            </div>
                            <Link :href="route('driver.dashboard')" class="app-outline-btn">Advanced filters</Link>
                        </div>

                        <div class="mt-4 rounded-[24px] border px-4 py-4 text-sm app-muted" style="border-color: rgba(47,46,124,0.08); background: rgba(47,46,124,0.03);">
                            Prioritize high-fit requests here, then jump into the full dashboard when you need deeper filtering and feed management.
                        </div>

                        <div v-if="vehicle.available" class="mt-6 grid gap-4 xl:grid-cols-2">
                            <DriverRequestCard v-for="parcel in availableRequests.slice(0, 4)" :key="parcel.id" :request="parcel" :busy="!!actionBusy[`accept:${parcel.id}`]" :selected="selectedRequest?.id === parcel.id" @view="selectedRequest = $event" @accept="acceptParcel" />
                        </div>

                        <EmptyState v-if="!vehicle.available" class="mt-6" title="Go online to receive work" description="Your home feed activates when you are visible for matching." icon="OF" />
                        <EmptyState v-else-if="!availableRequests.length" class="mt-6" title="No requests matching right now" description="Your next opportunities appear here first." icon="RQ" />
                    </section>

                    <section class="rounded-[32px] border p-5 shadow-[0_18px_48px_rgba(15,23,42,0.06)] sm:p-6" style="border-color: var(--app-border); background: linear-gradient(145deg, rgba(255,255,255,0.98), rgba(246,243,237,0.94));">
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <p class="text-[11px] font-bold uppercase tracking-[0.18em] app-muted">Active deliveries</p>
                                <h2 class="mt-2 text-3xl font-black app-title">Current jobs</h2>
                            </div>
                            <div class="text-sm app-muted">Move jobs forward without leaving this screen.</div>
                        </div>

                        <div class="mt-6 grid gap-4 xl:grid-cols-2">
                            <ActiveDeliveryCard v-for="delivery in activeDeliveries.slice(0, 4)" :key="delivery.id" :delivery="delivery" :busy="!!actionBusy[`status:${delivery.id}`]" @advance="updateParcelStatus" />
                        </div>

                        <EmptyState v-if="!activeDeliveries.length" class="mt-6" title="No active jobs" description="Accepted requests will appear here with their next required action." icon="DV" />
                    </section>
                </div>

                <div class="space-y-6">
                    <DriverComplianceCard :compliance="complianceSummary" />
                    <DriverActivityTimeline title="Alerts and reminders" :items="activityFeed" />

                    <section class="rounded-[30px] border p-5 shadow-[0_18px_48px_rgba(15,23,42,0.06)]" style="border-color: var(--app-border); background: linear-gradient(150deg, rgba(255,255,255,0.98), rgba(246,243,237,0.94));">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-[11px] font-bold uppercase tracking-[0.18em] app-muted">Quick actions</p>
                                <h3 class="mt-2 text-2xl font-black app-title">Control center</h3>
                            </div>
                            <button type="button" class="app-outline-btn" :disabled="syncBusy" @click="fetchHome({ silent: false })">{{ syncBusy ? "Syncing..." : "Refresh" }}</button>
                        </div>

                        <div class="mt-5 grid gap-3">
                            <Link v-for="action in linkQuickActions" :key="action.label" :href="action.href" class="app-outline-btn w-full justify-between">
                                {{ action.label }}
                                <span class="text-xs app-muted">Open</span>
                            </Link>
                            <button v-for="action in instantQuickActions" :key="`${action.label}-action`" type="button" class="app-primary-btn w-full justify-between" @click="toggleAvailability">
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
