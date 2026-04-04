<script setup>
import { Link } from "@inertiajs/vue3";
import AppHeader from "@/Components/AppShell/AppHeader.vue";
import AppFooter from "@/Components/AppShell/AppFooter.vue";
import BackToTopButton from "@/Components/AppShell/BackToTopButton.vue";

const footerCities = ["Windhoek", "Walvis Bay", "Swakopmund", "Oshakati", "Ondangwa", "Rundu", "Otjiwarongo", "Gobabis", "Keetmanshoop", "Luderitz"];
const navItems = [
    { label: "Home", href: route("welcome"), active: route().current("welcome"), icon: "home" },
    { label: "Find Drivers", href: route("find.Driver"), active: route().current("find.Driver"), icon: "search" },
    { label: "Book Parcel", href: route("parcel-requests.create"), active: route().current("parcel-requests.create"), icon: "parcel" },
];
</script>

<template>
    <div class="app-shell">
        <AppHeader :nav-items="navItems" :can-login="route().has('login')" :can-register="route().has('register')" />
        <BackToTopButton />
        <div class="mx-auto flex min-h-[calc(100vh-14rem)] w-full max-w-md flex-col justify-center px-4 py-10 sm:px-6">
            <div class="app-panel overflow-hidden rounded-[24px] px-6 py-6 sm:px-8">
                <slot />
            </div>
        </div>
        <AppFooter :coverage-cities="footerCities" />
    </div>
</template>
