<script setup>
import { computed, ref, watch } from "vue";
import { Link, usePage } from "@inertiajs/vue3";
import AppHeader from "@/Components/AppShell/AppHeader.vue";
import AppFooter from "@/Components/AppShell/AppFooter.vue";
import ToastContainer from "@/Components/AppShell/ToastContainer.vue";
import BackToTopButton from "@/Components/AppShell/BackToTopButton.vue";
import { useAppRefreshListener, usePolling } from "@/composables/useLivePage";
import api from "@/lib/api";

defineProps({
    title: {
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
const isUser = computed(() => !isAdmin.value && !isDriver.value);
const footerCities = ["Windhoek", "Walvis Bay", "Swakopmund", "Oshakati", "Ondangwa", "Rundu", "Otjiwarongo", "Gobabis", "Keetmanshoop", "Luderitz"];

function safeHref(routeName, fallback = "#", params) {
    try { return route(routeName, params); } catch { return fallback; }
}

function routeExists(routeName) {
    try { return typeof route().has === "function" ? route().has(routeName) : true; } catch { return false; }
}

function normalizeMenuItems(items = []) {
    return (Array.isArray(items) ? items : [])
        .filter((item) => item && typeof item === "object")
        .map((item) => ({
            ...item,
            href: typeof item.href === "string" ? item.href.trim() : "",
            label: item.label || "Open",
        }))
        .filter((item) => item.href && item.href !== "#");
}

function userParcelsHref() {
    if (!routeExists("user.parcels.index")) return safeHref("dashboard");
    if (isUser.value && !isVerified.value && routeExists("verification.notice")) {
        return safeHref("verification.notice");
    }
    return safeHref("user.parcels.index");
}

const primaryNav = computed(() => {
    if (isAdmin.value) {
        return normalizeMenuItems([
            { label: "Home", href: safeHref("welcome"), active: route().current("welcome"), icon: "home" },
            { label: "Admin", href: safeHref("dashboard"), active: route().current("dashboard"), icon: "admin" },
            { label: "Verification", href: routeExists("admin.verification.index") ? safeHref("admin.verification.index") : "", active: route().current("admin.verification.*"), icon: "drivers" },
            { label: "Routes", href: routeExists("admin.routes.index") ? safeHref("admin.routes.index") : "", active: route().current("admin.routes.*"), icon: "routes" },
            { label: "Pricing", href: routeExists("admin.pricing.index") ? safeHref("admin.pricing.index") : "", active: route().current("admin.pricing.*"), icon: "parcel" },
        ]);
    }

    if (isDriver.value) {
        return normalizeMenuItems([
            { label: "Home", href: routeExists("driver.home") ? safeHref("driver.home") : safeHref("driver.dashboard"), active: route().current("driver.home"), icon: "home" },
            { label: "Dashboard", href: routeExists("driver.dashboard") ? safeHref("driver.dashboard") : "", active: route().current("driver.dashboard"), icon: "dashboard" },
            { label: "Routes", href: routeExists("driver.routes") ? safeHref("driver.routes") : "", active: route().current("driver.routes"), icon: "routes" },
            { label: "Messages", href: routeExists("driver.messages") ? safeHref("driver.messages") : "", active: route().current("driver.messages"), icon: "messages" },
        ]);
    }

    return normalizeMenuItems([
        { label: "Home", href: safeHref("welcome"), active: route().current("welcome"), icon: "home" },
        { label: "Send Parcel", href: routeExists("parcel-requests.create") ? safeHref("parcel-requests.create") : "", active: route().current("parcel-requests.create"), icon: "parcel" },
        { label: "Track", href: userParcelsHref(), active: route().current("user.parcels.index") || route().current("dashboard") || route().current("verification.notice"), icon: "track" },
        { label: "Drivers", href: routeExists("find.Driver") ? safeHref("find.Driver") : "", active: route().current("find.Driver") || route().current("driver.detail"), icon: "drivers" },
    ]);
});

const sidebarItems = computed(() => {
    if (isAdmin.value) {
        return normalizeMenuItems([
            { label: "Overview", href: safeHref("dashboard"), active: route().current("dashboard"), icon: "OV" },
            { label: "Verification", href: routeExists("admin.verification.index") ? safeHref("admin.verification.index") : "", active: route().current("admin.verification.*"), icon: "VR" },
            { label: "Routes", href: routeExists("admin.routes.index") ? safeHref("admin.routes.index") : "", active: route().current("admin.routes.*"), icon: "RT" },
            { label: "Pricing", href: routeExists("admin.pricing.index") ? safeHref("admin.pricing.index") : "", active: route().current("admin.pricing.*"), icon: "PR" },
            { label: "Quotations", href: routeExists("admin.quotations.index") ? safeHref("admin.quotations.index") : "", active: route().current("admin.quotations.*"), icon: "QT" },
            { label: "Invoices", href: routeExists("admin.invoices.index") ? safeHref("admin.invoices.index") : "", active: route().current("admin.invoices.*"), icon: "IV" },
            { label: "SMS Logs", href: routeExists("admin.sms-logs.index") ? safeHref("admin.sms-logs.index") : "", active: route().current("admin.sms-logs.*"), icon: "SM" },
            { label: "Requests", href: routeExists("admin.requests.index") ? safeHref("admin.requests.index") : "", active: route().current("admin.requests.*"), icon: "RQ" },
            { label: "Drivers", href: safeHref("admin.drivers.index"), active: route().current("admin.drivers.index"), icon: "DR" },
            { label: "Users", href: safeHref("admin.users.index"), active: route().current("admin.users.index"), icon: "US" },
            { label: "Schedules", href: safeHref("admin.schedules.index"), active: route().current("admin.schedules.index"), icon: "SC" },
            { label: "News", href: routeExists("admin.news.index") ? safeHref("admin.news.index") : "", active: route().current("admin.news.index"), icon: "NW" },
        ]);
    }
    if (isDriver.value) {
        return normalizeMenuItems([
            { label: "Home", href: routeExists("driver.home") ? safeHref("driver.home") : safeHref("driver.dashboard"), active: route().current("driver.home"), icon: "HM" },
            { label: "Overview", href: routeExists("driver.dashboard") ? safeHref("driver.dashboard") : "", active: route().current("driver.dashboard"), icon: "OV" },
            { label: "My Routes", href: routeExists("driver.routes") ? safeHref("driver.routes") : "", active: route().current("driver.routes"), icon: "RT" },
            { label: "Messages", href: routeExists("driver.messages") ? safeHref("driver.messages") : "", active: route().current("driver.messages"), icon: "MS" },
            { label: "Schedules", href: routeExists("driver.schedules.index") ? safeHref("driver.schedules.index") : "", active: route().current("driver.schedules.index"), icon: "SC" },
            { label: "Profile", href: routeExists("driver.profile") ? safeHref("driver.profile") : "", active: route().current("driver.profile"), icon: "PR" },
            { label: "Banking", href: routeExists("driver.profile") ? `${safeHref("driver.profile")}#banking` : "", active: false, icon: "BK" },
            { label: "Verification", href: routeExists("driver.profile") ? `${safeHref("driver.profile")}#verification` : "", active: false, icon: "VR" },
        ]);
    }
    return [];
});

const localNotifications = ref(Array.isArray(page.props.appNotifications) ? page.props.appNotifications : []);

watch(() => page.props.appNotifications, (value) => {
    localNotifications.value = Array.isArray(value) ? value : [];
}, { immediate: true, deep: true });

async function refreshNotifications() {
    if (!user.value) return;
    try {
        const { data } = await api.get('/api/notifications');
        localNotifications.value = Array.isArray(data?.notifications) ? data.notifications : [];
    } catch (error) {
        // keep current notifications if refresh fails
    }
}

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
            { label: "Verification Queue", href: routeExists("admin.verification.index") ? safeHref("admin.verification.index") : "", icon: "drivers" },
            { label: "Routes", href: routeExists("admin.routes.index") ? safeHref("admin.routes.index") : "", icon: "routes" },
            { label: "Pricing", href: routeExists("admin.pricing.index") ? safeHref("admin.pricing.index") : "", icon: "requests" },
            { label: "Quotations", href: routeExists("admin.quotations.index") ? safeHref("admin.quotations.index") : "", icon: "news" },
            { label: "Invoices", href: routeExists("admin.invoices.index") ? safeHref("admin.invoices.index") : "", icon: "messages" },
            { label: "SMS Logs", href: routeExists("admin.sms-logs.index") ? safeHref("admin.sms-logs.index") : "", icon: "messages" },
            { label: "Requests", href: routeExists("admin.requests.index") ? safeHref("admin.requests.index") : "", icon: "parcels" },
            { label: "Manage Drivers", href: routeExists("admin.drivers.index") ? safeHref("admin.drivers.index") : "", icon: "drivers" },
            { label: "Manage Users", href: routeExists("admin.users.index") ? safeHref("admin.users.index") : "", icon: "users" },
            { label: "Schedules", href: routeExists("admin.schedules.index") ? safeHref("admin.schedules.index") : "", icon: "routes" },
            { label: "News", href: routeExists("admin.news.index") ? safeHref("admin.news.index") : "", icon: "news" },
        );
    } else if (isDriver.value) {
        items.push(
            { label: "Driver Home", href: routeExists("driver.home") ? safeHref("driver.home") : safeHref("driver.dashboard"), icon: "dashboard", highlight: true, suffix: "Live" },
            { label: "My Profile", href: routeExists("driver.profile") ? safeHref("driver.profile") : "", icon: "profile" },
            { label: "Driver Dashboard", href: routeExists("driver.dashboard") ? safeHref("driver.dashboard") : "", icon: "routes" },
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
    return normalizeMenuItems(items);
});

const flashToasts = computed(() => {
    const items = [];
    if (page.props.flash?.success) items.push({ tone: "success", title: "Success", message: page.props.flash.success });
    if (page.props.flash?.error) items.push({ tone: "error", title: "Something went wrong", message: page.props.flash.error });
    return items;
});

useAppRefreshListener(({ only = ["appNotifications"] } = {}) => {
    if (only.includes("appNotifications")) {
        refreshNotifications();
    }
});

usePolling(() => refreshNotifications(), 20000, {
    enabled: () => !!user.value,
});
</script>

<template>
    <div class="app-shell">
        <AppHeader :user="user" :nav-items="primaryNav" :profile-items="profileItems" :notifications="localNotifications" :notification-href="notificationHref" />
        <ToastContainer :initial-toasts="flashToasts" />
        <BackToTopButton />

        <div class="flex w-full gap-6 px-4 py-6 sm:px-6 lg:px-10 2xl:px-12">
            <aside v-if="sidebarItems.length" class="sticky top-24 hidden h-[calc(100vh-7rem)] w-80 shrink-0 xl:block">
                <div class="app-panel flex h-full flex-col rounded-[24px] p-5">
                    <div class="border-b pb-5" style="border-color: var(--app-border);">
                        <p class="text-[11px] font-bold uppercase tracking-[0.24em]" style="color: #2F2E7C;">{{ isAdmin ? "Admin workspace" : "Driver workspace" }}</p>
                        <h2 class="mt-2 text-2xl font-black app-title">{{ user?.name }}</h2>
                        <p class="mt-1 text-sm app-muted">{{ isAdmin ? "Manage people, routes, and operations." : "Track loads, alerts, and parcel work." }}</p>
                    </div>
                    <nav class="mt-5 flex-1 space-y-2">
                        <Link v-for="item in sidebarItems" :key="item.label" :href="item.href" class="app-nav-link !flex !justify-start !rounded-2xl !px-4 !py-3" :class="item.active ? 'app-nav-link-active' : ''">
                            <span class="flex h-10 w-10 items-center justify-center rounded-2xl" :style="item.active ? 'background: rgba(255,255,255,0.14);' : 'background: var(--app-surface-soft);'">{{ item.icon }}</span>
                            {{ item.label }}
                        </Link>
                    </nav>
                    <Link :href="safeHref('logout')" method="post" as="button" class="app-outline-btn mt-4 !justify-start">
                        Logout
                    </Link>
                </div>
            </aside>

            <div class="min-w-0 flex-1">
                <main class="space-y-6">
                    <slot />
                </main>
            </div>
        </div>

        <AppFooter :coverage-cities="footerCities" />
    </div>
</template>
