<script setup>
import StatusBadge from "@/Components/AppShell/StatusBadge.vue";

defineProps({
    request: { type: Object, required: true },
    busy: { type: Boolean, default: false },
    selected: { type: Boolean, default: false },
});

const emit = defineEmits(["view", "accept"]);

function urgencyTone(level) {
    return ["express", "same_day"].includes(level) ? "warning" : "neutral";
}

function matchTone(score) {
    if (score >= 92) return "brand";
    if (score >= 78) return "info";
    return "neutral";
}
</script>

<template>
    <article
        class="rounded-[30px] border p-5 shadow-[0_18px_48px_rgba(15,23,42,0.06)] transition duration-200 hover:-translate-y-1"
        :style="selected
            ? 'border-color:#2F2E7C; background: linear-gradient(145deg, rgba(47,46,124,0.08), rgba(255,255,255,0.98)); box-shadow: 0 24px 60px rgba(47,46,124,0.16);'
            : 'border-color: var(--app-border); background: linear-gradient(145deg, rgba(255,255,255,0.98), rgba(246,243,237,0.92));'"
    >
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <StatusBadge :label="request.urgency_level?.replace('_', ' ') || 'standard'" :tone="urgencyTone(request.urgency_level)" small />
                    <StatusBadge :label="`${request.match_context?.match_score || 0}% match`" :tone="matchTone(request.match_context?.match_score || 0)" small />
                    <StatusBadge v-if="request.match_context?.label" :label="request.match_context.label" tone="brand" small />
                    <StatusBadge v-if="selected" label="Focused" tone="dark" small />
                </div>
                <h3 class="mt-3 text-2xl font-black app-title">{{ request.pickup_location }} -> {{ request.dropoff_location }}</h3>
                <p class="mt-2 text-sm app-muted">{{ request.package_type }} · {{ request.weight_kg || 0 }} kg · {{ request.load_size }}</p>
            </div>
            <div class="text-right">
                <div class="text-[11px] font-bold uppercase tracking-[0.16em] app-muted">Posted</div>
                <div class="mt-1 text-sm font-black app-title">{{ request.time_posted || "Just now" }}</div>
            </div>
        </div>

        <div class="mt-4 overflow-hidden rounded-[24px] border" style="border-color: rgba(47,46,124,0.08);">
            <div class="grid gap-px sm:grid-cols-3" style="background: rgba(47,46,124,0.08);">
                <div class="px-4 py-3" style="background: rgba(255,255,255,0.92);">
                    <div class="text-[11px] font-bold uppercase tracking-[0.16em] app-muted">Pickup</div>
                    <div class="mt-1 text-sm font-black app-title">{{ request.pickup_location }}</div>
                </div>
                <div class="px-4 py-3" style="background: rgba(255,255,255,0.92);">
                    <div class="text-[11px] font-bold uppercase tracking-[0.16em] app-muted">Destination</div>
                    <div class="mt-1 text-sm font-black app-title">{{ request.dropoff_location }}</div>
                </div>
                <div class="px-4 py-3" style="background: rgba(255,255,255,0.92);">
                    <div class="text-[11px] font-bold uppercase tracking-[0.16em] app-muted">Service promise</div>
                    <div class="mt-1 text-sm font-black app-title">{{ request.eta_summary || "ETA pricing ready" }}</div>
                </div>
            </div>
        </div>

        <div class="mt-5 grid gap-3 sm:grid-cols-3">
            <div class="rounded-[22px] px-4 py-3" style="background: rgba(47,46,124,0.05);">
                <div class="text-[11px] font-bold uppercase tracking-[0.16em] app-muted">Estimated payout</div>
                <div class="mt-1 text-lg font-black app-title">N$ {{ Number(request.estimated_payout || 0).toFixed(2) }}</div>
                <div class="mt-1 text-xs app-muted">Likely driver value</div>
            </div>
            <div class="rounded-[22px] px-4 py-3" style="background: rgba(242,201,0,0.14);">
                <div class="text-[11px] font-bold uppercase tracking-[0.16em] app-muted">Customer total</div>
                <div class="mt-1 text-lg font-black app-title">N$ {{ Number(request.total_price || request.estimated_price || 0).toFixed(2) }}</div>
                <div class="mt-1 text-xs app-muted">Pricing confidence cue</div>
            </div>
            <div class="rounded-[22px] px-4 py-3" style="background: var(--app-surface-soft);">
                <div class="text-[11px] font-bold uppercase tracking-[0.16em] app-muted">Tracking</div>
                <div class="mt-1 text-lg font-black app-title">{{ request.tracking_number }}</div>
                <div class="mt-1 text-xs app-muted">Operational reference</div>
            </div>
        </div>

        <div class="mt-4 rounded-[24px] border px-4 py-4" style="border-color: rgba(47,46,124,0.10); background: rgba(255,255,255,0.8);">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                <div class="min-w-0">
                    <div class="text-[11px] font-bold uppercase tracking-[0.16em] app-muted">Route fit</div>
                    <div class="mt-2 text-sm font-bold app-title">{{ request.match_context?.route_summary || request.match_context?.route }}</div>
                    <div v-if="(request.match_context?.reasons || []).length" class="mt-2 flex flex-wrap gap-2">
                        <span v-for="reason in request.match_context.reasons.slice(0, 3)" :key="reason" class="rounded-full border px-3 py-1 text-[11px] font-bold" style="border-color: var(--app-border); background: var(--app-surface-soft); color: var(--app-text);">
                            {{ reason }}
                        </span>
                    </div>
                </div>
                <StatusBadge :label="request.can_accept ? 'Ready to accept' : 'Already assigned'" :tone="request.can_accept ? 'success' : 'neutral'" small />
            </div>
        </div>

        <div class="mt-5 flex flex-col gap-3 sm:flex-row">
            <button type="button" class="app-outline-btn w-full" :style="selected ? 'border-color:#2F2E7C;color:#2F2E7C;' : ''" @click="emit('view', request)">
                {{ selected ? "Viewing details" : "View details" }}
            </button>
            <button type="button" class="app-primary-btn w-full" :disabled="busy || !request.can_accept" @click="emit('accept', request.id)">
                {{ busy ? "Accepting..." : request.can_accept ? "Accept request" : "Assigned" }}
            </button>
        </div>
    </article>
</template>
