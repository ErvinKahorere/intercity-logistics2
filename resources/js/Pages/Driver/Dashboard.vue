<script setup>
import { Head, Link } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import PageHeader from "@/Components/AppShell/PageHeader.vue";

</script>

<template>
    <Head title="Driver Dashboard" />

    <AuthenticatedLayout>
        <PageHeader eyebrow="Driver workspace" title="Driver Dashboard" description="Monitor your route activity, profile, and parcel workflow.">
            <template #actions>
                <Link :href="route('driver.profile')" class="app-outline-btn">My Profile</Link>
                <Link :href="route('driver.routes')" class="app-primary-btn">Manage Routes</Link>
            </template>
        </PageHeader>

        <section class="grid gap-6 md:grid-cols-3">
            <div class="app-panel p-5 text-center">
                <div class="text-xs font-bold uppercase tracking-[0.18em] app-muted">Profile</div>
                <div class="mt-2 text-3xl font-black app-title">Ready</div>
                <p class="mt-2 text-sm app-muted">Update your public information and route details.</p>
            </div>
            <div class="app-panel p-5 text-center">
                <div class="text-xs font-bold uppercase tracking-[0.18em] app-muted">Routes</div>
                <div class="mt-2 text-3xl font-black app-title">Live</div>
                <p class="mt-2 text-sm app-muted">Manage city coverage and supported cargo types.</p>
            </div>
            <div class="app-panel p-5 text-center">
                <div class="text-xs font-bold uppercase tracking-[0.18em] app-muted">Messages</div>
                <div class="mt-2 text-3xl font-black app-title">Open</div>
                <p class="mt-2 text-sm app-muted">Stay on top of parcel requests and updates.</p>
            </div>
        </section>

        <section class="grid gap-6 lg:grid-cols-3">
            <article class="app-panel p-6">
                <h2 class="text-2xl font-black app-title">Profile & Availability</h2>
                <p class="mt-2 text-sm leading-7 app-muted">Keep your driver profile complete so matching stays accurate.</p>
                <div class="mt-6">
                    <Link :href="route('driver.profile')" class="app-primary-btn">Open Profile</Link>
                </div>
            </article>

            <article class="app-panel p-6">
                <h2 class="text-2xl font-black app-title">Route Coverage</h2>
                <p class="mt-2 text-sm leading-7 app-muted">Add and manage your city routes and parcel categories.</p>
                <div class="mt-6">
                    <Link :href="route('driver.routes')" class="app-primary-btn">Manage Routes</Link>
                </div>
            </article>

            <article class="app-panel p-6">
                <h2 class="text-2xl font-black app-title">Messages</h2>
                <p class="mt-2 text-sm leading-7 app-muted">Review customer conversations and route-related updates.</p>
                <div class="mt-6">
                    <Link :href="route('driver.messages')" class="app-primary-btn">Open Messages</Link>
                </div>
            </article>
        </section>
    </AuthenticatedLayout>
</template>
