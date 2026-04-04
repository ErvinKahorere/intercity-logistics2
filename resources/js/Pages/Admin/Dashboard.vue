<script setup>
import { computed } from "vue";
import { Head, Link } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import PageHeader from "@/Components/AppShell/PageHeader.vue";
import StatusBadge from "@/Components/AppShell/StatusBadge.vue";
import AdminStatCard from "@/Components/Admin/AdminStatCard.vue";
import AdminAlertPanel from "@/Components/Admin/AdminAlertPanel.vue";

const props = defineProps({
    stats: { type: Array, default: () => [] },
    alerts: { type: Array, default: () => [] },
    quick_actions: { type: Array, default: () => [] },
    recent_activity: { type: Array, default: () => [] },
    verification_preview: { type: Array, default: () => [] },
    route_preview: { type: Array, default: () => [] },
    quotation_preview: { type: Array, default: () => [] },
    invoice_preview: { type: Array, default: () => [] },
});

const criticalAlerts = computed(() => props.alerts.filter((item) => ["danger", "warning"].includes(item.tone)));
const watchAlerts = computed(() => props.alerts.filter((item) => !["danger", "warning"].includes(item.tone)));
const primaryStats = computed(() => props.stats.slice(0, 5));
const secondaryStats = computed(() => props.stats.slice(5));
</script>

<template>
    <Head title="Admin Operations" />

    <AuthenticatedLayout>
        <PageHeader eyebrow="Operations console" title="Admin Operations Layer" description="Monitor verification, routes, pricing, commercial documents, and live marketplace health from one operational workspace.">
            <template #actions>
                <div class="rounded-[18px] border px-4 py-3 text-sm font-semibold" style="border-color: var(--app-border); background: var(--app-surface-soft); color: var(--app-text);">
                    {{ new Date().toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }) }}
                </div>
            </template>
        </PageHeader>

        <section class="grid gap-6 xl:grid-cols-[1.25fr_0.75fr]">
            <article class="app-panel rounded-[32px] p-6 sm:p-7">
                <div class="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
                    <div class="max-w-2xl">
                        <div class="text-[11px] font-bold uppercase tracking-[0.18em]" style="color:#2F2E7C;">Control center</div>
                        <h2 class="mt-3 text-3xl font-black leading-tight app-title sm:text-4xl">Stay ahead of compliance, pricing, and delivery risk.</h2>
                        <p class="mt-3 max-w-xl text-sm leading-7 app-muted">The console prioritizes issues that need action first, then keeps the rest of your operational controls within quick reach.</p>
                    </div>
                    <div class="grid min-w-[220px] gap-3 sm:grid-cols-2 lg:grid-cols-1">
                        <div class="rounded-[24px] border p-4" style="border-color: rgba(47,46,124,0.14); background: rgba(47,46,124,0.05);">
                            <div class="text-[11px] font-bold uppercase tracking-[0.16em] app-muted">Priority alerts</div>
                            <div class="mt-2 text-3xl font-black app-title">{{ criticalAlerts.length }}</div>
                            <div class="mt-1 text-sm app-muted">Needs attention now</div>
                        </div>
                        <div class="rounded-[24px] border p-4" style="border-color: var(--app-border); background: var(--app-surface-soft);">
                            <div class="text-[11px] font-bold uppercase tracking-[0.16em] app-muted">Recent activity</div>
                            <div class="mt-2 text-3xl font-black app-title">{{ recent_activity.length }}</div>
                            <div class="mt-1 text-sm app-muted">Fresh operational events</div>
                        </div>
                    </div>
                </div>
            </article>

            <AdminAlertPanel :alerts="criticalAlerts" title="Priority alerts" />
        </section>

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-5">
            <AdminStatCard v-for="item in primaryStats" :key="item.label" :label="item.label" :value="item.value" :meta="item.meta" :tone="item.tone" />
        </section>

        <section v-if="secondaryStats.length" class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <AdminStatCard v-for="item in secondaryStats" :key="item.label" :label="item.label" :value="item.value" :meta="item.meta" :tone="item.tone" />
        </section>

        <section class="grid gap-6 xl:grid-cols-[1.3fr_0.9fr]">
            <article class="app-panel rounded-[30px] p-6">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <div class="text-[11px] font-bold uppercase tracking-[0.18em]" style="color:#2F2E7C;">Quick actions</div>
                        <h2 class="mt-2 text-2xl font-black app-title">Operational shortcuts</h2>
                    </div>
                    <StatusBadge :label="`${quick_actions.length} links`" tone="brand" />
                </div>

                <div class="mt-5 grid gap-3 sm:grid-cols-2">
                    <Link v-for="action in quick_actions" :key="action.label" :href="action.route" class="rounded-[24px] border px-5 py-4 transition hover:-translate-y-0.5" style="border-color: var(--app-border); background: var(--app-surface-soft); color: var(--app-text);">
                        <div class="text-base font-black app-title">{{ action.label }}</div>
                        <div class="mt-1 text-sm app-muted">Jump directly into the relevant workspace.</div>
                    </Link>
                </div>
            </article>

            <AdminAlertPanel :alerts="watchAlerts" title="Operational watchlist" />
        </section>

        <section class="grid gap-6 xl:grid-cols-[1.15fr_0.85fr]">
            <article class="app-panel rounded-[30px] p-6">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <div class="text-[11px] font-bold uppercase tracking-[0.18em]" style="color:#2F2E7C;">Recent activity</div>
                        <h2 class="mt-2 text-2xl font-black app-title">Marketplace activity feed</h2>
                    </div>
                    <StatusBadge :label="`${recent_activity.length} items`" tone="neutral" />
                </div>

                <div class="mt-5 space-y-3">
                    <div v-for="item in recent_activity" :key="`${item.type}-${item.title}-${item.meta}`" class="rounded-[24px] border p-4" style="border-color: var(--app-border); background: var(--app-surface-soft);">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <div class="text-base font-black app-title">{{ item.title }}</div>
                                <div class="mt-1 text-sm leading-6 app-muted">{{ item.meta }}</div>
                            </div>
                            <StatusBadge :label="item.type" :tone="item.tone || 'neutral'" small />
                        </div>
                    </div>
                </div>
            </article>

            <div class="space-y-6">
                <article class="app-panel rounded-[30px] p-6">
                    <div class="flex items-center justify-between gap-3">
                        <div class="text-[11px] font-bold uppercase tracking-[0.18em]" style="color:#2F2E7C;">Verification preview</div>
                        <StatusBadge :label="`${verification_preview.length} queued`" :tone="verification_preview.length ? 'warning' : 'success'" small />
                    </div>
                    <div class="mt-4 space-y-3">
                        <div v-for="item in verification_preview" :key="item.id" class="rounded-[22px] border p-4" style="border-color: var(--app-border); background: var(--app-surface-soft);">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <div class="text-base font-black app-title">{{ item.driver_name }}</div>
                                    <div class="mt-1 text-sm app-muted">{{ item.licence_type_name }} · expires {{ item.expiry_date }}</div>
                                    <div v-if="item.expiry_state === 'expiring' || item.expiry_state === 'expired'" class="mt-2 text-xs font-semibold" :style="item.expiry_state === 'expired' ? 'color:#991b1b;' : 'color:#7a6200;'">
                                        {{ item.expiry_state === 'expired' ? 'Expired licence' : 'Expiring soon' }}
                                    </div>
                                </div>
                                <StatusBadge :label="item.verification_status" :tone="item.verification_status === 'pending' ? 'warning' : item.verification_status === 'verified' ? 'success' : 'danger'" small />
                            </div>
                        </div>
                    </div>
                </article>

                <article class="app-panel rounded-[30px] p-6">
                    <div class="flex items-center justify-between gap-3">
                        <div class="text-[11px] font-bold uppercase tracking-[0.18em]" style="color:#2F2E7C;">Commercial preview</div>
                        <StatusBadge :label="`${quotation_preview.length + invoice_preview.length} docs`" tone="brand" small />
                    </div>
                    <div class="mt-4 grid gap-3">
                        <div v-for="item in quotation_preview.slice(0, 2)" :key="item.id" class="rounded-[22px] border p-4" style="border-color: var(--app-border); background: var(--app-surface-soft);">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <div class="text-sm font-black app-title">{{ item.quotation_number }}</div>
                                    <div class="mt-1 text-sm app-muted">{{ item.route.pickup }} -> {{ item.route.dropoff }} · N$ {{ Number(item.total || 0).toFixed(2) }}</div>
                                </div>
                                <StatusBadge :label="item.status" tone="brand" small />
                            </div>
                        </div>
                        <div v-for="item in invoice_preview.slice(0, 2)" :key="item.id" class="rounded-[22px] border p-4" style="border-color: var(--app-border); background: var(--app-surface-soft);">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <div class="text-sm font-black app-title">{{ item.invoice_number }}</div>
                                    <div class="mt-1 text-sm app-muted">{{ item.route.pickup }} -> {{ item.route.dropoff }} · N$ {{ Number(item.total || 0).toFixed(2) }}</div>
                                </div>
                                <StatusBadge :label="item.payment_status || item.status" :tone="item.overdue ? 'danger' : item.payment_status === 'paid' ? 'success' : 'warning'" small />
                            </div>
                        </div>
                    </div>
                </article>
            </div>
        </section>
    </AuthenticatedLayout>
</template>
