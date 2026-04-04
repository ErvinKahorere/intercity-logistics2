<script setup>
import { computed, onMounted, ref } from "vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import PageHeader from "@/Components/AppShell/PageHeader.vue";
import api from "@/lib/api";
import { errorToast, successToast } from "@/composables/useAppToast";

const activeTab = ref("drivers");
const loading = ref(false);
const queueLoading = ref(false);
const routesLoading = ref(false);
const drivers = ref([]);
const verificationQueue = ref([]);
const routeMatrix = ref([]);

async function fetchDrivers() {
    loading.value = true;
    try {
        const { data } = await api.get(route("admin.drivers.list"));
        drivers.value = Array.isArray(data) ? data : [];
    } catch (error) {
        errorToast(error.response?.data?.message || "Could not load drivers.", "Load failed");
    } finally {
        loading.value = false;
    }
}

async function fetchVerificationQueue() {
    queueLoading.value = true;
    try {
        const { data } = await api.get(route("admin.drivers.verification-queue"));
        verificationQueue.value = Array.isArray(data?.items) ? data.items : [];
    } catch (error) {
        errorToast(error.response?.data?.message || "Could not load verification queue.", "Load failed");
    } finally {
        queueLoading.value = false;
    }
}

async function fetchRouteMatrix() {
    routesLoading.value = true;
    try {
        const { data } = await api.get(route("admin.drivers.routes.matrix"));
        routeMatrix.value = Array.isArray(data) ? data : [];
    } catch (error) {
        errorToast(error.response?.data?.message || "Could not load route matrix.", "Load failed");
    } finally {
        routesLoading.value = false;
    }
}

async function reviewLicence(id, status) {
    try {
        const payload = { status };
        if (status === "rejected") {
            payload.rejection_reason = "Licence details require correction before approval.";
        }

        await api.post(route("admin.drivers.verification-review", id), payload);
        successToast(`Licence ${status}.`, "Verification updated");
        fetchDrivers();
        fetchVerificationQueue();
    } catch (error) {
        errorToast(error.response?.data?.message || "Could not update verification.", "Review failed");
    }
}

const metrics = computed(() => [
    { label: "Drivers", value: drivers.value.length, meta: "Managed accounts" },
    { label: "Pending verification", value: verificationQueue.value.filter((item) => item.verification_status === "pending").length, meta: "Ready to review" },
    { label: "Verified", value: drivers.value.filter((item) => item.verification_status === "verified").length, meta: "Operationally cleared" },
    { label: "Route lanes", value: routeMatrix.value.length, meta: "Distance records" },
]);

onMounted(() => {
    fetchDrivers();
    fetchVerificationQueue();
    fetchRouteMatrix();
});
</script>

<template>
    <AuthenticatedLayout>
        <PageHeader eyebrow="Admin operations" title="Drivers & Compliance" description="Manage driver readiness, verification review, and route distance coverage.">
            <template #actions>
                <button type="button" class="app-outline-btn" @click="fetchDrivers(); fetchVerificationQueue(); fetchRouteMatrix();">Refresh</button>
            </template>
        </PageHeader>

        <div class="space-y-6">
            <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <article v-for="metric in metrics" :key="metric.label" class="app-panel rounded-[28px] p-5">
                    <div class="text-[11px] font-bold uppercase tracking-[0.18em] app-muted">{{ metric.label }}</div>
                    <div class="mt-3 text-3xl font-black app-title">{{ metric.value }}</div>
                    <div class="mt-2 text-sm app-muted">{{ metric.meta }}</div>
                </article>
            </section>

            <section class="app-panel rounded-[30px] p-5">
                <div class="flex flex-wrap gap-2">
                    <button type="button" class="rounded-full px-4 py-2 text-[11px] font-bold uppercase tracking-[0.16em]" :style="activeTab === 'drivers' ? 'background:#2F2E7C;color:#FFFFFF;' : 'background:var(--app-surface-soft);color:var(--app-text);'" @click="activeTab = 'drivers'">Driver list</button>
                    <button type="button" class="rounded-full px-4 py-2 text-[11px] font-bold uppercase tracking-[0.16em]" :style="activeTab === 'verification' ? 'background:#2F2E7C;color:#FFFFFF;' : 'background:var(--app-surface-soft);color:var(--app-text);'" @click="activeTab = 'verification'">Verification queue</button>
                    <button type="button" class="rounded-full px-4 py-2 text-[11px] font-bold uppercase tracking-[0.16em]" :style="activeTab === 'routes' ? 'background:#2F2E7C;color:#FFFFFF;' : 'background:var(--app-surface-soft);color:var(--app-text);'" @click="activeTab = 'routes'">Route matrix</button>
                </div>
            </section>

            <section v-if="activeTab === 'drivers'" class="app-panel rounded-[30px] p-6">
                <div class="text-[11px] font-bold uppercase tracking-[0.18em] app-muted">Operational driver list</div>
                <div class="mt-5 grid gap-4">
                    <div v-for="driver in drivers" :key="driver.id" class="rounded-[24px] border p-4" style="border-color: var(--app-border); background: var(--app-surface-soft);">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                            <div>
                                <div class="text-xl font-black app-title">{{ driver.name }}</div>
                                <div class="mt-1 text-sm app-muted">{{ driver.email }} | {{ driver.phone || "No phone" }}</div>
                                <div class="mt-3 flex flex-wrap gap-2">
                                    <span class="rounded-full px-3 py-1 text-[11px] font-bold uppercase tracking-[0.16em]" :style="driver.verification_status === 'verified' ? 'background:#E7F7EE;color:#156B45;' : driver.verification_status === 'pending' ? 'background:rgba(47,46,124,0.08);color:#2F2E7C;' : driver.verification_status === 'rejected' ? 'background:rgba(220,38,38,0.08);color:#b91c1c;' : 'background:var(--app-surface);color:var(--app-text);'">
                                        {{ driver.verification_status }}
                                    </span>
                                    <span class="rounded-full px-3 py-1 text-[11px] font-bold uppercase tracking-[0.16em]" style="background:var(--app-surface);color:var(--app-text);border:1px solid var(--app-border);">
                                        Banking: {{ driver.banking_status }}
                                    </span>
                                </div>
                            </div>
                            <div class="text-sm app-muted">
                                <div>Licence: {{ driver.licence_type || "Not submitted" }}</div>
                                <div v-if="driver.licence_expiry_date">Expiry: {{ driver.licence_expiry_date }}</div>
                                <div>Account: {{ driver.masked_account_number || "Not saved" }}</div>
                            </div>
                        </div>
                    </div>
                    <div v-if="!loading && !drivers.length" class="text-sm app-muted">No drivers found.</div>
                </div>
            </section>

            <section v-else-if="activeTab === 'verification'" class="app-panel rounded-[30px] p-6">
                <div class="text-[11px] font-bold uppercase tracking-[0.18em] app-muted">Verification queue</div>
                <div class="mt-5 grid gap-4">
                    <div v-for="item in verificationQueue" :key="item.id" class="rounded-[24px] border p-4" style="border-color: var(--app-border); background: var(--app-surface-soft);">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                            <div>
                                <div class="text-xl font-black app-title">{{ item.driver_name }}</div>
                                <div class="mt-1 text-sm app-muted">{{ item.driver_email }}</div>
                                <div class="mt-3 text-sm app-muted">{{ item.licence_type_name }} | {{ item.licence_number || "No licence number" }}</div>
                                <div class="mt-1 text-sm app-muted">Expiry {{ item.expiry_date }}</div>
                            </div>
                            <div class="flex flex-col items-start gap-3 lg:items-end">
                                <span class="rounded-full px-3 py-1 text-[11px] font-bold uppercase tracking-[0.16em]" :style="item.verification_status === 'verified' ? 'background:#E7F7EE;color:#156B45;' : item.verification_status === 'pending' ? 'background:rgba(47,46,124,0.08);color:#2F2E7C;' : 'background:rgba(220,38,38,0.08);color:#b91c1c;'">
                                    {{ item.verification_status }}
                                </span>
                                <div class="flex gap-2">
                                    <a v-if="item.document_url" :href="item.document_url" target="_blank" class="app-outline-btn !px-4 !py-3 !text-xs">Open Licence</a>
                                    <button type="button" class="app-primary-btn !px-4 !py-3 !text-xs" @click="reviewLicence(item.id, 'verified')">Approve</button>
                                    <button type="button" class="app-outline-btn !px-4 !py-3 !text-xs" @click="reviewLicence(item.id, 'rejected')">Reject</button>
                                </div>
                            </div>
                        </div>
                        <div v-if="item.rejection_reason" class="mt-4 text-sm" style="color:#b91c1c;">{{ item.rejection_reason }}</div>
                    </div>
                    <div v-if="!queueLoading && !verificationQueue.length" class="text-sm app-muted">No verification submissions yet.</div>
                </div>
            </section>

            <section v-else class="app-panel rounded-[30px] p-6">
                <div class="text-[11px] font-bold uppercase tracking-[0.18em] app-muted">Route distance matrix</div>
                <div class="mt-5 grid gap-4 md:grid-cols-2">
                    <div v-for="route in routeMatrix" :key="route.id" class="rounded-[24px] border p-4" style="border-color: var(--app-border); background: var(--app-surface-soft);">
                        <div class="text-lg font-black app-title">{{ route.origin_name }} -> {{ route.destination_name }}</div>
                        <div class="mt-3 grid gap-2 text-sm app-muted">
                            <div>Distance: {{ route.distance_km }} km</div>
                            <div>ETA: {{ route.estimated_hours }} hrs</div>
                            <div>Base fee: N$ {{ Number(route.base_fare || 0).toFixed(2) }}</div>
                            <div>Per km: N$ {{ Number(route.per_km_rate || 0).toFixed(2) }}</div>
                            <div>Minimum: N$ {{ Number(route.minimum_price || 0).toFixed(2) }}</div>
                            <div>Source: {{ route.distance_source }}</div>
                        </div>
                    </div>
                    <div v-if="!routesLoading && !routeMatrix.length" class="text-sm app-muted">No route distance records found.</div>
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
