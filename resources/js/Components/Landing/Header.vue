<script setup>
import { computed } from "vue";
import { usePage } from "@inertiajs/vue3";
import AppHeader from "@/Components/AppShell/AppHeader.vue";

const props = defineProps({
    canLogin: Boolean,
    canRegister: Boolean,
});

const page = usePage();
const user = computed(() => page.props.auth?.user ?? null);
const navItems = [
    { label: "Home", href: route("welcome"), active: route().current("welcome"), icon: "home" },
    { label: "Find Drivers", href: route("find.Driver"), active: route().current("find.Driver"), icon: "search" },
    { label: "Book Parcel", href: route("parcel-requests.create"), active: route().current("parcel-requests.create"), icon: "parcel" },
];

const profileItems = [
    { label: "My Profile", href: user.value ? route("profile.edit") : "#", icon: "profile" },
    { label: "My Parcels", href: route().has("user.parcels.index") ? route("user.parcels.index") : "#", icon: "parcels" },
    { label: "My Requests", href: route().has("parcel-requests.create") ? route("parcel-requests.create") : "#", icon: "requests" },
    { label: "Driver Dashboard", href: route().has("driver.dashboard") ? route("driver.dashboard") : "#", icon: "dashboard" },
    { label: "Notifications", href: "#", icon: "bell" },
    { label: "Settings", href: user.value ? route("profile.edit") : "#", icon: "settings" },
    { label: "Help", href: "#", icon: "help" },
    { label: "Logout", href: route().has("logout") ? route("logout") : "#", method: "post", as: "button", icon: "logout", highlight: true },
];

const notifications = [
    { id: 1, icon: "DM", title: "Driver matched for your request", message: "A driver is available for your selected route.", time: "5 mins ago", badge: "Match", tone: "success", read: false },
    { id: 2, icon: "PT", title: "Parcel is now in transit", message: "Tracking has been updated for a recent parcel.", time: "18 mins ago", badge: "Transit", tone: "info", read: false },
];
</script>

<template>
    <AppHeader
        :user="user"
        :nav-items="navItems"
        :profile-items="profileItems"
        :notifications="notifications"
        :can-login="canLogin"
        :can-register="canRegister"
    />
</template>
