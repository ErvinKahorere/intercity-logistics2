<script setup>
import StatusBadge from "@/Components/AppShell/StatusBadge.vue";

const props = defineProps({
    request: {
        type: Object,
        required: true,
    },
});

const emit = defineEmits(["view", "accept"]);

function toneForBadge(label) {
    const normalized = String(label || "").toLowerCase();
    if (normalized.includes("best") || normalized.includes("strong") || normalized.includes("route") || normalized.includes("corridor")) return "brand";
    if (normalized.includes("express")) return "warning";
    if (normalized.includes("heavy")) return "dark";
    if (normalized.includes("offer") || normalized.includes("available") || normalized.includes("load")) return "success";
    return "neutral";
}

function badgesFor(request) {
    const badges = [];

    for (const badge of request.match_context?.badges || []) {
        badges.push({ label: badge, tone: toneForBadge(badge) });
    }

    if ((request.match_context?.match_score || 0) >= 90) badges.push({ label: "Best Match", tone: "brand" });
    if (["heavy", "oversized"].includes(request.load_size)) badges.push({ label: "Heavy Load", tone: "dark" });
    if (request.urgency_level === "express" || request.urgency_level === "same_day") badges.push({ label: "Express", tone: "warning" });
    if ((request.notes || "").toLowerCase().includes("fragile")) badges.push({ label: "Fragile", tone: "neutral" });
    if (request.client_offer_price) badges.push({ label: "Offer", tone: "success" });

    return badges.filter((badge, index, arr) => arr.findIndex((item) => item.label === badge.label) === index).slice(0, 4);
}
</script>

<template>
    <article class="app-panel rounded-[28px] p-5 transition duration-200 hover:-translate-y-1">
        <div class="flex items-start justify-between gap-4">
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <p class="text-[11px] font-bold uppercase tracking-[0.18em] app-muted">{{ request.time_posted }}</p>
                    <StatusBadge tone="brand" :label="`${request.match_context?.match_score || 0}% match`" small />
                </div>
                <h3 class="mt-3 text-2xl font-black app-title">{{ request.pickup_location }} -> {{ request.dropoff_location }}</h3>
                <p class="mt-2 text-sm app-muted">{{ request.package_type }} | {{ request.weight_kg || 0 }} kg | {{ request.load_size }}</p>
            </div>
            <StatusBadge :tone="request.urgency_level === 'standard' ? 'neutral' : 'warning'" :label="request.urgency_level.replace('_', ' ')" />
        </div>

        <div class="mt-4 flex flex-wrap gap-2">
            <StatusBadge v-for="badge in badgesFor(request)" :key="badge.label" :tone="badge.tone" :label="badge.label" small />
        </div>

        <div class="mt-5 grid gap-3 sm:grid-cols-2">
            <div class="rounded-2xl px-4 py-3" style="background: var(--app-surface-soft);">
                <div class="text-[11px] font-bold uppercase tracking-[0.16em] app-muted">Estimate</div>
                <div class="mt-1 text-lg font-black app-title">N$ {{ Number(request.estimated_price || 0).toFixed(2) }}</div>
            </div>
            <div class="rounded-2xl px-4 py-3" :style="request.client_offer_price ? 'background: rgba(242,201,0,0.16);' : 'background: var(--app-surface-soft);'">
                <div class="text-[11px] font-bold uppercase tracking-[0.16em] app-muted">Client offer</div>
                <div class="mt-1 text-lg font-black app-title">{{ request.client_offer_price ? `N$ ${Number(request.client_offer_price).toFixed(2)}` : 'Not set' }}</div>
            </div>
        </div>

        <div class="mt-4 flex items-center justify-between gap-3 rounded-2xl px-4 py-3" style="background: var(--app-surface-soft);">
            <div>
                <div class="text-[11px] font-bold uppercase tracking-[0.16em] app-muted">Route match</div>
                <div class="mt-1 text-sm font-bold app-title">{{ request.match_context?.route_summary || request.match_context?.route }}</div>
            </div>
            <div class="text-right">
                <div class="text-[11px] font-bold uppercase tracking-[0.16em] app-muted">Tracking</div>
                <div class="mt-1 text-sm font-bold app-title">{{ request.tracking_number }}</div>
            </div>
        </div>

        <div v-if="(request.match_context?.reasons || []).length" class="mt-4 rounded-2xl px-4 py-3" style="background: var(--app-surface-soft);">
            <div class="text-[11px] font-bold uppercase tracking-[0.16em] app-muted">Why this matches</div>
            <ul class="mt-2 space-y-1 text-sm app-muted">
                <li v-for="reason in request.match_context.reasons.slice(0, 3)" :key="reason">{{ reason }}</li>
            </ul>
        </div>

        <div class="mt-5 flex flex-wrap gap-3">
            <button type="button" class="app-outline-btn !px-4 !py-3 !text-xs" @click="emit('view', request)">View</button>
            <button v-if="request.can_accept" type="button" class="app-primary-btn !px-4 !py-3 !text-xs" @click="emit('accept', request.id)">Accept</button>
        </div>
    </article>
</template>
