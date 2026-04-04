<script setup>
import { computed, ref, watch } from "vue";
import { Head } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import PageHeader from "@/Components/AppShell/PageHeader.vue";
import StatusBadge from "@/Components/AppShell/StatusBadge.vue";
import api from "@/lib/api";
import { errorToast, successToast } from "@/composables/useAppToast";

const props = defineProps({
    drivers: { type: Array, default: () => [] },
    queue: { type: Array, default: () => [] },
});

const drivers = ref([...(props.drivers || [])]);
const queue = ref([...(props.queue || [])]);
const activeStatus = ref("all");
const searchTerm = ref("");
const selectedId = ref(queue.value[0]?.id || null);
const rejectionReason = ref("Licence details require correction before approval.");
const processing = ref(false);
const validationMessage = ref("");

const filteredQueue = computed(() =>
    queue.value.filter((item) => {
        const statusMatch = activeStatus.value === "all" || item.verification_status === activeStatus.value;
        const query = searchTerm.value.trim().toLowerCase();
        const searchMatch = !query || [item.driver_name, item.driver_email, item.licence_type_name, item.licence_number, item.vehicle_type]
            .filter(Boolean)
            .some((value) => String(value).toLowerCase().includes(query));
        return statusMatch && searchMatch;
    })
);

const selectedItem = computed(() => filteredQueue.value.find((item) => item.id === selectedId.value) || filteredQueue.value[0] || null);
const metrics = computed(() => [
    { label: "Pending", value: queue.value.filter((item) => item.verification_status === "pending").length, tone: "warning" },
    { label: "Verified", value: drivers.value.filter((item) => item.verification_status === "verified").length, tone: "success" },
    { label: "Rejected", value: queue.value.filter((item) => item.verification_status === "rejected").length, tone: "danger" },
    { label: "Expiring", value: queue.value.filter((item) => ["expiring", "expired"].includes(item.expiry_state)).length, tone: "warning" },
]);
const statusSummary = computed(() => {
    if (!selectedItem.value) return { title: "Select a driver", description: "Choose a queue item to review the submission details." };
    if (selectedItem.value.verification_status === "verified") return { title: "Driver verified", description: "Verification is complete and the driver now carries stronger trust cues." };
    if (selectedItem.value.verification_status === "rejected") return { title: "Resubmission required", description: "A corrected licence or metadata update is needed before verification can pass." };
    return { title: "Ready for review", description: "Check the document, expiry, banking readiness, and missing-field warnings before confirming the outcome." };
});
const canApprove = computed(() => !!selectedItem.value && selectedItem.value.expiry_state !== "expired" && !(selectedItem.value.missing_fields || []).length);

async function review(status) {
    if (!selectedItem.value || processing.value) return;
    processing.value = true;
    validationMessage.value = "";
    try {
        const { data } = await api.post(route("admin.verification.review", selectedItem.value.id), {
            status,
            rejection_reason: status === "rejected" ? rejectionReason.value : null,
        });
        drivers.value = data.drivers || drivers.value;
        queue.value = data.queue || queue.value;
        successToast(data.message || "Verification updated.", "Operations updated");
    } catch (error) {
        validationMessage.value = Object.values(error.response?.data?.errors || {}).flat()[0] || "";
        errorToast(validationMessage.value || error.response?.data?.message || "Could not update verification.", "Review failed");
    } finally {
        processing.value = false;
    }
}

watch(filteredQueue, (value) => {
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
    <Head title="Driver Verification" />

    <AuthenticatedLayout>
        <PageHeader eyebrow="Admin verification" title="Driver Verification Operations" description="Review licence submissions, banking readiness, expiry risk, and trusted-driver status from one operational queue." />

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <article v-for="item in metrics" :key="item.label" class="app-panel rounded-[28px] p-5">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <div class="text-[11px] font-bold uppercase tracking-[0.18em] app-muted">{{ item.label }}</div>
                        <div class="mt-3 text-3xl font-black app-title">{{ item.value }}</div>
                    </div>
                    <StatusBadge :label="item.label" :tone="item.tone" small />
                </div>
            </article>
        </section>

        <section class="grid gap-6 xl:grid-cols-[400px_minmax(0,1fr)]">
            <article class="app-panel rounded-[30px] p-5 sm:p-6">
                <div class="space-y-4">
                    <div class="rounded-[24px] border p-4" style="border-color: rgba(47,46,124,0.12); background: rgba(47,46,124,0.05);">
                        <div class="text-[11px] font-bold uppercase tracking-[0.16em] app-muted">Queue focus</div>
                        <div class="mt-2 text-base font-black app-title">{{ filteredQueue.length }} drivers in this view</div>
                        <div class="mt-1 text-sm app-muted">Use the filter chips to narrow the review set quickly.</div>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <button v-for="status in ['all', 'pending', 'verified', 'rejected', 'expired', 'unverified']" :key="status" type="button" class="rounded-full px-4 py-2 text-[11px] font-bold uppercase tracking-[0.16em]" :style="activeStatus === status ? 'background:#2F2E7C;color:#FFFFFF;' : 'background:var(--app-surface-soft);color:var(--app-text);border:1px solid var(--app-border);'" @click="activeStatus = status">
                            {{ status.replaceAll('_', ' ') }}
                        </button>
                    </div>
                    <input v-model="searchTerm" type="text" class="app-field" placeholder="Search driver, email, licence, or vehicle" />
                </div>

                <div class="mt-5 grid gap-3 max-h-[70vh] overflow-y-auto pr-1">
                    <button v-for="item in filteredQueue" :key="item.id" type="button" class="rounded-[24px] border p-4 text-left transition" :style="selectedItem?.id === item.id ? 'border-color:#2F2E7C;background:rgba(47,46,124,0.06);' : 'border-color:var(--app-border);background:var(--app-surface-soft);'" @click="selectedId = item.id">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="text-base font-black app-title">{{ item.driver_name }}</div>
                                <div class="mt-1 text-sm app-muted">{{ item.licence_type_name }} · {{ item.driver_email }}</div>
                                <div class="mt-2 flex flex-wrap gap-2">
                                    <span class="rounded-full px-3 py-1 text-[10px] font-bold uppercase tracking-[0.16em]" :style="item.expiry_state === 'expired' ? 'background:#1F1F1F;color:#FFFFFF;' : item.expiry_state === 'expiring' ? 'background:#F2C900;color:#1F1F1F;' : 'background:rgba(255,255,255,0.7);color:var(--app-text);border:1px solid var(--app-border);'">
                                        {{ item.expiry_state === 'expired' ? 'Expired' : item.expiry_state === 'expiring' ? 'Expiring soon' : 'Valid' }}
                                    </span>
                                    <span class="rounded-full px-3 py-1 text-[10px] font-bold uppercase tracking-[0.16em]" style="background:rgba(255,255,255,0.7);color:var(--app-text);border:1px solid var(--app-border);">
                                        Banking {{ item.banking_status }}
                                    </span>
                                </div>
                            </div>
                            <StatusBadge :label="item.verification_status" :tone="item.verification_status === 'verified' ? 'success' : item.verification_status === 'pending' ? 'warning' : item.verification_status === 'rejected' ? 'danger' : 'neutral'" small />
                        </div>
                    </button>

                    <div v-if="!filteredQueue.length" class="rounded-[24px] border p-4 text-sm app-muted" style="border-color: var(--app-border); background: var(--app-surface-soft);">
                        No verification records match this filter.
                    </div>
                </div>
            </article>

            <article class="app-panel rounded-[30px] p-6" v-if="selectedItem">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <div class="text-[11px] font-bold uppercase tracking-[0.18em]" style="color:#2F2E7C;">Verification detail</div>
                        <h2 class="mt-2 text-3xl font-black app-title">{{ selectedItem.driver_name }}</h2>
                        <p class="mt-2 text-sm leading-6 app-muted">{{ selectedItem.driver_email }} · {{ selectedItem.driver_phone || 'No phone recorded' }}</p>
                    </div>
                    <StatusBadge :label="selectedItem.verification_status" :tone="selectedItem.verification_status === 'verified' ? 'success' : selectedItem.verification_status === 'pending' ? 'warning' : selectedItem.verification_status === 'rejected' ? 'danger' : 'neutral'" />
                </div>

                <div class="mt-5 rounded-[24px] border p-5" style="border-color: rgba(47,46,124,0.12); background: rgba(47,46,124,0.05);">
                    <div class="text-base font-black app-title">{{ statusSummary.title }}</div>
                    <p class="mt-1 text-sm leading-6 app-muted">{{ statusSummary.description }}</p>
                </div>

                <div class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <div class="rounded-[22px] p-4" style="background: var(--app-surface-soft);"><div class="text-[11px] font-bold uppercase tracking-[0.16em] app-muted">Licence type</div><div class="mt-2 text-base font-black app-title">{{ selectedItem.licence_type_name }}</div></div>
                    <div class="rounded-[22px] p-4" style="background: var(--app-surface-soft);"><div class="text-[11px] font-bold uppercase tracking-[0.16em] app-muted">Licence number</div><div class="mt-2 text-base font-black app-title">{{ selectedItem.licence_number || 'Not provided' }}</div></div>
                    <div class="rounded-[22px] p-4" style="background: var(--app-surface-soft);"><div class="text-[11px] font-bold uppercase tracking-[0.16em] app-muted">Issue date</div><div class="mt-2 text-base font-black app-title">{{ selectedItem.issue_date || 'Not recorded' }}</div></div>
                    <div class="rounded-[22px] p-4" :style="selectedItem.expiry_state === 'expired' ? 'background: rgba(31,31,31,0.92); color:#FFFFFF;' : selectedItem.expiry_state === 'expiring' ? 'background: rgba(242,201,0,0.22);' : 'background: var(--app-surface-soft);'">
                        <div class="text-[11px] font-bold uppercase tracking-[0.16em]" :class="selectedItem.expiry_state === 'expired' ? 'text-white/70' : 'app-muted'">Expiry</div>
                        <div class="mt-2 text-base font-black">{{ selectedItem.expiry_date }}</div>
                        <div class="mt-1 text-xs" :class="selectedItem.expiry_state === 'expired' ? 'text-white/70' : 'app-muted'">{{ selectedItem.days_to_expiry }} day(s)</div>
                    </div>
                </div>

                <div class="mt-6 grid gap-6 2xl:grid-cols-[1.08fr_0.92fr]">
                    <div class="space-y-4">
                        <div class="rounded-[24px] border p-5" style="border-color: var(--app-border); background: var(--app-surface-soft);">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <div class="text-[11px] font-bold uppercase tracking-[0.16em] app-muted">Document</div>
                                    <div class="mt-2 text-base font-black app-title">Uploaded licence file</div>
                                </div>
                                <a v-if="selectedItem.document_url" :href="selectedItem.document_url" target="_blank" class="app-outline-btn !px-4 !py-3 !text-xs">Open document</a>
                            </div>
                            <div class="mt-4 text-sm leading-6 app-muted">Review the uploaded licence against the driver profile, expiry date, and route fit before updating status.</div>
                        </div>

                        <div class="rounded-[24px] border p-5" style="border-color: var(--app-border); background: var(--app-surface-soft);">
                            <div class="text-[11px] font-bold uppercase tracking-[0.16em] app-muted">Operational fit</div>
                            <div class="mt-4 grid gap-4 md:grid-cols-2">
                                <div><div class="text-xs font-bold uppercase tracking-[0.14em] app-muted">Vehicle</div><div class="mt-2 text-sm font-black app-title">{{ selectedItem.vehicle_type || 'Not captured yet' }}</div></div>
                                <div><div class="text-xs font-bold uppercase tracking-[0.14em] app-muted">Banking</div><div class="mt-2 text-sm font-black app-title">{{ selectedItem.banking_status }}</div><div class="mt-1 text-xs app-muted">{{ selectedItem.masked_account_number || 'No account on file' }}</div></div>
                                <div><div class="text-xs font-bold uppercase tracking-[0.14em] app-muted">Routes served</div><div class="mt-2 text-sm font-black app-title">{{ selectedItem.routes_served?.join(', ') || 'No routes mapped' }}</div></div>
                                <div><div class="text-xs font-bold uppercase tracking-[0.14em] app-muted">Parcel capabilities</div><div class="mt-2 text-sm font-black app-title">{{ selectedItem.parcel_capabilities?.join(', ') || 'No capabilities mapped' }}</div></div>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <div class="rounded-[24px] border p-5" style="border-color: var(--app-border); background: var(--app-surface-soft);">
                            <div class="text-[11px] font-bold uppercase tracking-[0.16em] app-muted">Warnings</div>
                            <div class="mt-4 grid gap-3">
                                <div v-if="selectedItem.expiry_state === 'expired'" class="rounded-[18px] border px-4 py-3 text-sm" style="border-color: rgba(220,38,38,0.16); background: rgba(220,38,38,0.06); color:#991b1b;">Licence has already expired.</div>
                                <div v-if="selectedItem.expiry_state === 'expiring'" class="rounded-[18px] border px-4 py-3 text-sm" style="border-color: rgba(242,201,0,0.6); background: rgba(242,201,0,0.12); color:#1F1F1F;">Licence is within the 30-day expiry window.</div>
                                <div v-for="warning in selectedItem.missing_fields || []" :key="warning" class="rounded-[18px] border px-4 py-3 text-sm" style="border-color: var(--app-border); background: rgba(255,255,255,0.7);">{{ warning }}</div>
                                <div v-if="!(selectedItem.missing_fields || []).length && selectedItem.expiry_state === 'valid'" class="rounded-[18px] border px-4 py-3 text-sm app-muted" style="border-color: var(--app-border); background: rgba(255,255,255,0.7);">No missing verification fields detected.</div>
                            </div>
                        </div>

                        <div class="rounded-[24px] border p-5" style="border-color: var(--app-border); background: var(--app-surface-soft);">
                            <div class="text-[11px] font-bold uppercase tracking-[0.16em] app-muted">Audit trail</div>
                            <div class="mt-4 space-y-3">
                                <div v-for="entry in selectedItem.audit_trail || []" :key="`${entry.label}-${entry.time}`" class="rounded-[18px] border px-4 py-3" style="border-color: rgba(31,31,31,0.06); background: rgba(255,255,255,0.75);">
                                    <div class="text-sm font-black app-title">{{ entry.label }}</div>
                                    <div class="mt-1 text-xs app-muted">{{ entry.meta }} · {{ entry.time }}</div>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-[24px] border p-5" style="border-color: var(--app-border); background: rgba(47,46,124,0.05);">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <div class="text-[11px] font-bold uppercase tracking-[0.16em] app-muted">Review action</div>
                                    <div class="mt-2 text-base font-black app-title">Approve or reject cleanly</div>
                                </div>
                                <StatusBadge :label="selectedItem.verification_status === 'pending' ? 'awaiting decision' : 'reviewed'" :tone="selectedItem.verification_status === 'pending' ? 'warning' : 'neutral'" small />
                            </div>
                            <textarea v-model="rejectionReason" rows="4" class="mt-4 w-full rounded-[20px] border" style="border-color: var(--app-border); background: rgba(255,255,255,0.78);" placeholder="Explain exactly what needs correction if this submission is rejected."></textarea>
                            <div class="mt-3 text-xs leading-5 app-muted">Use the rejection note only when the submission needs an update. Approval will ignore this note.</div>
                            <div v-if="validationMessage" class="mt-3 rounded-[18px] border px-4 py-3 text-sm" style="border-color: rgba(220,38,38,0.16); background: rgba(220,38,38,0.06); color:#991b1b;">{{ validationMessage }}</div>
                            <div class="mt-4 flex flex-wrap gap-3">
                                <button type="button" class="app-primary-btn" :disabled="processing || !canApprove" @click="review('verified')">{{ processing ? 'Working...' : 'Approve driver' }}</button>
                                <button type="button" class="app-outline-btn" :disabled="processing" @click="review('rejected')">Reject with note</button>
                            </div>
                        </div>
                    </div>
                </div>
            </article>
        </section>
    </AuthenticatedLayout>
</template>
