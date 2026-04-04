<script setup>
import { Link } from "@inertiajs/vue3";
import DriverVerificationBadge from "@/Components/DriverWorkspace/DriverVerificationBadge.vue";
import StatusBadge from "@/Components/AppShell/StatusBadge.vue";

const props = defineProps({
    compliance: { type: Object, default: () => ({}) },
});

function toneFor(state) {
    return {
        complete: "success",
        verified: "success",
        pending: "warning",
        incomplete: "danger",
        expired: "danger",
        expiring: "warning",
        valid: "success",
    }[String(state || "").toLowerCase()] || "neutral";
}

function readinessWidth() {
    return Math.max(0, Math.min(100, Number(props.compliance.score || 0)));
}
</script>

<template>
    <section class="rounded-[30px] border p-5 shadow-[0_18px_48px_rgba(15,23,42,0.06)]" style="border-color: var(--app-border); background: linear-gradient(150deg, rgba(255,255,255,0.98), rgba(246,243,237,0.94));">
        <div class="flex items-start justify-between gap-4">
            <div>
                <p class="text-[11px] font-bold uppercase tracking-[0.18em] app-muted">Readiness</p>
                <h3 class="mt-2 text-2xl font-black app-title">Compliance and payout</h3>
            </div>
            <div class="text-right">
                <div class="text-[11px] font-bold uppercase tracking-[0.18em] app-muted">Ready</div>
                <div class="mt-2 text-3xl font-black app-title">{{ compliance.score || 0 }}%</div>
            </div>
        </div>

        <div class="mt-5 h-3 overflow-hidden rounded-full" style="background: rgba(47,46,124,0.08);">
            <div class="h-full rounded-full" :style="`width:${readinessWidth()}%; background: linear-gradient(90deg, #2F2E7C 0%, #F2C900 100%);`" />
        </div>

        <div class="mt-5 flex flex-wrap gap-2">
            <DriverVerificationBadge :status="compliance.verification_status" />
            <StatusBadge :label="`Banking ${compliance.banking_status || 'incomplete'}`" :tone="toneFor(compliance.banking_status)" small />
            <StatusBadge v-if="compliance.licence_expiry_state && compliance.licence_expiry_state !== 'unknown'" :label="`Licence ${compliance.licence_expiry_state}`" :tone="toneFor(compliance.licence_expiry_state)" small />
        </div>

        <div class="mt-5 rounded-[24px] border px-4 py-4" style="border-color: rgba(47,46,124,0.08); background: rgba(255,255,255,0.82);">
            <div class="text-[11px] font-bold uppercase tracking-[0.16em] app-muted">Next readiness step</div>
            <div class="mt-2 text-sm font-black app-title">{{ (compliance.items || []).find((item) => !item.complete)?.label || "Account is payout ready" }}</div>
        </div>

        <div class="mt-5 space-y-3">
            <div v-for="item in compliance.items || []" :key="item.label" class="flex items-center justify-between rounded-[22px] border px-4 py-3" style="border-color: rgba(47,46,124,0.08); background: rgba(255,255,255,0.82);">
                <div class="text-sm font-bold app-title">{{ item.label }}</div>
                <StatusBadge :label="item.complete ? 'Complete' : 'Needs update'" :tone="item.complete ? 'success' : 'warning'" small />
            </div>
        </div>

        <div class="mt-5 grid gap-3 sm:grid-cols-2">
            <Link :href="route('driver.profile') + '#verification'" class="app-outline-btn w-full">Licence and verification</Link>
            <Link :href="route('driver.profile') + '#banking'" class="app-primary-btn w-full">Banking and payouts</Link>
        </div>
    </section>
</template>
