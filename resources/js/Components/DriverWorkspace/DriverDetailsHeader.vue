<script setup>
import { Link } from "@inertiajs/vue3";
import DriverAvailabilityBadge from "@/Components/DriverWorkspace/DriverAvailabilityBadge.vue";
import DriverVerificationBadge from "@/Components/DriverWorkspace/DriverVerificationBadge.vue";
import StatusBadge from "@/Components/AppShell/StatusBadge.vue";

defineProps({
    profile: { type: Object, default: () => ({}) },
    viewerMode: { type: String, default: "customer" },
    actions: { type: Object, default: () => ({}) },
});
</script>

<template>
    <section class="overflow-hidden rounded-[34px] border shadow-[0_24px_80px_rgba(15,23,42,0.08)]" style="border-color: rgba(242,201,0,0.35); background: linear-gradient(135deg, rgba(242,201,0,0.94), rgba(255,244,204,0.96) 52%, rgba(255,255,255,0.98) 100%);">
        <div class="grid gap-6 p-6 sm:p-8 lg:grid-cols-[0.9fr_1.1fr] lg:items-end">
            <div class="flex items-start gap-4">
                <div class="rounded-[30px] p-1.5" style="background: linear-gradient(145deg, rgba(255,255,255,0.88), rgba(242,201,0,0.58));">
                    <img v-if="profile.image" :src="profile.image" :alt="profile.name" class="h-24 w-24 rounded-[24px] object-cover sm:h-28 sm:w-28" />
                    <div v-else class="flex h-24 w-24 items-center justify-center rounded-[24px] text-3xl font-black sm:h-28 sm:w-28" style="background: rgba(255,255,255,0.7); color: #1F1F1F;">
                        {{ (profile.name || "D").slice(0, 1) }}
                    </div>
                </div>

                <div class="min-w-0">
                    <div class="flex flex-wrap gap-2">
                        <DriverVerificationBadge :status="profile.verification_status" />
                        <DriverAvailabilityBadge :status="profile.available ? 'Online' : 'Busy'" />
                        <StatusBadge :label="profile.trust_label || 'Trusted route partner'" tone="info" />
                    </div>
                    <h1 class="mt-4 text-4xl font-black" style="color: #1F1F1F;">{{ profile.name }}</h1>
                    <p class="mt-2 text-base">{{ profile.designation }} · {{ profile.vehicle_label || "Route driver" }}</p>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <span class="rounded-full border px-3 py-1.5 text-[11px] font-bold uppercase tracking-[0.14em]" style="border-color: rgba(31,31,31,0.08); background: rgba(255,255,255,0.72); color:#1F1F1F;">
                            Trust {{ profile.trust_score || 0 }}/100
                        </span>
                        <span class="rounded-full border px-3 py-1.5 text-[11px] font-bold uppercase tracking-[0.14em]" style="border-color: rgba(31,31,31,0.08); background: rgba(255,255,255,0.72); color:#1F1F1F;">
                            {{ profile.active_workload || 0 }} active jobs
                        </span>
                    </div>
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-[24px] border px-4 py-4" style="border-color: rgba(31,31,31,0.08); background: rgba(255,255,255,0.72);">
                    <div class="text-[11px] font-bold uppercase tracking-[0.16em]" style="color: rgba(31,31,31,0.56);">Home base</div>
                    <div class="mt-2 text-sm font-black" style="color: #1F1F1F;">{{ profile.home_base || "Namibia network" }}</div>
                </div>
                <div class="rounded-[24px] border px-4 py-4" style="border-color: rgba(31,31,31,0.08); background: rgba(255,255,255,0.72);">
                    <div class="text-[11px] font-bold uppercase tracking-[0.16em]" style="color: rgba(31,31,31,0.56);">Route focus</div>
                    <div class="mt-2 text-sm font-black" style="color: #1F1F1F;">{{ profile.route_summary || "Route coverage updating" }}</div>
                </div>
                <div class="rounded-[24px] border px-4 py-4" style="border-color: rgba(31,31,31,0.08); background: rgba(255,255,255,0.72);">
                    <div class="text-[11px] font-bold uppercase tracking-[0.16em]" style="color: rgba(31,31,31,0.56);">Trust score</div>
                    <div class="mt-2 text-sm font-black" style="color: #1F1F1F;">{{ profile.trust_score || 0 }} / 100</div>
                </div>
                <div class="rounded-[24px] border px-4 py-4" style="border-color: rgba(31,31,31,0.08); background: rgba(255,255,255,0.72);">
                    <div class="text-[11px] font-bold uppercase tracking-[0.16em]" style="color: rgba(31,31,31,0.56);">Active workload</div>
                    <div class="mt-2 text-sm font-black" style="color: #1F1F1F;">{{ profile.active_workload || 0 }} jobs</div>
                </div>
            </div>
        </div>

        <div class="flex flex-wrap gap-3 border-t px-6 py-4 sm:px-8" style="border-color: rgba(31,31,31,0.08); background: rgba(255,255,255,0.74);">
            <Link v-if="actions.can_select" :href="route('find.Driver', { select: profile.id })" class="app-primary-btn">Select driver</Link>
            <Link v-if="actions.can_review" :href="route('admin.verification.index')" class="app-primary-btn">Open verification review</Link>
            <Link v-if="actions.can_edit" :href="route('driver.profile')" class="app-primary-btn">Edit profile</Link>
            <Link v-if="actions.can_edit" :href="route('driver.profile') + '#verification'" class="app-outline-btn">Compliance</Link>
            <Link v-if="actions.can_edit" :href="route('driver.profile') + '#banking'" class="app-outline-btn">Banking</Link>
        </div>
    </section>
</template>
