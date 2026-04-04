<script setup>
import { computed, ref, watch } from "vue";
import { Head } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import PageHeader from "@/Components/AppShell/PageHeader.vue";
import StatusBadge from "@/Components/AppShell/StatusBadge.vue";
import api from "@/lib/api";
import { errorToast, successToast } from "@/composables/useAppToast";

const props = defineProps({ invoices: { type: Array, default: () => [] } });
const invoices = ref([...(props.invoices || [])]);
const activeStatus = ref("all");
const searchTerm = ref("");
const selectedId = ref(invoices.value[0]?.id || null);
const form = ref({ status: "", payment_status: "", notes: "" });
const saving = ref(false);

const filterOptions = ["all", "issued", "unpaid", "partially_paid", "paid", "cancelled", "pending", "manual", "failed"];

const filteredItems = computed(() => invoices.value.filter((item) => {
    const query = searchTerm.value.trim().toLowerCase();
    const searchMatch = !query || [item.invoice_number, item.customer?.name, item.customer?.email, item.route?.pickup, item.route?.dropoff, item.tracking_number, item.booking_reference].filter(Boolean).some((value) => String(value).toLowerCase().includes(query));
    const statusMatch = activeStatus.value === "all" || item.status === activeStatus.value || item.payment_status === activeStatus.value;
    return searchMatch && statusMatch;
}));

const selectedItem = computed(() => filteredItems.value.find((item) => item.id === selectedId.value) || filteredItems.value[0] || null);
const overdueCount = computed(() => invoices.value.filter((item) => item.overdue).length);
const unpaidCount = computed(() => invoices.value.filter((item) => item.payment_status !== "paid" && item.status !== "cancelled").length);
const paidCount = computed(() => invoices.value.filter((item) => item.payment_status === "paid").length);
const financeCards = computed(() => ([
    { label: "In view", value: filteredItems.value.length, meta: "Invoices after filters", tone: "brand" },
    { label: "Unpaid", value: unpaidCount.value, meta: "Needs collection or follow-up", tone: unpaidCount.value ? "warning" : "success" },
    { label: "Overdue", value: overdueCount.value, meta: "Past due date", tone: overdueCount.value ? "danger" : "success" },
    { label: "Paid", value: paidCount.value, meta: "Closed commercial records", tone: "success" },
]));
const orderedBreakdown = computed(() => {
    if (!selectedItem.value?.pricing_breakdown) return [];
    const map = selectedItem.value.pricing_breakdown;
    const labels = {
        base_fee: "Base fee",
        distance_fee: "Distance fee",
        weight_fee: "Weight fee",
        urgency_fee: "Urgency fee",
        special_handling_fee: "Special handling",
        total: "Invoice total",
    };

    return Object.entries(labels)
        .map(([key, label]) => ({ key, label, value: map[key] }))
        .filter((item) => item.value !== undefined && item.value !== null);
});
const invoiceWarnings = computed(() => {
    if (!selectedItem.value) return [];

    return [
        selectedItem.value.overdue
            ? { title: "Invoice overdue", text: "Payment is past the due date and should be escalated for commercial follow-up.", tone: "danger" }
            : null,
        selectedItem.value.payment_status === "failed"
            ? { title: "Payment failed", text: "The current payment state is failed. Review the notes and customer communication before retrying.", tone: "danger" }
            : null,
        selectedItem.value.payment_status === "manual"
            ? { title: "Manual payment tracking", text: "This invoice depends on an operational update to confirm settlement and supporting notes.", tone: "warning" }
            : null,
    ].filter(Boolean);
});

function syncForm() {
    if (!selectedItem.value) return;
    form.value = {
        status: selectedItem.value.status,
        payment_status: selectedItem.value.payment_status,
        notes: selectedItem.value.notes || "",
    };
}

function formatMoney(value) {
    return `N$ ${Number(value || 0).toFixed(2)}`;
}

function paymentTone(status, overdue = false) {
    if (status === "paid") return "success";
    if (overdue || status === "failed") return "danger";
    return "warning";
}

function invoiceTone(status) {
    if (status === "cancelled") return "danger";
    if (status === "paid") return "success";
    if (status === "partially_paid" || status === "unpaid") return "warning";
    return "brand";
}

async function saveInvoice() {
    if (!selectedItem.value) return;
    saving.value = true;
    try {
        const { data } = await api.put(route("admin.invoices.update", selectedItem.value.id), form.value);
        invoices.value = data.invoices || invoices.value;
        successToast(data.message || "Invoice updated.", "Invoice operations");
    } catch (error) {
        errorToast(error.response?.data?.message || "Could not update invoice.", "Update failed");
    } finally {
        saving.value = false;
    }
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
watch(selectedItem, () => syncForm(), { immediate: true });
</script>

<template>
    <Head title="Admin Invoices" />

    <AuthenticatedLayout>
        <PageHeader eyebrow="Admin invoices" title="Invoice Operations" description="Monitor billing health, payment exceptions, and commercial follow-up with clearer finance-first hierarchy." />

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <article v-for="card in financeCards" :key="card.label" class="app-panel rounded-[26px] p-5">
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

        <section class="grid gap-6 2xl:grid-cols-[390px_minmax(0,1fr)]">
            <article class="app-panel rounded-[30px] p-5 sm:p-6">
                <div class="rounded-[24px] border p-4" style="border-color: rgba(47,46,124,0.12); background: rgba(47,46,124,0.05);">
                    <div class="text-[11px] font-bold uppercase tracking-[0.16em] app-muted">Filter invoices</div>
                    <div class="mt-2 text-lg font-black app-title">Stay on top of cash collection</div>
                    <div class="mt-1 text-sm app-muted">Search by invoice number, route, customer, booking reference, or tracking number.</div>
                </div>

                <div class="mt-4 flex flex-wrap gap-2">
                    <button v-for="status in filterOptions" :key="status" type="button" class="rounded-full px-4 py-2 text-[11px] font-bold uppercase tracking-[0.16em]" :style="activeStatus === status ? 'background:#2F2E7C;color:#FFFFFF;' : 'background:var(--app-surface-soft);color:var(--app-text);border:1px solid var(--app-border);'" @click="activeStatus = status">{{ status.replaceAll('_', ' ') }}</button>
                </div>

                <input v-model="searchTerm" type="text" class="app-field mt-4" placeholder="Search invoice, customer, route, booking, or tracking" />

                <div class="mt-5 space-y-3 max-h-[72vh] overflow-y-auto pr-1">
                    <button v-for="item in filteredItems" :key="item.id" type="button" class="w-full rounded-[24px] border p-4 text-left transition" :style="selectedItem?.id === item.id ? 'border-color:#2F2E7C;background:rgba(47,46,124,0.06);' : 'border-color:var(--app-border);background:var(--app-surface-soft);'" @click="selectedId = item.id">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0 flex-1">
                                <div class="text-base font-black app-title">{{ item.invoice_number }}</div>
                                <div class="mt-1 text-sm app-muted">{{ item.customer?.name || 'Unknown customer' }} · {{ formatMoney(item.total) }}</div>
                                <div class="mt-2 text-xs app-muted">{{ item.route?.pickup }} -> {{ item.route?.dropoff }}</div>
                                <div class="mt-1 text-xs app-muted">Due {{ item.due_date || 'not set' }}</div>
                            </div>
                            <div class="space-y-2 text-right">
                                <StatusBadge :label="item.payment_status" :tone="paymentTone(item.payment_status, item.overdue)" small />
                                <div v-if="item.overdue" class="text-[11px] font-bold uppercase tracking-[0.16em]" style="color:#991b1b;">Overdue</div>
                            </div>
                        </div>
                    </button>

                    <div v-if="!filteredItems.length" class="rounded-[24px] border border-dashed p-5 text-sm app-muted" style="border-color: var(--app-border); background: var(--app-surface-soft);">
                        No invoices match the current filters.
                    </div>
                </div>
            </article>

            <article v-if="selectedItem" class="app-panel rounded-[30px] p-6 sm:p-7">
                <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                    <div>
                        <div class="text-[11px] font-bold uppercase tracking-[0.18em]" style="color:#2F2E7C;">Invoice detail</div>
                        <h2 class="mt-2 text-3xl font-black app-title sm:text-4xl">{{ selectedItem.invoice_number }}</h2>
                        <p class="mt-2 text-sm leading-6 app-muted">Issued {{ selectedItem.issue_date || 'not set' }} · Due {{ selectedItem.due_date || 'not set' }} · Driver {{ selectedItem.driver_name || 'Pending assignment' }}</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <StatusBadge :label="selectedItem.status" :tone="invoiceTone(selectedItem.status)" />
                        <StatusBadge :label="selectedItem.payment_status" :tone="paymentTone(selectedItem.payment_status, selectedItem.overdue)" />
                        <a :href="route('invoices.download', selectedItem.id)" class="app-outline-btn !px-4 !py-3 !text-xs">Download PDF</a>
                    </div>
                </div>

                <div class="mt-6 grid gap-4 lg:grid-cols-2 2xl:grid-cols-4">
                    <div class="rounded-[22px] p-4" style="background: var(--app-surface-soft);">
                        <div class="text-[11px] font-bold uppercase tracking-[0.16em] app-muted">Customer</div>
                        <div class="mt-2 text-base font-black app-title">{{ selectedItem.customer?.name || 'Unknown customer' }}</div>
                        <div class="mt-1 text-xs app-muted">{{ selectedItem.customer?.email || 'Email unavailable' }}</div>
                    </div>
                    <div class="rounded-[22px] p-4" style="background: var(--app-surface-soft);">
                        <div class="text-[11px] font-bold uppercase tracking-[0.16em] app-muted">Route</div>
                        <div class="mt-2 text-base font-black app-title">{{ selectedItem.route?.pickup }}</div>
                        <div class="mt-1 text-xs app-muted">Destination {{ selectedItem.route?.dropoff }}</div>
                        <div class="mt-1 text-xs app-muted">Tracking {{ selectedItem.tracking_number || 'not linked' }}</div>
                    </div>
                    <div class="rounded-[22px] p-4" style="background: var(--app-surface-soft);">
                        <div class="text-[11px] font-bold uppercase tracking-[0.16em] app-muted">References</div>
                        <div class="mt-2 text-base font-black app-title">{{ selectedItem.booking_reference || 'No booking ref' }}</div>
                        <div class="mt-1 text-xs app-muted">{{ selectedItem.driver_name || 'Pending driver assignment' }}</div>
                    </div>
                    <div class="rounded-[22px] p-4" :style="selectedItem.overdue ? 'background: rgba(31,31,31,0.92); color:#FFFFFF;' : 'background: rgba(47,46,124,0.05);'">
                        <div class="text-[11px] font-bold uppercase tracking-[0.16em]" :class="selectedItem.overdue ? 'text-white/70' : 'app-muted'">Commercial total</div>
                        <div class="mt-2 text-base font-black">{{ formatMoney(selectedItem.total) }}</div>
                        <div class="mt-1 text-xs" :class="selectedItem.overdue ? 'text-white/70' : 'app-muted'">{{ selectedItem.overdue ? 'Needs immediate attention' : 'Billing record in view' }}</div>
                    </div>
                </div>

                <div v-if="invoiceWarnings.length" class="mt-6 grid gap-3 lg:grid-cols-2">
                    <div v-for="warning in invoiceWarnings" :key="warning.title" class="rounded-[22px] border p-4" :style="warning.tone === 'danger' ? 'border-color:rgba(153,27,27,0.16); background:rgba(153,27,27,0.06);' : 'border-color:rgba(122,98,0,0.16); background:rgba(122,98,0,0.06);'">
                        <div class="text-sm font-black app-title">{{ warning.title }}</div>
                        <div class="mt-1 text-sm app-muted">{{ warning.text }}</div>
                    </div>
                </div>

                <div class="mt-6 grid gap-6 2xl:grid-cols-[minmax(0,1fr)_360px]">
                    <div class="space-y-6">
                        <div class="rounded-[24px] border p-5" style="border-color: var(--app-border); background: var(--app-surface-soft);">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <div class="text-[11px] font-bold uppercase tracking-[0.16em] app-muted">Pricing breakdown</div>
                                    <div class="mt-1 text-sm app-muted">Finance and operations can review the same structured charge lines.</div>
                                </div>
                                <StatusBadge :label="`${orderedBreakdown.length} lines`" tone="neutral" small />
                            </div>
                            <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                                <div v-for="item in orderedBreakdown" :key="item.key" class="rounded-[18px] p-3" style="background: rgba(255,255,255,0.72);">
                                    <div class="text-xs app-muted">{{ item.label }}</div>
                                    <div class="mt-1 text-sm font-black app-title">{{ formatMoney(item.value) }}</div>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-[24px] border p-5" style="border-color: var(--app-border); background: var(--app-surface-soft);">
                            <div class="text-[11px] font-bold uppercase tracking-[0.16em] app-muted">Operational note</div>
                            <div class="mt-3 text-sm leading-6 app-muted">{{ selectedItem.notes || 'No manual note has been added for this invoice yet.' }}</div>
                        </div>
                    </div>

                    <div class="rounded-[24px] border p-5" style="border-color: rgba(47,46,124,0.12); background: rgba(47,46,124,0.05);">
                        <div class="text-[11px] font-bold uppercase tracking-[0.16em] app-muted">Manual update</div>
                        <div class="mt-2 text-lg font-black app-title">Adjust status without leaving the page</div>
                        <div class="mt-1 text-sm app-muted">Use this for manual collections, exception handling, or finance notes.</div>

                        <div class="mt-4 grid gap-4">
                            <div>
                                <label class="text-[11px] font-bold uppercase tracking-[0.16em] app-muted">Invoice status</label>
                                <select v-model="form.status" class="app-field mt-2">
                                    <option value="issued">Issued</option>
                                    <option value="unpaid">Unpaid</option>
                                    <option value="partially_paid">Partially paid</option>
                                    <option value="paid">Paid</option>
                                    <option value="cancelled">Cancelled</option>
                                </select>
                            </div>
                            <div>
                                <label class="text-[11px] font-bold uppercase tracking-[0.16em] app-muted">Payment status</label>
                                <select v-model="form.payment_status" class="app-field mt-2">
                                    <option value="pending">Pending</option>
                                    <option value="manual">Manual</option>
                                    <option value="paid">Paid</option>
                                    <option value="failed">Failed</option>
                                </select>
                            </div>
                            <div>
                                <label class="text-[11px] font-bold uppercase tracking-[0.16em] app-muted">Finance note</label>
                                <textarea v-model="form.notes" rows="6" class="w-full rounded-[24px] border px-5 py-4 text-sm" style="border-color: var(--app-border); background: rgba(255,255,255,0.8);" placeholder="Add manual payment context, bank transfer confirmation, or cancellation notes"></textarea>
                            </div>
                            <button type="button" class="app-primary-btn" :disabled="saving" @click="saveInvoice">{{ saving ? 'Saving...' : 'Save Invoice Update' }}</button>
                        </div>
                    </div>
                </div>
            </article>
        </section>
    </AuthenticatedLayout>
</template>
