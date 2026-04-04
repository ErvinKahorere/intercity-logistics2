<script setup>
import StatusBadge from "@/Components/AppShell/StatusBadge.vue";
import DeliveryProgressStepper from "@/Components/DriverDashboard/DeliveryProgressStepper.vue";

defineProps({
    delivery: {
        type: Object,
        required: true,
    },
    busy: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(["advance"]);

function toneFor(status) {
    return {
        accepted: "success",
        picked_up: "warning",
        in_transit: "info",
        arrived: "brand",
        delivered: "success",
    }[status] || "neutral";
}

function nextStepLabel(status) {
    return {
        picked_up: "Mark Picked Up",
        in_transit: "Mark In Transit",
        arrived: "Mark Arrived",
        delivered: "Mark Delivered",
    }[status] || status;
}
</script>

<template>
    <article class="rounded-[30px] border p-5 shadow-[0_18px_48px_rgba(15,23,42,0.06)]" style="border-color: rgba(47,46,124,0.08); background: linear-gradient(145deg, rgba(255,255,255,0.98), rgba(246,243,237,0.92));">
        <div class="flex items-start justify-between gap-4">
            <div>
                <p class="text-[11px] font-bold uppercase tracking-[0.18em] app-muted">{{ delivery.tracking_number }}</p>
                <h3 class="mt-2 text-2xl font-black app-title">{{ delivery.pickup_location }} -> {{ delivery.dropoff_location }}</h3>
                <p class="mt-2 text-sm app-muted">{{ delivery.package_type }} · {{ delivery.receiver_name || "Parcel request" }}</p>
            </div>
            <StatusBadge :tone="toneFor(delivery.status)" :label="delivery.status_label" />
        </div>

        <div class="mt-4 grid gap-3 sm:grid-cols-3">
            <div class="rounded-2xl px-4 py-3" style="background: rgba(47,46,124,0.05);">
                <div class="text-[11px] font-bold uppercase tracking-[0.16em] app-muted">Accepted price</div>
                <div class="mt-1 text-lg font-black app-title">N$ {{ Number(delivery.total_price || 0).toFixed(2) }}</div>
                <div class="mt-1 text-xs app-muted">Booked route value</div>
            </div>
            <div class="rounded-2xl px-4 py-3" style="background: rgba(242,201,0,0.14);">
                <div class="text-[11px] font-bold uppercase tracking-[0.16em] app-muted">Accepted</div>
                <div class="mt-1 text-lg font-black app-title">{{ delivery.accepted_time || "Now" }}</div>
                <div class="mt-1 text-xs app-muted">Job start point</div>
            </div>
            <div class="rounded-2xl px-4 py-3" style="background: var(--app-surface-soft);">
                <div class="text-[11px] font-bold uppercase tracking-[0.16em] app-muted">Reference</div>
                <div class="mt-1 text-lg font-black app-title">{{ delivery.receiver_phone || "Tracking live" }}</div>
                <div class="mt-1 text-xs app-muted">{{ delivery.next_action ? `Next action: ${nextStepLabel(delivery.next_action)}` : "Awaiting next step" }}</div>
            </div>
        </div>

        <div class="mt-4 overflow-hidden rounded-[24px] border" style="border-color: rgba(47,46,124,0.08);">
            <div class="grid gap-px md:grid-cols-3" style="background: rgba(47,46,124,0.08);">
                <div class="px-4 py-3" style="background: rgba(255,255,255,0.92);">
                    <div class="text-[11px] font-bold uppercase tracking-[0.16em] app-muted">Current status</div>
                    <div class="mt-1 text-sm font-black app-title">{{ delivery.status_label }}</div>
                </div>
                <div class="px-4 py-3" style="background: rgba(255,255,255,0.92);">
                    <div class="text-[11px] font-bold uppercase tracking-[0.16em] app-muted">ETA summary</div>
                    <div class="mt-1 text-sm font-black app-title">{{ delivery.eta_summary || "Tracking live" }}</div>
                </div>
                <div class="px-4 py-3" style="background: rgba(255,255,255,0.92);">
                    <div class="text-[11px] font-bold uppercase tracking-[0.16em] app-muted">Next action</div>
                    <div class="mt-1 text-sm font-black app-title">{{ delivery.next_action ? nextStepLabel(delivery.next_action) : "No action due" }}</div>
                </div>
            </div>
        </div>

        <div class="mt-5 rounded-[24px] border p-4" style="border-color: var(--app-border); background: var(--app-surface-soft);">
            <div class="flex items-center justify-between gap-3">
                <p class="text-[11px] font-bold uppercase tracking-[0.18em] app-muted">Progress</p>
                <StatusBadge :tone="toneFor(delivery.status)" :label="delivery.status_label" small />
            </div>
            <div class="mt-4">
                <DeliveryProgressStepper :status="delivery.status" />
            </div>
        </div>

        <div class="mt-4 space-y-2">
            <div v-for="update in delivery.timeline" :key="update.id" class="flex items-center justify-between rounded-2xl px-4 py-3" style="background: var(--app-surface-soft);">
                <div>
                    <div class="text-sm font-bold app-title">{{ update.title }}</div>
                    <div class="text-xs app-muted">{{ update.status.replace('_', ' ') }}</div>
                </div>
                <div class="text-xs font-semibold uppercase tracking-[0.16em] app-muted">{{ update.time }}</div>
            </div>
        </div>

        <div class="mt-5 flex flex-wrap gap-3">
            <button
                v-for="nextStatus in delivery.next_steps"
                :key="nextStatus"
                type="button"
                class="app-primary-btn !px-4 !py-3 !text-xs"
                :disabled="busy"
                @click="emit('advance', delivery.id, nextStatus)"
            >
                {{ busy ? "Updating..." : nextStepLabel(nextStatus) }}
            </button>
        </div>
    </article>
</template>
