<script setup>
import { computed, ref, watch } from "vue";
import { Head } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import PageHeader from "@/Components/AppShell/PageHeader.vue";
import StatusBadge from "@/Components/AppShell/StatusBadge.vue";

const props = defineProps({ requests: { type: Array, default: () => [] } });
const requests = ref([...(props.requests || [])]);
const activeStatus = ref("all");
const searchTerm = ref("");
const selectedId = ref(requests.value[0]?.id || null);

const statusOptions = ["all", "pending", "matched", "accepted", "picked_up", "in_transit", "arrived", "delivered", "cancelled"];

const filteredItems = computed(() => requests.value.filter((item) => {
    const query = searchTerm.value.trim().toLowerCase();
    const searchMatch = !query || [item.tracking_number, item.customer_name, item.route, item.assigned_driver, item.package_type, item.quotation?.quotation_number, item.invoice?.invoice_number].filter(Boolean).some((value) => String(value).toLowerCase().includes(query));
    const statusMatch = activeStatus.value === "all" || item.status === activeStatus.value;
    return searchMatch && statusMatch;
}));

const selectedItem = computed(() => filteredItems.value.find((item) => item.id === selectedId.value) || filteredItems.value[0] || null);
const unassignedCount = computed(() => requests.value.filter((item) => !item.assigned_driver && !["delivered", "cancelled"].includes(item.status)).length);
const transitCount = computed(() => requests.value.filter((item) => ["accepted", "picked_up", "in_transit", "arrived"].includes(item.status)).length);
const docsMissingCount = computed(() => requests.value.filter((item) => !item.quotation || !item.invoice).length);
const summaryCards = computed(() => ([
    { label: "In view", value: filteredItems.value.length, meta: "Requests after filters", tone: "brand" },
    { label: "Active deliveries", value: transitCount.value, meta: "Currently in execution", tone: transitCount.value ? "warning" : "neutral" },
    { label: "Awaiting driver", value: unassignedCount.value, meta: "No driver attached yet", tone: unassignedCount.value ? "warning" : "success" },
    { label: "Docs missing", value: docsMissingCount.value, meta: "Quote or invoice not present", tone: docsMissingCount.value ? "danger" : "success" },
]));
const requestWarnings = computed(() => {
    if (!selectedItem.value) return [];

    return [
        !selectedItem.value.assigned_driver && !["delivered", "cancelled"].includes(selectedItem.value.status)
            ? { title: "Driver assignment required", text: "This request is still operationally open without a confirmed driver.", tone: "warning" }
            : null,
        !selectedItem.value.quotation
            ? { title: "Quotation missing", text: "Commercial estimation is not yet linked to this request.", tone: "danger" }
            : null,
        !selectedItem.value.invoice && ["accepted", "picked_up", "in_transit", "arrived", "delivered"].includes(selectedItem.value.status)
            ? { title: "Invoice missing", text: "A fulfilment-stage request should normally already have an invoice generated or queued.", tone: "warning" }
            : null,
    ].filter(Boolean);
});
const pricingEntries = computed(() => {
    if (!selectedItem.value?.pricing_summary) return [];
    const labels = {
        base_price: "Base fee",
        distance_fee: "Distance fee",
        weight_surcharge: "Weight surcharge",
        urgency_surcharge: "Urgency surcharge",
        special_handling_fee: "Special handling",
        total_price: "Total price",
    };

    return Object.entries(labels).map(([key, label]) => ({
        key,
        label,
        value: selectedItem.value?.pricing_summary?.[key] ?? 0,
    }));
});

function formatMoney(value) {
    return `N$ ${Number(value || 0).toFixed(2)}`;
}

function requestTone(status) {
    if (status === "delivered") return "success";
    if (status === "cancelled") return "danger";
    if (["in_transit", "arrived", "picked_up"].includes(status)) return "warning";
    return "brand";
}

watch(filteredItems, (value) => {
    if (!value.length) {
        selectedId.value = null;
        return;
    }
    if (!value.some((item) => item.id === selectedId.value)) {
        selectedId.value = value[0].id;
    }
}, { immediate: true });
</script>

<template>
    <Head title="Admin Requests" />

    <AuthenticatedLayout>
        <PageHeader eyebrow="Admin requests" title="Request and Delivery Oversight" description="Inspect request health, document coverage, assignment state, and recent delivery activity from a cleaner operations view." />

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <article v-for="card in summaryCards" :key="card.label" class="app-panel rounded-[26px] p-5">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <div class="text-[11px] font-bold uppercase tracking-[0.16em] app-muted">{{ card.label }}</div>
                        <div class="mt-2 text-3xl font-black app-title">{{ card.value }}</div>
                        <div class="mt-2 text-sm app-muted">{{ card.meta }}</div>
                    </div>
                    <StatusBadge :label="card.tone" :tone="card.tone" small />
                </div>
            </article>
        </section>

        <section class="grid gap-6 2xl:grid-cols-[400px_minmax(0,1fr)]">
            <article class="app-panel rounded-[30px] p-5 sm:p-6">
                <div class="rounded-[24px] border p-4" style="border-color: rgba(47,46,124,0.12); background: rgba(47,46,124,0.05);">
                    <div class="text-[11px] font-bold uppercase tracking-[0.16em] app-muted">Filter requests</div>
                    <div class="mt-2 text-lg font-black app-title">Track the requests that need attention</div>
                    <div class="mt-1 text-sm app-muted">Search by tracking number, customer, driver, route, or document reference.</div>
                </div>

                <div class="mt-4 flex flex-wrap gap-2">
                    <button v-for="status in statusOptions" :key="status" type="button" class="rounded-full px-4 py-2 text-[11px] font-bold uppercase tracking-[0.16em]" :style="activeStatus === status ? 'background:#2F2E7C;color:#FFFFFF;' : 'background:var(--app-surface-soft);color:var(--app-text);border:1px solid var(--app-border);'" @click="activeStatus = status">{{ status.replaceAll('_', ' ') }}</button>
                </div>

                <input v-model="searchTerm" type="text" class="app-field mt-4" placeholder="Search tracking, customer, route, driver, or document" />

                <div class="mt-5 space-y-3 max-h-[72vh] overflow-y-auto pr-1">
                    <button v-for="item in filteredItems" :key="item.id" type="button" class="w-full rounded-[24px] border p-4 text-left transition" :style="selectedItem?.id === item.id ? 'border-color:#2F2E7C;background:rgba(47,46,124,0.06);' : 'border-color:var(--app-border);background:var(--app-surface-soft);'" @click="selectedId = item.id">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0 flex-1">
                                <div class="text-base font-black app-title">{{ item.tracking_number }}</div>
                                <div class="mt-1 text-sm app-muted">{{ item.customer_name || 'Unknown customer' }}</div>
                                <div class="mt-2 text-xs app-muted">{{ item.route }}</div>
                                <div class="mt-1 text-xs app-muted">Driver {{ item.assigned_driver || 'awaiting assignment' }}</div>
                            </div>
                            <StatusBadge :label="item.status" :tone="requestTone(item.status)" small />
                        </div>
                    </button>

                    <div v-if="!filteredItems.length" class="rounded-[24px] border border-dashed p-5 text-sm app-muted" style="border-color: var(--app-border); background: var(--app-surface-soft);">
                        No requests match the current filters.
                    </div>
                </div>
            </article>

            <article v-if="selectedItem" class="app-panel rounded-[30px] p-6 sm:p-7">
                <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                    <div>
                        <div class="text-[11px] font-bold uppercase tracking-[0.18em]" style="color:#2F2E7C;">Request detail</div>
                        <h2 class="mt-2 text-3xl font-black app-title sm:text-4xl">{{ selectedItem.tracking_number }}</h2>
                        <p class="mt-2 text-sm leading-6 app-muted">{{ selectedItem.customer_name || 'Unknown customer' }} · {{ selectedItem.route }} · {{ selectedItem.distance_km || 0 }} km</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <StatusBadge :label="selectedItem.status" :tone="requestTone(selectedItem.status)" />
                        <StatusBadge :label="selectedItem.assigned_driver || 'Awaiting driver'" :tone="selectedItem.assigned_driver ? 'success' : 'neutral'" />
                    </div>
                </div>

                <div class="mt-6 grid gap-4 lg:grid-cols-2 2xl:grid-cols-4">
                    <div class="rounded-[22px] p-4" style="background: var(--app-surface-soft);">
                        <div class="text-[11px] font-bold uppercase tracking-[0.16em] app-muted">Package</div>
                        <div class="mt-2 text-base font-black app-title">{{ selectedItem.package_type || 'Unknown parcel' }}</div>
                        <div class="mt-1 text-xs app-muted">Route distance {{ selectedItem.distance_km || 0 }} km</div>
                    </div>
                    <div class="rounded-[22px] p-4" style="background: var(--app-surface-soft);">
                        <div class="text-[11px] font-bold uppercase tracking-[0.16em] app-muted">Driver</div>
                        <div class="mt-2 text-base font-black app-title">{{ selectedItem.assigned_driver || 'Awaiting assignment' }}</div>
                        <div class="mt-1 text-xs app-muted">Status {{ selectedItem.status.replaceAll('_', ' ') }}</div>
                    </div>
                    <div class="rounded-[22px] p-4" style="background: var(--app-surface-soft);">
                        <div class="text-[11px] font-bold uppercase tracking-[0.16em] app-muted">Quotation</div>
                        <div class="mt-2 text-base font-black app-title">{{ selectedItem.quotation?.quotation_number || 'Not created' }}</div>
                        <div class="mt-1 text-xs app-muted">{{ selectedItem.quotation?.status || 'No quotation status' }}</div>
                    </div>
                    <div class="rounded-[22px] p-4" style="background: rgba(47,46,124,0.05);">
                        <div class="text-[11px] font-bold uppercase tracking-[0.16em] app-muted">Invoice</div>
                        <div class="mt-2 text-base font-black app-title">{{ selectedItem.invoice?.invoice_number || 'Not created' }}</div>
                        <div class="mt-1 text-xs app-muted">{{ selectedItem.invoice?.payment_status || selectedItem.invoice?.status || 'No billing status' }}</div>
                    </div>
                </div>

                <div v-if="requestWarnings.length" class="mt-6 grid gap-3 lg:grid-cols-2">
                    <div v-for="warning in requestWarnings" :key="warning.title" class="rounded-[22px] border p-4" :style="warning.tone === 'danger' ? 'border-color:rgba(153,27,27,0.16); background:rgba(153,27,27,0.06);' : 'border-color:rgba(122,98,0,0.16); background:rgba(122,98,0,0.06);'">
                        <div class="text-sm font-black app-title">{{ warning.title }}</div>
                        <div class="mt-1 text-sm app-muted">{{ warning.text }}</div>
                    </div>
                </div>

                <div class="mt-6 grid gap-6 2xl:grid-cols-[minmax(0,1fr)_320px]">
                    <div class="space-y-6">
                        <div class="rounded-[24px] border p-5" style="border-color: var(--app-border); background: var(--app-surface-soft);">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <div class="text-[11px] font-bold uppercase tracking-[0.16em] app-muted">Pricing summary</div>
                                    <div class="mt-1 text-sm app-muted">Commercial value lines tied to the request's latest operational pricing snapshot.</div>
                                </div>
                                <StatusBadge :label="`${pricingEntries.length} lines`" tone="neutral" small />
                            </div>
                            <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                                <div v-for="item in pricingEntries" :key="item.key" class="rounded-[18px] p-3" style="background: rgba(255,255,255,0.72);">
                                    <div class="text-xs app-muted">{{ item.label }}</div>
                                    <div class="mt-1 text-sm font-black app-title">{{ formatMoney(item.value) }}</div>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-[24px] border p-5" style="border-color: var(--app-border); background: var(--app-surface-soft);">
                            <div class="text-[11px] font-bold uppercase tracking-[0.16em] app-muted">Timeline</div>
                            <div class="mt-4 space-y-3">
                                <div v-for="entry in selectedItem.timeline || []" :key="entry.id" class="rounded-[18px] border px-4 py-3" style="border-color: rgba(31,31,31,0.06); background: rgba(255,255,255,0.75);">
                                    <div class="flex flex-col gap-1 sm:flex-row sm:items-start sm:justify-between">
                                        <div>
                                            <div class="text-sm font-black app-title">{{ entry.title }}</div>
                                            <div v-if="entry.message" class="mt-1 text-sm app-muted">{{ entry.message }}</div>
                                        </div>
                                        <div class="text-xs app-muted">{{ entry.status }} · {{ entry.time }}</div>
                                    </div>
                                </div>
                                <div v-if="!(selectedItem.timeline || []).length" class="rounded-[18px] border border-dashed px-4 py-4 text-sm app-muted" style="border-color: var(--app-border); background: rgba(255,255,255,0.72);">
                                    No recent timeline events are available for this request.
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-[24px] border p-5" style="border-color: rgba(47,46,124,0.12); background: rgba(47,46,124,0.05);">
                        <div class="text-[11px] font-bold uppercase tracking-[0.16em] app-muted">Document links</div>
                        <div class="mt-2 text-lg font-black app-title">Commercial handoff</div>
                        <div class="mt-1 text-sm app-muted">Open the latest quotation and invoice directly from the request context.</div>

                        <div class="mt-4 grid gap-3">
                            <a v-if="selectedItem.quotation" :href="route('quotations.download', selectedItem.quotation.id)" class="rounded-[20px] border px-4 py-4" style="border-color: var(--app-border); background: rgba(255,255,255,0.78); color: var(--app-text);">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <div class="text-sm font-black app-title">{{ selectedItem.quotation.quotation_number }}</div>
                                        <div class="mt-1 text-xs app-muted">Quotation PDF · {{ selectedItem.quotation.status }}</div>
                                    </div>
                                    <StatusBadge :label="selectedItem.quotation.status" :tone="selectedItem.quotation.status === 'converted' ? 'success' : selectedItem.quotation.status === 'expired' ? 'danger' : 'brand'" small />
                                </div>
                            </a>
                            <a v-if="selectedItem.invoice" :href="route('invoices.download', selectedItem.invoice.id)" class="rounded-[20px] border px-4 py-4" style="border-color: var(--app-border); background: rgba(255,255,255,0.78); color: var(--app-text);">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <div class="text-sm font-black app-title">{{ selectedItem.invoice.invoice_number }}</div>
                                        <div class="mt-1 text-xs app-muted">Invoice PDF · {{ selectedItem.invoice.payment_status || selectedItem.invoice.status }}</div>
                                    </div>
                                    <StatusBadge :label="selectedItem.invoice.payment_status || selectedItem.invoice.status" :tone="selectedItem.invoice.payment_status === 'paid' ? 'success' : 'warning'" small />
                                </div>
                            </a>
                            <div v-if="!selectedItem.quotation && !selectedItem.invoice" class="rounded-[20px] border border-dashed px-4 py-4 text-sm app-muted" style="border-color: var(--app-border); background: rgba(255,255,255,0.78);">
                                No quotation or invoice is linked yet.
                            </div>
                        </div>
                    </div>
                </div>
            </article>
        </section>
    </AuthenticatedLayout>
</template>
