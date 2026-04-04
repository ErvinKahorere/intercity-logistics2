<script setup>
import { computed } from "vue";
import { Head, Link, usePage } from "@inertiajs/vue3";
import PublicLayout from "@/Layouts/PublicLayout.vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import DriverDetailsHeader from "@/Components/DriverWorkspace/DriverDetailsHeader.vue";
import DriverPerformanceCard from "@/Components/DriverWorkspace/DriverPerformanceCard.vue";
import DriverRoutesSection from "@/Components/DriverWorkspace/DriverRoutesSection.vue";
import DriverVerificationBadge from "@/Components/DriverWorkspace/DriverVerificationBadge.vue";
import DriverAvailabilityBadge from "@/Components/DriverWorkspace/DriverAvailabilityBadge.vue";
import StatusBadge from "@/Components/AppShell/StatusBadge.vue";

const props = defineProps({
    details: { type: Object, default: () => ({}) },
});

const page = usePage();
const authUser = computed(() => page.props.auth?.user || null);
const layoutComponent = computed(() => props.details.viewer_mode === "customer" ? PublicLayout : AuthenticatedLayout);
const pageTitle = computed(() => `${props.details.profile?.name || "Driver"} - Driver Details`);
const isCustomerView = computed(() => props.details.viewer_mode === "customer");
const verificationTone = computed(() => ({
    valid: "success",
    expiring: "warning",
    expired: "danger",
    unknown: "neutral",
}[props.details.verification?.expiry_state] || "neutral"));
const recentActivity = computed(() => props.details.recent_activity || []);
const activeJobs = computed(() => props.details.active_jobs || []);
</script>

<template>
    <component :is="layoutComponent" :user="authUser">
        <Head :title="pageTitle" />

        <div class="mx-auto max-w-[1560px] space-y-6 px-4 py-8 sm:px-6 lg:px-8">
            <div class="flex flex-wrap items-center gap-3">
                <Link :href="isCustomerView ? route('find.Driver') : route('dashboard')" class="app-outline-btn">
                    {{ isCustomerView ? "Back to Find Driver" : "Back to workspace" }}
                </Link>
                <DriverVerificationBadge :status="details.profile?.verification_status" />
                <DriverAvailabilityBadge :status="details.profile?.available ? 'Online' : 'Busy'" />
            </div>

            <DriverDetailsHeader :profile="details.profile" :viewer-mode="details.viewer_mode" :actions="details.actions" />

            <section class="grid gap-6 xl:grid-cols-[1.16fr_0.84fr]">
                <div class="space-y-6">
                    <section class="rounded-[30px] border p-5 shadow-[0_18px_48px_rgba(15,23,42,0.06)] sm:p-6" style="border-color: var(--app-border); background: linear-gradient(150deg, rgba(255,255,255,0.98), rgba(246,243,237,0.94));">
                        <p class="text-[11px] font-bold uppercase tracking-[0.18em] app-muted">Overview</p>
                        <h2 class="mt-2 text-3xl font-black app-title">Professional summary</h2>
                        <p class="mt-4 text-base leading-7 app-muted">{{ details.profile?.about }}</p>

                        <div class="mt-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                            <div class="rounded-[22px] px-4 py-4" style="background: rgba(255,255,255,0.84);">
                                <div class="text-[11px] font-bold uppercase tracking-[0.16em] app-muted">Operating region</div>
                                <div class="mt-2 text-sm font-black app-title">{{ details.overview?.operating_region || "Namibia network" }}</div>
                            </div>
                            <div class="rounded-[22px] px-4 py-4" style="background: rgba(255,255,255,0.84);">
                                <div class="text-[11px] font-bold uppercase tracking-[0.16em] app-muted">Preferred loads</div>
                                <div class="mt-2 text-sm font-black app-title">{{ (details.overview?.preferred_parcel_types || []).slice(0, 2).join(", ") || "General parcels" }}</div>
                            </div>
                            <div class="rounded-[22px] px-4 py-4" style="background: rgba(255,255,255,0.84);">
                                <div class="text-[11px] font-bold uppercase tracking-[0.16em] app-muted">Vehicle capacity</div>
                                <div class="mt-2 text-sm font-black app-title">{{ details.overview?.vehicle_capacity || "Not set" }}</div>
                            </div>
                            <div class="rounded-[22px] px-4 py-4" style="background: rgba(255,255,255,0.84);">
                                <div class="text-[11px] font-bold uppercase tracking-[0.16em] app-muted">Current workload</div>
                                <div class="mt-2 text-sm font-black app-title">{{ details.overview?.current_workload || 0 }} jobs</div>
                            </div>
                        </div>

                        <div class="mt-5 rounded-[24px] border px-4 py-4 text-sm app-muted" style="border-color: rgba(47,46,124,0.08); background: rgba(47,46,124,0.03);">
                            {{ details.profile?.trust_label || "Trusted route partner" }} with route coverage across {{ (details.overview?.routes_served || []).length || 0 }} stop{{ (details.overview?.routes_served || []).length === 1 ? "" : "s" }}.
                        </div>
                    </section>

                    <section class="rounded-[30px] border p-5 shadow-[0_18px_48px_rgba(15,23,42,0.06)] sm:p-6" style="border-color: var(--app-border); background: linear-gradient(150deg, rgba(255,255,255,0.98), rgba(246,243,237,0.94));">
                        <p class="text-[11px] font-bold uppercase tracking-[0.18em] app-muted">Capability</p>
                        <h2 class="mt-2 text-3xl font-black app-title">Vehicle and cargo fit</h2>

                        <div class="mt-6 grid gap-4 md:grid-cols-2">
                            <div class="rounded-[24px] border px-4 py-4" style="border-color: rgba(47,46,124,0.08); background: rgba(255,255,255,0.82);">
                                <div class="text-[11px] font-bold uppercase tracking-[0.16em] app-muted">Vehicle</div>
                                <div class="mt-2 text-lg font-black app-title">{{ details.profile?.vehicle_label || "Delivery vehicle" }}</div>
                                <div class="mt-1 text-sm app-muted">{{ details.capabilities?.vehicle_type || "Vehicle class not set" }}</div>
                            </div>
                            <div class="rounded-[24px] border px-4 py-4" style="border-color: rgba(47,46,124,0.08); background: rgba(255,255,255,0.82);">
                                <div class="text-[11px] font-bold uppercase tracking-[0.16em] app-muted">Load profile</div>
                                <div class="mt-2 text-lg font-black app-title">{{ details.capabilities?.max_load_size || "Not specified" }}</div>
                                <div class="mt-1 text-sm app-muted">{{ details.capabilities?.supports_refrigerated ? "Cold-chain capable" : "Standard cargo handling" }}</div>
                            </div>
                        </div>

                        <div class="mt-5 flex flex-wrap gap-2">
                            <span v-for="badge in details.capabilities?.badges || []" :key="badge" class="rounded-full border px-3 py-1.5 text-[11px] font-bold uppercase tracking-[0.14em]" style="border-color: var(--app-border); background: var(--app-surface-soft); color: var(--app-text);">
                                {{ badge }}
                            </span>
                        </div>

                        <div class="mt-5 rounded-[24px] border px-4 py-4" style="border-color: rgba(47,46,124,0.08); background: rgba(255,255,255,0.82);">
                            <div class="text-[11px] font-bold uppercase tracking-[0.16em] app-muted">Parcel specialties</div>
                            <div class="mt-3 flex flex-wrap gap-2">
                                <span v-for="item in details.capabilities?.parcel_types || []" :key="item" class="rounded-full border px-3 py-1.5 text-[11px] font-bold" style="border-color: var(--app-border); background: rgba(255,255,255,0.96); color: var(--app-text);">
                                    {{ item }}
                                </span>
                            </div>
                        </div>
                    </section>

                    <DriverRoutesSection title="Routes served" :routes="details.routes || []" />
                    <DriverPerformanceCard :performance="details.performance || {}" />
                </div>

                <div class="space-y-6">
                    <section class="rounded-[30px] border p-5 shadow-[0_18px_48px_rgba(15,23,42,0.06)] sm:p-6" style="border-color: var(--app-border); background: linear-gradient(150deg, rgba(255,255,255,0.98), rgba(246,243,237,0.94));">
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <p class="text-[11px] font-bold uppercase tracking-[0.18em] app-muted">Licence and trust</p>
                                <h2 class="mt-2 text-2xl font-black app-title">Verification</h2>
                            </div>
                            <DriverVerificationBadge :status="details.verification?.status" />
                        </div>

                        <div class="mt-5 grid gap-3">
                            <div class="rounded-[22px] border px-4 py-4" style="border-color: rgba(47,46,124,0.08); background: rgba(255,255,255,0.84);">
                                <div class="flex items-center justify-between gap-3">
                                    <div>
                                        <div class="text-[11px] font-bold uppercase tracking-[0.16em] app-muted">Licence type</div>
                                        <div class="mt-2 text-sm font-black app-title">{{ details.verification?.licence_type || "Not uploaded" }}</div>
                                    </div>
                                    <StatusBadge :label="details.verification?.expiry_state || 'unknown'" :tone="verificationTone" small />
                                </div>
                            </div>
                            <div class="rounded-[22px] border px-4 py-4" style="border-color: rgba(47,46,124,0.08); background: rgba(255,255,255,0.84);">
                                <div class="text-[11px] font-bold uppercase tracking-[0.16em] app-muted">Expiry date</div>
                                <div class="mt-2 text-sm font-black app-title">{{ details.verification?.expiry_date || "Not added" }}</div>
                            </div>
                            <div v-if="details.verification?.licence_number" class="rounded-[22px] border px-4 py-4" style="border-color: rgba(47,46,124,0.08); background: rgba(255,255,255,0.84);">
                                <div class="text-[11px] font-bold uppercase tracking-[0.16em] app-muted">Licence number</div>
                                <div class="mt-2 text-sm font-black app-title">{{ details.verification.licence_number }}</div>
                            </div>
                            <div v-if="details.verification?.rejection_reason" class="rounded-[22px] border px-4 py-4" style="border-color: rgba(31,31,31,0.12); background: rgba(31,31,31,0.04);">
                                <div class="text-[11px] font-bold uppercase tracking-[0.16em] app-muted">Review note</div>
                                <div class="mt-2 text-sm app-muted">{{ details.verification.rejection_reason }}</div>
                            </div>
                        </div>
                    </section>

                    <section class="rounded-[30px] border p-5 shadow-[0_18px_48px_rgba(15,23,42,0.06)] sm:p-6" style="border-color: var(--app-border); background: linear-gradient(150deg, rgba(255,255,255,0.98), rgba(246,243,237,0.94));">
                        <p class="text-[11px] font-bold uppercase tracking-[0.18em] app-muted">Recent activity</p>
                        <h2 class="mt-2 text-2xl font-black app-title">Delivery history</h2>

                        <div v-if="recentActivity.length" class="mt-5 space-y-3">
                            <article v-for="item in recentActivity" :key="item.id" class="rounded-[24px] border px-4 py-4" style="border-color: rgba(47,46,124,0.08); background: rgba(255,255,255,0.84);">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <div class="text-sm font-black app-title">{{ item.route }}</div>
                                        <div class="mt-1 text-sm app-muted">{{ item.parcel_type }} · {{ item.tracking_number }}</div>
                                    </div>
                                    <div class="text-right">
                                        <StatusBadge :label="item.status_label" tone="info" small />
                                        <div class="mt-2 text-[11px] font-bold uppercase tracking-[0.16em] app-muted">{{ item.updated_at }}</div>
                                    </div>
                                </div>
                            </article>
                        </div>
                        <div v-else class="mt-5 rounded-[24px] border px-4 py-5 text-sm app-muted" style="border-color: rgba(47,46,124,0.08); background: rgba(255,255,255,0.82);">
                            No delivery history is available yet.
                        </div>
                    </section>

                    <section v-if="activeJobs.length" class="rounded-[30px] border p-5 shadow-[0_18px_48px_rgba(15,23,42,0.06)] sm:p-6" style="border-color: var(--app-border); background: linear-gradient(150deg, rgba(255,255,255,0.98), rgba(246,243,237,0.94));">
                        <p class="text-[11px] font-bold uppercase tracking-[0.18em] app-muted">Live workload</p>
                        <h2 class="mt-2 text-2xl font-black app-title">Active jobs</h2>

                        <div class="mt-5 space-y-3">
                            <article v-for="job in activeJobs" :key="job.id" class="rounded-[24px] border px-4 py-4" style="border-color: rgba(47,46,124,0.08); background: rgba(255,255,255,0.84);">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <div class="text-sm font-black app-title">{{ job.route }}</div>
                                        <div class="mt-1 text-sm app-muted">{{ job.parcel_type }} · {{ job.tracking_number }}</div>
                                    </div>
                                    <StatusBadge :label="job.status_label" tone="warning" small />
                                </div>
                            </article>
                        </div>
                    </section>

                    <section v-if="details.banking" class="rounded-[30px] border p-5 shadow-[0_18px_48px_rgba(15,23,42,0.06)] sm:p-6" style="border-color: var(--app-border); background: linear-gradient(150deg, rgba(255,255,255,0.98), rgba(246,243,237,0.94));">
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <p class="text-[11px] font-bold uppercase tracking-[0.18em] app-muted">Payout readiness</p>
                                <h2 class="mt-2 text-2xl font-black app-title">Banking overview</h2>
                            </div>
                            <StatusBadge :label="details.banking.status || 'incomplete'" :tone="details.banking.status === 'confirmed' ? 'success' : 'warning'" small />
                        </div>

                        <div class="mt-5 grid gap-3">
                            <div class="rounded-[22px] border px-4 py-4" style="border-color: rgba(47,46,124,0.08); background: rgba(255,255,255,0.84);">
                                <div class="text-[11px] font-bold uppercase tracking-[0.16em] app-muted">Bank</div>
                                <div class="mt-2 text-sm font-black app-title">{{ details.banking.bank_name || "Not added" }}</div>
                            </div>
                            <div class="rounded-[22px] border px-4 py-4" style="border-color: rgba(47,46,124,0.08); background: rgba(255,255,255,0.84);">
                                <div class="text-[11px] font-bold uppercase tracking-[0.16em] app-muted">Account</div>
                                <div class="mt-2 text-sm font-black app-title">{{ details.banking.masked_account_number || "Masked after save" }}</div>
                            </div>
                            <div class="rounded-[22px] border px-4 py-4" style="border-color: rgba(47,46,124,0.08); background: rgba(255,255,255,0.84);">
                                <div class="text-[11px] font-bold uppercase tracking-[0.16em] app-muted">Account type</div>
                                <div class="mt-2 text-sm font-black app-title">{{ details.banking.account_type || "Not added" }}</div>
                            </div>
                        </div>
                    </section>

                    <section v-if="details.documents" class="rounded-[30px] border p-5 shadow-[0_18px_48px_rgba(15,23,42,0.06)] sm:p-6" style="border-color: var(--app-border); background: linear-gradient(150deg, rgba(255,255,255,0.98), rgba(246,243,237,0.94));">
                        <p class="text-[11px] font-bold uppercase tracking-[0.18em] app-muted">Documents</p>
                        <h2 class="mt-2 text-2xl font-black app-title">Compliance files</h2>

                        <div class="mt-5 space-y-3">
                            <article v-for="document in details.documents.licences || []" :key="document.id" class="rounded-[24px] border px-4 py-4" style="border-color: rgba(47,46,124,0.08); background: rgba(255,255,255,0.84);">
                                <div class="flex items-center justify-between gap-3">
                                    <div>
                                        <div class="text-sm font-black app-title">{{ document.name }}</div>
                                        <div class="mt-1 text-sm app-muted">Expiry {{ document.expiry_date || "not set" }}</div>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <StatusBadge :label="document.status || 'pending'" tone="info" small />
                                        <a v-if="document.document_url" :href="document.document_url" target="_blank" class="app-outline-btn">Open</a>
                                    </div>
                                </div>
                            </article>
                        </div>
                    </section>
                </div>
            </section>
        </div>
    </component>
</template>
