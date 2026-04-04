<script setup>
import { Head, Link } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import PageHeader from "@/Components/AppShell/PageHeader.vue";
import EmptyState from "@/Components/AppShell/EmptyState.vue";

const { authUser, savedDrivers } = defineProps({
    authUser: Object,
    savedDrivers: Array,
});
</script>

<template>
    <Head title="User Dashboard" />

    <AuthenticatedLayout>
        <PageHeader eyebrow="User workspace" :title="`Welcome, ${authUser?.name || 'User'}`" description="Manage your profile, review saved drivers, and continue parcel activity from one place.">
            <template #actions>
                <Link :href="route('user.profile')" class="app-outline-btn">Edit Profile</Link>
                <Link :href="route('find.Driver')" class="app-primary-btn">Find Drivers</Link>
            </template>
        </PageHeader>

        <section class="grid gap-6 lg:grid-cols-[0.95fr_1.05fr]">
            <div class="app-panel p-6">
                <div class="flex items-center gap-4">
                    <img v-if="authUser?.profile_photo_url" :src="authUser.profile_photo_url" alt="Profile Photo" class="h-20 w-20 rounded-full object-cover" />
                    <div v-else class="flex h-20 w-20 items-center justify-center rounded-full text-3xl font-black" style="background: #2F2E7C; color: #FFFFFF;">
                        {{ authUser?.name?.charAt(0) || 'U' }}
                    </div>
                    <div>
                        <h2 class="text-2xl font-black app-title">{{ authUser?.name || 'No Name' }}</h2>
                        <p class="text-sm app-muted">{{ authUser?.email || 'No email added' }}</p>
                        <p class="text-sm app-muted">{{ authUser?.phone || 'No phone number added' }}</p>
                    </div>
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-3">
                <div class="app-panel p-5 text-center">
                    <div class="text-xs font-bold uppercase tracking-[0.18em] app-muted">Saved Drivers</div>
                    <div class="mt-2 text-3xl font-black app-title">{{ savedDrivers?.length || 0 }}</div>
                </div>
                <div class="app-panel p-5 text-center">
                    <div class="text-xs font-bold uppercase tracking-[0.18em] app-muted">Profile Status</div>
                    <div class="mt-2 text-3xl font-black app-title">Active</div>
                </div>
                <div class="app-panel p-5 text-center">
                    <div class="text-xs font-bold uppercase tracking-[0.18em] app-muted">Next Step</div>
                    <div class="mt-2 text-lg font-black app-title">Book a parcel</div>
                </div>
            </div>
        </section>

        <section class="app-panel p-6">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h2 class="text-2xl font-black app-title">Saved Drivers</h2>
                    <p class="mt-1 text-sm app-muted">Quick access to the drivers you have saved.</p>
                </div>
                <Link :href="route('find.Driver')" class="app-outline-btn">Browse Drivers</Link>
            </div>

            <div v-if="savedDrivers?.length" class="mt-6 grid gap-4">
                <div v-for="driver in savedDrivers" :key="driver?.id" class="flex flex-col gap-4 rounded-[24px] border p-4 sm:flex-row sm:items-center sm:justify-between" style="border-color: var(--app-border); background: var(--app-surface-soft);">
                    <div class="flex items-center gap-4">
                        <img v-if="driver?.user?.profile_photo_url" :src="driver.user.profile_photo_url" alt="Driver Photo" class="h-14 w-14 rounded-full object-cover" />
                        <div v-else class="flex h-14 w-14 items-center justify-center rounded-full font-black" style="background: #2F2E7C; color: #FFFFFF;">
                            {{ driver?.user?.name?.charAt(0) || 'D' }}
                        </div>
                        <div>
                            <p class="font-bold app-title">{{ driver?.user?.name || 'Unnamed Driver' }}</p>
                            <p class="text-sm app-muted">{{ driver?.user?.location || 'Location not set' }}</p>
                        </div>
                    </div>
                    <Link :href="`/drivers/${driver?.id}`" class="app-primary-btn">View Driver</Link>
                </div>
            </div>

            <div v-else class="mt-6">
                <EmptyState title="No saved drivers yet" description="Save useful driver contacts so you can reach them faster later." icon="D" />
            </div>
        </section>
    </AuthenticatedLayout>
</template>
