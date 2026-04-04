<script setup>
import { computed } from "vue";
import { CircleDollarSign, ShieldCheck } from "lucide-vue-next";

const props = defineProps({
    bookingReference: { type: String, default: "" },
    trackingNumber: { type: String, default: "" },
    paymentStatus: { type: String, default: "ready" },
    bookingStatus: { type: String, default: "confirmed" },
    total: { type: String, default: "" },
    nextStep: { type: String, default: "" },
});

const tone = computed(() => {
    return {
        pending: "background: rgba(242,201,0,0.18); color:#1F1F1F;",
        ready: "background: rgba(47,46,124,0.12); color:#2F2E7C;",
        manual: "background: rgba(31,31,31,0.1); color: var(--app-text);",
        paid: "background: rgba(22,163,74,0.14); color:#166534;",
        failed: "background: rgba(220,38,38,0.12); color:#b91c1c;",
    }[props.paymentStatus] || "background: var(--app-surface-soft); color: var(--app-text);";
});
</script>

<template>
    <div class="rounded-[26px] border p-5 sm:p-6" style="border-color: var(--app-border); background: var(--app-surface);">
        <div class="flex items-start justify-between gap-4">
            <div>
                <div class="text-[11px] font-bold uppercase tracking-[0.18em]" style="color: #2F2E7C;">Payment-ready</div>
                <div class="mt-2 text-2xl font-black app-title">{{ total }}</div>
            </div>
            <span class="rounded-full px-3 py-2 text-[11px] font-bold uppercase tracking-[0.16em]" :style="tone">
                {{ paymentStatus.replace('_', ' ') }}
            </span>
        </div>

        <div class="mt-5 grid gap-3 sm:grid-cols-2">
            <div class="rounded-[20px] p-4" style="background: var(--app-surface-soft);">
                <div class="inline-flex items-center gap-2 text-[11px] font-bold uppercase tracking-[0.16em] app-muted">
                    <ShieldCheck class="h-4 w-4" />
                    Booking ref
                </div>
                <div class="mt-2 text-sm font-black app-title">{{ bookingReference }}</div>
                <div class="mt-1 text-sm app-muted">{{ bookingStatus.replace('_', ' ') }}</div>
            </div>
            <div class="rounded-[20px] p-4" style="background: var(--app-surface-soft);">
                <div class="inline-flex items-center gap-2 text-[11px] font-bold uppercase tracking-[0.16em] app-muted">
                    <CircleDollarSign class="h-4 w-4" />
                    Tracking
                </div>
                <div class="mt-2 text-sm font-black app-title">{{ trackingNumber }}</div>
                <div class="mt-1 text-sm app-muted">{{ nextStep }}</div>
            </div>
        </div>
    </div>
</template>
