<script setup>
import StatusBadge from "@/Components/AppShell/StatusBadge.vue";

const props = defineProps({
    open: {
        type: Boolean,
        default: false,
    },
    request: {
        type: Object,
        default: null,
    },
    busy: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(["close", "accept"]);
</script>

<template>
    <transition enter-active-class="transition duration-200 ease-out" enter-from-class="opacity-0" enter-to-class="opacity-100" leave-active-class="transition duration-150 ease-in" leave-from-class="opacity-100" leave-to-class="opacity-0">
        <div v-if="open && request" class="fixed inset-0 z-50 flex justify-end bg-black/30 backdrop-blur-sm" @click.self="emit('close')">
            <div class="flex h-full w-full max-w-2xl flex-col border-l p-5 sm:p-6" style="border-color: var(--app-border); background: var(--app-bg);">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="text-[11px] font-bold uppercase tracking-[0.18em] app-muted">{{ request.tracking_number }}</p>
                            <StatusBadge :tone="request.urgency_level === 'standard' ? 'neutral' : 'warning'" :label="request.urgency_level.replace('_', ' ')" small />
                        </div>
                        <h2 class="mt-3 text-3xl font-black app-title">{{ request.pickup_location }} -> {{ request.dropoff_location }}</h2>
                        <p class="mt-2 text-sm app-muted">{{ request.package_type }} · {{ request.weight_kg || 0 }} kg · {{ request.load_size }}</p>
                    </div>
                    <button type="button" class="app-icon-button h-11 w-11" @click="emit('close')">×</button>
                </div>

                <div class="mt-6 grid gap-4 md:grid-cols-2">
                    <div class="rounded-[24px] border p-4" style="border-color: var(--app-border); background: var(--app-surface);">
                        <div class="text-[11px] font-bold uppercase tracking-[0.18em] app-muted">Pickup</div>
                        <div class="mt-2 text-lg font-black app-title">{{ request.pickup_location }}</div>
                        <p class="mt-2 text-sm app-muted">{{ request.pickup_address || 'Address pending' }}</p>
                    </div>
                    <div class="rounded-[24px] border p-4" style="border-color: var(--app-border); background: var(--app-surface);">
                        <div class="text-[11px] font-bold uppercase tracking-[0.18em] app-muted">Destination</div>
                        <div class="mt-2 text-lg font-black app-title">{{ request.dropoff_location }}</div>
                        <p class="mt-2 text-sm app-muted">{{ request.dropoff_address || 'Address pending' }}</p>
                    </div>
                    <div class="rounded-[24px] border p-4" style="border-color: var(--app-border); background: var(--app-surface);">
                        <div class="text-[11px] font-bold uppercase tracking-[0.18em] app-muted">Receiver</div>
                        <div class="mt-2 text-lg font-black app-title">{{ request.receiver_name || 'Customer handoff' }}</div>
                        <p class="mt-2 text-sm app-muted">{{ request.receiver_phone || 'Phone pending' }}</p>
                    </div>
                    <div class="rounded-[24px] border p-4" style="border-color: var(--app-border); background: var(--app-surface);">
                        <div class="text-[11px] font-bold uppercase tracking-[0.18em] app-muted">Capability match</div>
                        <div class="mt-2 text-lg font-black app-title">{{ request.match_context?.match_score || 0 }}% fit</div>
                        <p class="mt-2 text-sm app-muted">{{ request.match_context?.route_summary || request.match_context?.route }}</p>
                    </div>
                </div>

                <div class="mt-6 grid gap-4 sm:grid-cols-2">
                    <div class="rounded-[24px] p-4" style="background: var(--app-surface-soft);">
                        <div class="text-[11px] font-bold uppercase tracking-[0.18em] app-muted">Estimate</div>
                        <div class="mt-2 text-2xl font-black app-title">N$ {{ Number(request.estimated_price || 0).toFixed(2) }}</div>
                    </div>
                    <div class="rounded-[24px] p-4" :style="request.client_offer_price ? 'background: rgba(242,201,0,0.18);' : 'background: var(--app-surface-soft);'">
                        <div class="text-[11px] font-bold uppercase tracking-[0.18em] app-muted">Client offer</div>
                        <div class="mt-2 text-2xl font-black app-title">{{ request.client_offer_price ? `N$ ${Number(request.client_offer_price).toFixed(2)}` : 'No offer set' }}</div>
                    </div>
                </div>

                <div class="mt-6 rounded-[24px] border p-4" style="border-color: var(--app-border); background: var(--app-surface);">
                    <div class="text-[11px] font-bold uppercase tracking-[0.18em] app-muted">Notes</div>
                    <p class="mt-2 text-sm leading-7 app-muted">{{ request.notes || 'No extra handling notes.' }}</p>
                </div>

                <div class="mt-auto flex flex-wrap gap-3 pt-6">
                    <button type="button" class="app-outline-btn" @click="emit('close')">Close</button>
                    <button v-if="request.can_accept" type="button" class="app-primary-btn" :disabled="busy" @click="emit('accept', request.id)">{{ busy ? 'Accepting...' : 'Accept job' }}</button>
                </div>
            </div>
        </div>
    </transition>
</template>
