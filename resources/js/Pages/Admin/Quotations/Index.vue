<script setup>
import { computed, ref, watch } from "vue";
import { Head } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import PageHeader from "@/Components/AppShell/PageHeader.vue";
import StatusBadge from "@/Components/AppShell/StatusBadge.vue";

const props = defineProps({ quotations: { type: Array, default: () => [] } });
const quotations = ref([...(props.quotations || [])]);
const activeStatus = ref("all");
const searchTerm = ref("");
const selectedId = ref(quotations.value[0]?.id || null);

const statusOptions = ["all", "draft", "issued", "accepted", "expired", "converted"];

const filteredItems = computed(() => quotations.value.filter((item) => {
    const query = searchTerm.value.trim().toLowerCase();
    const searchMatch = !query || [item.quotation_number, item.customer?.name, item.customer?.email, item.route?.pickup, item.route?.dropoff, item.tracking_number].filter(Boolean).some((value) => String(value).toLowerCase().includes(query));
    const statusMatch = activeStatus.value === "all" || item.status === activeStatus.value;
    return searchMatch && statusMatch;
}));

const selectedItem = computed(() => filteredItems.value.find((item) => item.id === selectedId.value) || filteredItems.value[0] || null);
const activeQuoteCount = computed(() => quotations.value.filter((item) => ["draft", "issued", "accepted"].includes(item.status)).length);
const conversionCount = computed(() => quotations.value.filter((item) => item.status === "converted").length);
const expiredCount = computed(() => quotations.value.filter((item) => item.status === "expired").length);
const alertCount = computed(() => expiredCount.value + quotations.value.filter((item) => !item.tracking_number && item.status !== "draft").length);
const summaryCards = computed(() => ([
    { label: "In view", value: filteredItems.value.length, meta: "Documents after filters", tone: "brand" },
    { label: "Active quotes", value: activeQuoteCount.value, meta: "Still commercially open", tone: activeQuoteCount.value ? "warning" : "neutral" },
    { label: "Converted", value: conversionCount.value, meta: "Moved into booking flow", tone: "success" },
    { label: "Attention", value: alertCount.value, meta: "Expired or not linked", tone: alertCount.value ? "danger" : "success" },
]));
const breakdownItems = computed(() => {
    if (!selectedItem.value?.pricing_breakdown) return [];
    const map = selectedItem.value.pricing_breakdown;
    const labels = {
        distance_source: "Distance source",
        route_resolution: "Route resolution",
        base_fee: "Base fee",
        distance_fee: "Distance fee",
        weight_fee: "Weight fee",
        urgency_fee: "Urgency fee",
        special_handling_fee: "Special handling",
        minimum_charge: "Minimum charge",
        total: "Total estimate",
    };

    return Object.entries(labels)
        .map(([key, label]) => ({ key, label, value: map[key] }))
        .filter((item) => item.value !== undefined && item.value !== null);
});
const quoteWarnings = computed(() => {
    if (!selectedItem.value) return [];

    return [
        selectedItem.value.status === "expired"
            ? { title: "Quotation expired", text: "Commercial terms may need reissue before the customer can proceed.", tone: "danger" }
            : null,
        !selectedItem.value.tracking_number && selectedItem.value.status !== "draft"
            ? { title: "No linked request", text: "This quotation has not yet been tied to an active parcel request or booking record.", tone: "warning" }
            : null,
        selectedItem.value.distance_km <= 0
            ? { title: "Distance requires review", text: "This quotation is missing a valid route distance, which can affect pricing confidence.", tone: "warning" }
            : null,
    ].filter(Boolean);
});

function formatMoney(value) {
    return `N$ ${Number(value || 0).toFixed(2)}`;
}

function statusTone(status) {
    if (status === "converted") return "success";
    if (status === "expired") return "danger";
    if (status === "accepted") return "warning";
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
    <Head title="Admin Quotations" />

    <AuthenticatedLayout>
        <PageHeader eyebrow="Admin quotations" title="Quotation Operations" description="Review commercial estimates, pricing confidence, route detail, and conversion readiness from a cleaner operations workspace." />

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

        <section class="grid gap-6 2xl:grid-cols-[380px_minmax(0,1fr)]">
            <article class="app-panel rounded-[30px] p-5 sm:p-6">
                <div class="rounded-[24px] border p-4" style="border-color: rgba(47,46,124,0.12); background: rgba(47,46,124,0.05);">
                    <div class="text-[11px] font-bold uppercase tracking-[0.16em] app-muted">Filter quotations</div>
                    <div class="mt-2 text-lg font-black app-title">Find the right commercial document fast</div>
                    <div class="mt-1 text-sm app-muted">Search by reference, route, customer, or linked tracking number.</div>
                </div>

                <div class="mt-4 flex flex-wrap gap-2">
                    <button v-for="status in statusOptions" :key="status" type="button" class="rounded-full px-4 py-2 text-[11px] font-bold uppercase tracking-[0.16em]" :style="activeStatus === status ? 'background:#2F2E7C;color:#FFFFFF;' : 'background:var(--app-surface-soft);color:var(--app-text);border:1px solid var(--app-border);'" @click="activeStatus = status">{{ status.replaceAll('_', ' ') }}</button>
                </div>

                <input v-model="searchTerm" type="text" class="app-field mt-4" placeholder="Search quotation, customer, route, or tracking" />

                <div class="mt-5 space-y-3 max-h-[72vh] overflow-y-auto pr-1">
                    <button v-for="item in filteredItems" :key="item.id" type="button" class="w-full rounded-[24px] border p-4 text-left transition" :style="selectedItem?.id === item.id ? 'border-color:#2F2E7C;background:rgba(47,46,124,0.06);' : 'border-color:var(--app-border);background:var(--app-surface-soft);'" @click="selectedId = item.id">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0 flex-1">
                                <div class="text-base font-black app-title">{{ item.quotation_number }}</div>
                                <div class="mt-1 text-sm app-muted">{{ item.customer?.name || 'Unknown customer' }}</div>
                                <div class="mt-2 text-xs app-muted">{{ item.route?.pickup }} -> {{ item.route?.dropoff }}</div>
                                <div class="mt-1 text-xs app-muted">Issued {{ item.issue_date || 'not set' }}<span v-if="item.expires_at"> · Expires {{ item.expires_at }}</span></div>
                            </div>
                            <div class="space-y-2 text-right">
                                <StatusBadge :label="item.status" :tone="statusTone(item.status)" small />
                                <div class="text-xs font-semibold app-muted">{{ formatMoney(item.total) }}</div>
                            </div>
                        </div>
                    </button>

                    <div v-if="!filteredItems.length" class="rounded-[24px] border border-dashed p-5 text-sm app-muted" style="border-color: var(--app-border); background: var(--app-surface-soft);">
                        No quotations match the current filters.
                    </div>
                </div>
            </article>

            <article v-if="selectedItem" class="app-panel rounded-[30px] p-6 sm:p-7">
                <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                    <div>
                        <div class="text-[11px] font-bold uppercase tracking-[0.18em]" style="color:#2F2E7C;">Quotation detail</div>
                        <h2 class="mt-2 text-3xl font-black app-title sm:text-4xl">{{ selectedItem.quotation_number }}</h2>
                        <p class="mt-2 text-sm leading-6 app-muted">{{ selectedItem.customer?.name }} · {{ selectedItem.route?.pickup }} to {{ selectedItem.route?.dropoff }} · {{ selectedItem.distance_km || 0 }} km</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <StatusBadge :label="selectedItem.status" :tone="statusTone(selectedItem.status)" />
                        <a :href="route('quotations.download', selectedItem.id)" class="app-outline-btn !px-4 !py-3 !text-xs">Download PDF</a>
                    </div>
                </div>

                <div class="mt-6 grid gap-4 lg:grid-cols-2 2xl:grid-cols-4">
                    <div class="rounded-[22px] p-4" style="background: var(--app-surface-soft);">
                        <div class="text-[11px] font-bold uppercase tracking-[0.16em] app-muted">Customer</div>
                        <div class="mt-2 text-base font-black app-title">{{ selectedItem.customer?.name || 'Unknown customer' }}</div>
                        <div class="mt-1 text-xs app-muted">{{ selectedItem.customer?.email || 'Email unavailable' }}</div>
                        <div class="mt-1 text-xs app-muted">{{ selectedItem.customer?.phone || 'Phone unavailable' }}</div>
                    </div>
                    <div class="rounded-[22px] p-4" style="background: var(--app-surface-soft);">
                        <div class="text-[11px] font-bold uppercase tracking-[0.16em] app-muted">Route</div>
                        <div class="mt-2 text-base font-black app-title">{{ selectedItem.route?.pickup }}</div>
                        <div class="mt-1 text-xs app-muted">Destination {{ selectedItem.route?.dropoff }}</div>
                        <div class="mt-1 text-xs app-muted">Distance {{ selectedItem.distance_km || 0 }} km</div>
                    </div>
                    <div class="rounded-[22px] p-4" style="background: var(--app-surface-soft);">
                        <div class="text-[11px] font-bold uppercase tracking-[0.16em] app-muted">Parcel profile</div>
                        <div class="mt-2 text-base font-black app-title">{{ selectedItem.parcel?.type || 'Unknown parcel' }}</div>
                        <div class="mt-1 text-xs app-muted">{{ selectedItem.parcel?.weight_kg || 0 }} kg</div>
                        <div class="mt-1 text-xs app-muted">Urgency {{ selectedItem.parcel?.urgency || 'normal' }}</div>
                    </div>
                    <div class="rounded-[22px] p-4" style="background: rgba(47,46,124,0.05);">
                        <div class="text-[11px] font-bold uppercase tracking-[0.16em] app-muted">Commercial total</div>
                        <div class="mt-2 text-base font-black app-title">{{ formatMoney(selectedItem.total) }}</div>
                        <div class="mt-1 text-xs app-muted">Issue {{ selectedItem.issue_date || 'not set' }}</div>
                        <div class="mt-1 text-xs app-muted">Expiry {{ selectedItem.expires_at || 'not set' }}</div>
                    </div>
                </div>

                <div v-if="quoteWarnings.length" class="mt-6 grid gap-3 lg:grid-cols-2">
                    <div v-for="warning in quoteWarnings" :key="warning.title" class="rounded-[22px] border p-4" :style="warning.tone === 'danger' ? 'border-color:rgba(153,27,27,0.16); background:rgba(153,27,27,0.06);' : 'border-color:rgba(122,98,0,0.16); background:rgba(122,98,0,0.06);'">
                        <div class="text-sm font-black app-title">{{ warning.title }}</div>
                        <div class="mt-1 text-sm app-muted">{{ warning.text }}</div>
                    </div>
                </div>

                <div class="mt-6 grid gap-6 2xl:grid-cols-[minmax(0,1fr)_320px]">
                    <div class="rounded-[24px] border p-5" style="border-color: var(--app-border); background: var(--app-surface-soft);">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <div class="text-[11px] font-bold uppercase tracking-[0.16em] app-muted">Pricing breakdown</div>
                                <div class="mt-1 text-sm app-muted">The same pricing snapshot used during customer-facing quotation review.</div>
                            </div>
                            <StatusBadge :label="`${breakdownItems.length} lines`" tone="neutral" small />
                        </div>

                        <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                            <div v-for="item in breakdownItems" :key="item.key" class="rounded-[18px] p-3" style="background: rgba(255,255,255,0.72);">
                                <div class="text-xs app-muted">{{ item.label }}</div>
                                <div class="mt-1 text-sm font-black app-title">{{ typeof item.value === 'number' ? formatMoney(item.value) : item.value }}</div>
                            </div>
                        </div>
                    </div>

                    <div class="grid gap-4 content-start">
                        <div class="rounded-[24px] border p-5" style="border-color: rgba(47,46,124,0.12); background: rgba(47,46,124,0.05);">
                            <div class="text-[11px] font-bold uppercase tracking-[0.16em] app-muted">Linked request</div>
                            <div class="mt-3 text-lg font-black app-title">{{ selectedItem.tracking_number || 'Not linked yet' }}</div>
                            <div class="mt-2 text-sm leading-6 app-muted">Use this as the commercial handoff check before a quotation is accepted or converted.</div>
                        </div>
                        <div class="rounded-[24px] border p-5" style="border-color: var(--app-border); background: var(--app-surface-soft);">
                            <div class="text-[11px] font-bold uppercase tracking-[0.16em] app-muted">Readability cues</div>
                            <div class="mt-3 space-y-3 text-sm app-muted">
                                <div>Clear route, parcel, and pricing sections make PDF and on-screen reviews easier for operations and finance.</div>
                                <div>Status badges now highlight converted and expired documents more decisively.</div>
                            </div>
                        </div>
                    </div>
                </div>
            </article>
        </section>
    </AuthenticatedLayout>
</template>
