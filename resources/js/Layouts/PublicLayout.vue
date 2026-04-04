<script setup>
import { computed } from "vue";
import { usePage } from "@inertiajs/vue3";
import AppHeader from "@/Components/AppShell/AppHeader.vue";
import AppFooter from "@/Components/AppShell/AppFooter.vue";
import BackToTopButton from "@/Components/AppShell/BackToTopButton.vue";
import ToastContainer from "@/Components/AppShell/ToastContainer.vue";

const props = defineProps({
    contentClass: {
        type: String,
        default: "",
    },
});

const page = usePage();
const user = computed(() => page.props.auth?.user ?? null);
const role = computed(() => String(user.value?.role || "user").toLowerCase());
const isVerified = computed(() => !!user.value?.email_verified_at);
const isAdmin = computed(() => role.value === "admin");
const isDriver = computed(() => role.value === "driver");
const isUser = computed(() => !!user.value && !isAdmin.value && !isDriver.value);

const footerCities = ["Windhoek", "Walvis Bay", "Swakopmund", "Oshakati", "Ondangwa", "Rundu", "Otjiwarongo", "Gobabis", "Keetmanshoop", "Luderitz"];

function safeHref(routeName, fallback = "#", params) {
    try { return route(routeName, params); } catch { return fallback; }
}

function routeExists(routeName) {
    try { return typeof route().has === "function" ? route().has(routeName) : true; } catch { return false; }
}

function userParcelsHref() {
    if (!routeExists("user.parcels.index")) return safeHref("welcome");
    if (isUser.value && !isVerified.value && routeExists("verification.notice")) {
        return safeHref("verification.notice");
    }
    return safeHref("user.parcels.index");
}

const trackHref = computed(() => {
    if (isAdmin.value && routeExists("admin.schedules.index")) return safeHref("admin.schedules.index");
    if (isDriver.value && routeExists("driver.dashboard")) return safeHref("driver.dashboard");
    if (isUser.value) return userParcelsHref();
    if (routeExists("login")) return safeHref("login");
    return safeHref("welcome");
});

const navItems = computed(() => {
    if (isAdmin.value) {
        return [
            { label: "Home", href: safeHref("welcome"), active: route().current("welcome"), icon: "home" },
            { label: "Admin", href: safeHref("dashboard"), active: route().current("dashboard"), icon: "admin" },
            { label: "Users", href: routeExists("admin.users.index") ? safeHref("admin.users.index") : "", active: route().current("admin.users.index"), icon: "users" },
            { label: "Drivers", href: routeExists("admin.drivers.index") ? safeHref("admin.drivers.index") : "", active: route().current("admin.drivers.index"), icon: "drivers" },
        ].filter((item) => item.href);
    }

    if (isDriver.value) {
        return [
            { label: "Home", href: safeHref("welcome"), active: route().current("welcome"), icon: "home" },
            { label: "Dashboard", href: routeExists("driver.dashboard") ? safeHref("driver.dashboard") : "", active: route().current("driver.dashboard"), icon: "dashboard" },
            { label: "Routes", href: routeExists("driver.routes") ? safeHref("driver.routes") : "", active: route().current("driver.routes"), icon: "routes" },
            { label: "Messages", href: routeExists("driver.messages") ? safeHref("driver.messages") : "", active: route().current("driver.messages"), icon: "messages" },
        ].filter((item) => item.href);
    }

    return [
        { label: "Home", href: safeHref("welcome"), active: route().current("welcome"), icon: "home" },
        { label: "Send Parcel", href: user.value ? safeHref("parcel-requests.create") : (routeExists("login") ? safeHref("login") : safeHref("welcome")), active: route().current("parcel-requests.create"), icon: "parcel" },
        { label: "Track", href: trackHref.value, active: route().current("user.parcels.index") || route().current("dashboard"), icon: "track" },
        { label: "Drivers", href: safeHref("find.Driver"), active: route().current("find.Driver") || route().current("driver.detail"), icon: "drivers" },
    ].filter((item) => item.href);
});

const notifications = computed(() => page.props.appNotifications || []);

const flashToasts = computed(() => {
    const items = [];
    if (page.props.flash?.success) items.push({ tone: "success", title: "Success", message: page.props.flash.success });
    if (page.props.flash?.error) items.push({ tone: "error", title: "Something went wrong", message: page.props.flash.error });
    return items;
});


const notificationHref = computed(() => {
    if (isAdmin.value && routeExists("admin.schedules.index")) return safeHref("admin.schedules.index");
    if (isDriver.value && routeExists("driver.messages")) return safeHref("driver.messages");
    if (isUser.value) return userParcelsHref();
    return "";
});

const profileItems = computed(() => {
    const items = [];

    if (isAdmin.value) {
        items.push(
            { label: "My Profile", href: routeExists("profile.edit") ? safeHref("profile.edit") : "", icon: "profile" },
            { label: "Admin Dashboard", href: routeExists("dashboard") ? safeHref("dashboard") : "", icon: "dashboard", highlight: true },
            { label: "Manage Drivers", href: routeExists("admin.drivers.index") ? safeHref("admin.drivers.index") : "", icon: "drivers" },
            { label: "Manage Users", href: routeExists("admin.users.index") ? safeHref("admin.users.index") : "", icon: "users" },
            { label: "Schedules", href: routeExists("admin.schedules.index") ? safeHref("admin.schedules.index") : "", icon: "routes" },
            { label: "News", href: routeExists("admin.news.index") ? safeHref("admin.news.index") : "", icon: "news" },
        );
    } else if (isDriver.value) {
        items.push(
            { label: "My Profile", href: routeExists("driver.profile") ? safeHref("driver.profile") : "", icon: "profile" },
            { label: "Driver Dashboard", href: routeExists("driver.dashboard") ? safeHref("driver.dashboard") : "", icon: "dashboard", highlight: true, suffix: "Live" },
            { label: "My Routes", href: routeExists("driver.routes") ? safeHref("driver.routes") : "", icon: "routes" },
            { label: "Messages", href: routeExists("driver.messages") ? safeHref("driver.messages") : "", icon: "messages" },
            { label: "Schedules", href: routeExists("driver.schedules.index") ? safeHref("driver.schedules.index") : "", icon: "requests" },
        );
    } else {
        items.push(
            { label: "My Profile", href: routeExists("profile.edit") ? safeHref("profile.edit") : "", icon: "profile" },
            { label: "My Parcels", href: userParcelsHref(), icon: "parcels", highlight: true },
            { label: "New Request", href: routeExists("parcel-requests.create") ? safeHref("parcel-requests.create") : "", icon: "requests" },
            { label: "Drivers", href: routeExists("find.Driver") ? safeHref("find.Driver") : "", icon: "drivers" },
            { label: "Settings", href: routeExists("profile.edit") ? safeHref("profile.edit") : "", icon: "settings" },
        );
    }

    items.push({ label: "Logout", href: safeHref("logout"), method: "post", as: "button", icon: "logout", highlight: true });

    return items;
});
</script>

<template>
    <div class="app-shell">
        <AppHeader
            :user="user"
            :nav-items="navItems"
            :profile-items="profileItems"
            :notifications="notifications"
            :notification-href="notificationHref"
            :can-login="route().has('login')"
            :can-register="route().has('register')"
        />
        <ToastContainer :initial-toasts="flashToasts" />
        <BackToTopButton />
        <main :class="contentClass">
            <slot />
        </main>
        <AppFooter :coverage-cities="footerCities" />
    </div>
</template>
