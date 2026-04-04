<script setup>
import { CheckCircle2, ShieldCheck, Truck } from "lucide-vue-next";

defineProps({
    eyebrow: { type: String, default: "Booking confirmed" },
    title: { type: String, required: true },
    description: { type: String, default: "" },
    bookingReference: { type: String, default: "" },
    trackingNumber: { type: String, default: "" },
    driverName: { type: String, default: "" },
    routeLabel: { type: String, default: "" },
    etaLabel: { type: String, default: "" },
});
</script>

<template>
    <div class="rounded-[30px] border p-6 sm:p-7" style="border-color: rgba(47,46,124,0.16); background: rgba(47,46,124,0.05);">
        <div class="flex items-start gap-4">
            <div class="flex h-14 w-14 items-center justify-center rounded-[20px]" style="background: rgba(242,201,0,0.22); color:#1F1F1F;">
                <CheckCircle2 class="h-7 w-7" />
            </div>

            <div class="min-w-0">
                <div class="text-[11px] font-bold uppercase tracking-[0.22em]" style="color:#2F2E7C;">{{ eyebrow }}</div>
                <h2 class="mt-2 text-3xl font-black app-title sm:text-4xl">{{ title }}</h2>
                <p v-if="description" class="mt-3 max-w-3xl text-sm leading-7 app-muted">{{ description }}</p>
            </div>
        </div>

        <div class="mt-6 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-[22px] p-4" style="background: rgba(255,255,255,0.84);">
                <div class="text-[11px] font-bold uppercase tracking-[0.16em] app-muted">Booking ref</div>
                <div class="mt-2 text-base font-black app-title">{{ bookingReference }}</div>
            </div>
            <div class="rounded-[22px] p-4" style="background: rgba(255,255,255,0.84);">
                <div class="text-[11px] font-bold uppercase tracking-[0.16em] app-muted">Tracking number</div>
                <div class="mt-2 text-base font-black app-title">{{ trackingNumber }}</div>
            </div>
            <div class="rounded-[22px] p-4" style="background: rgba(255,255,255,0.84);">
                <div class="inline-flex items-center gap-2 text-[11px] font-bold uppercase tracking-[0.16em] app-muted">
                    <Truck class="h-4 w-4" />
                    Driver
                </div>
                <div class="mt-2 text-base font-black app-title">{{ driverName }}</div>
                <div class="mt-1 text-sm app-muted">{{ routeLabel }}</div>
            </div>
            <div class="rounded-[22px] p-4" style="background: rgba(255,255,255,0.84);">
                <div class="inline-flex items-center gap-2 text-[11px] font-bold uppercase tracking-[0.16em] app-muted">
                    <ShieldCheck class="h-4 w-4" />
                    ETA
                </div>
                <div class="mt-2 text-base font-black app-title">{{ etaLabel }}</div>
                <div class="mt-1 text-sm app-muted">Tracking included</div>
            </div>
        </div>
    </div>
</template>
