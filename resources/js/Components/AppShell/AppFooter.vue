<script setup>
import Logo from "@/assets/images/logo/logo.png";

defineProps({
    coverageCities: {
        type: Array,
        default: () => [],
    },
});
</script>

<template>
    <footer class="app-footer mt-12">
        <div class="w-full px-4 py-10 sm:px-6 lg:px-10 2xl:px-12">
            <div class="grid gap-8 lg:grid-cols-[1.2fr_0.8fr_1fr_0.9fr]">
                <div class="space-y-5">
                    <div class="flex items-center gap-3">
                        <img :src="Logo" alt="InterCity Logistics logo" class="h-14 w-auto object-contain sm:h-16" />
                        <h4 class="text-xl font-black tracking-tight app-title sm:text-2xl">InterCity Logistics</h4>
                    </div>
                    <a :href="route('driver.register')" class="app-secondary-btn">Driver Signup</a>
                </div>

                <div>
                    <h5 class="text-sm font-bold uppercase tracking-[0.22em] app-title">Links</h5>
                    <ul class="mt-4 space-y-3 text-sm app-muted">
                        <li><a href="/" class="transition hover:text-[#2F2E7C]">Home</a></li>
                        <li><a href="/parcel-request" class="transition hover:text-[#2F2E7C]">Send Parcel</a></li>
                        <li><a href="/find-driver" class="transition hover:text-[#2F2E7C]">Drivers</a></li>
                        <li><a href="/login" class="transition hover:text-[#2F2E7C]">Track</a></li>
                    </ul>
                </div>

                <div>
                    <h5 class="text-sm font-bold uppercase tracking-[0.22em] app-title">Cities</h5>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <span v-for="city in coverageCities" :key="city" class="rounded-full px-3 py-2 text-xs font-bold uppercase tracking-[0.12em]" style="background: var(--app-surface-soft); color: var(--app-text); border: 1px solid var(--app-border);">
                            {{ city }}
                        </span>
                    </div>
                </div>

                <div>
                    <h5 class="text-sm font-bold uppercase tracking-[0.22em] app-title">Contact</h5>
                    <ul class="mt-4 space-y-3 text-sm app-muted">
                        <li>Windhoek support</li>
                        <li>support@intercity.test</li>
                        <li>+264 81 000 0000</li>
                    </ul>
                </div>
            </div>

            <div class="mt-8 flex flex-col gap-3 border-t pt-5 text-sm app-muted sm:flex-row sm:items-center sm:justify-between" style="border-color: var(--app-border);">
                <p>&copy; {{ new Date().getFullYear() }} InterCity Logistics</p>
                <div class="flex gap-4">
                    <a href="#" class="transition hover:text-[#2F2E7C]">Privacy</a>
                    <a href="#" class="transition hover:text-[#2F2E7C]">Terms</a>
                </div>
            </div>
        </div>
    </footer>
</template>
