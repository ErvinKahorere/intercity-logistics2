<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from "vue";
import { Link } from "@inertiajs/vue3";
import Logo from "@/assets/images/logo/logo.png";
import NotificationBell from "@/Components/AppShell/NotificationBell.vue";
import ProfileDropdown from "@/Components/AppShell/ProfileDropdown.vue";

const props = defineProps({
    user: {
        type: Object,
        default: null,
    },
    navItems: {
        type: Array,
        default: () => [],
    },
    profileItems: {
        type: Array,
        default: () => [],
    },
    notifications: {
        type: Array,
        default: () => [],
    },
    notificationHref: {
        type: String,
        default: "",
    },
    canLogin: {
        type: Boolean,
        default: false,
    },
    canRegister: {
        type: Boolean,
        default: false,
    },
});

const mobileMenuOpen = ref(false);
const mobileRoot = ref(null);
const isDark = ref(false);

function normalizeLinkItems(items = []) {
    return (Array.isArray(items) ? items : [])
        .filter((item) => item && typeof item === "object")
        .map((item) => ({
            ...item,
            href: typeof item.href === "string" ? item.href.trim() : "",
            label: item.label || "Open",
        }))
        .filter((item) => item.href && item.href !== "#");
}

const visibleNavItems = computed(() => normalizeLinkItems(props.navItems).slice(0, 4));
const brandHref = computed(() => {
    try {
        return route("welcome");
    } catch {
        return "/";
    }
});

const iconMap = {
    home: '<svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 11.5 12 4l9 7.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z" /></svg>',
    parcel: '<svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="m3 7 9-4 9 4-9 4-9-4Zm0 0v10l9 4 9-4V7" /></svg>',
    track: '<svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M5 19a2 2 0 1 1 0-4 2 2 0 0 1 0 4Zm14-10a2 2 0 1 1 0-4 2 2 0 0 1 0 4ZM7 17h4a4 4 0 0 0 4-4V9" /></svg>',
    drivers: '<svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M1 5h15v11H1zM16 8h3l4 4v4h-7zM5.5 18.5a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3Zm13 0a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3Z" /></svg>',
    dashboard: '<svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 13h7V4H4v9Zm9 7h7V4h-7v16ZM4 20h7v-5H4v5Z" /></svg>',
    routes: '<svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M5 19a2 2 0 1 1 0-4 2 2 0 0 1 0 4Zm14-10a2 2 0 1 1 0-4 2 2 0 0 1 0 4ZM7 17h4a4 4 0 0 0 4-4V9" /></svg>',
    messages: '<svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M7 10h10M7 14h6M5 4h14a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H9l-4 3v-3H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z" /></svg>',
    users: '<svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16 11a4 4 0 1 0-4-4 4 4 0 0 0 4 4Zm-8 8a4 4 0 0 1 8 0v1H8Zm10 1v-1a5.98 5.98 0 0 0-1.6-4.09A4 4 0 0 1 20 18v2Z" /></svg>',
    news: '<svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 5h12v14H6zM8 8h8M8 12h8M8 16h5" /></svg>',
    admin: '<svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 3 4 7v6c0 5 3.4 8 8 8s8-3 8-8V7l-8-4Zm0 6v4m0 4h.01" /></svg>',
};

function iconSvg(icon) {
    return iconMap[icon] || iconMap.home;
}

function closeMenu() {
    mobileMenuOpen.value = false;
}

function handleClickOutside(event) {
    if (mobileRoot.value && !mobileRoot.value.contains(event.target)) {
        mobileMenuOpen.value = false;
    }
}

function applyTheme() {
    document.documentElement.classList.toggle("theme-dark", isDark.value);
    localStorage.setItem("intercity-theme", isDark.value ? "dark" : "light");
}

function toggleTheme() {
    isDark.value = !isDark.value;
    applyTheme();
}

onMounted(() => {
    const storedTheme = localStorage.getItem("intercity-theme") || localStorage.getItem("parcella-theme");
    isDark.value = storedTheme === "dark";
    applyTheme();
    document.addEventListener("click", handleClickOutside);
});

onBeforeUnmount(() => document.removeEventListener("click", handleClickOutside));
</script>

<template>
    <header class="app-header">
        <div ref="mobileRoot" class="relative flex w-full items-center justify-between gap-4 px-4 py-4 sm:px-6 lg:px-10 2xl:px-12">
            <div class="flex min-w-0 items-center gap-4 lg:gap-6">
                <Link :href="brandHref" class="flex min-w-0 items-center gap-3">
                    <img :src="Logo" alt="InterCity Logistics logo" class="h-14 w-auto object-contain sm:h-16 lg:h-[4.5rem]" />
                    <span class="truncate text-base font-black tracking-tight app-title sm:text-lg lg:text-xl">InterCity Logistics</span>
                </Link>

                <nav class="hidden items-center gap-1 xl:flex">
                    <Link
                        v-for="item in visibleNavItems"
                        :key="item.label"
                        :href="item.href"
                        class="app-nav-link"
                        :class="item.active ? 'app-nav-link-active' : ''"
                    >
                        <span class="shrink-0" v-html="iconSvg(item.icon)"></span>
                        {{ item.label }}
                    </Link>
                </nav>
            </div>

            <div class="flex shrink-0 items-center gap-2 sm:gap-3">
                <button type="button" class="app-icon-button h-11 w-11" @click="toggleTheme">
                    <svg v-if="isDark" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 3v2.5M12 18.5V21M4.93 4.93l1.77 1.77M17.3 17.3l1.77 1.77M3 12h2.5M18.5 12H21M4.93 19.07l1.77-1.77M17.3 6.7l1.77-1.77M12 16a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z" /></svg>
                    <svg v-else class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M21 12.79A9 9 0 1 1 11.21 3c0 .16-.01.32-.01.49A7.5 7.5 0 0 0 20.51 12c.17 0 .33-.01.49-.01Z" /></svg>
                </button>
                <div v-if="user" class="hidden lg:block">
                    <NotificationBell :notifications="notifications" :view-all-href="notificationHref" />
                </div>
                <ProfileDropdown v-if="user" :user="user" :items="profileItems" />
                <template v-else>
                    <Link v-if="canLogin" :href="route('login')" class="app-outline-btn hidden md:inline-flex">Sign In</Link>
                    <Link v-if="canRegister" :href="route('register')" class="app-primary-btn hidden md:inline-flex">Create Account</Link>
                </template>
                <button
                    type="button"
                    class="app-icon-button h-11 w-11 xl:hidden"
                    @click.stop="mobileMenuOpen = !mobileMenuOpen"
                >
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" :d="mobileMenuOpen ? 'M6 18L18 6M6 6l12 12' : 'M4 6h16M4 12h16M4 18h16'" />
                    </svg>
                </button>
            </div>

            <transition enter-active-class="transition duration-200 ease-out" enter-from-class="translate-y-2 opacity-0" enter-to-class="translate-y-0 opacity-100" leave-active-class="transition duration-150 ease-in" leave-from-class="translate-y-0 opacity-100" leave-to-class="translate-y-2 opacity-0">
                <div v-if="mobileMenuOpen" class="app-dropdown absolute inset-x-4 top-[calc(100%-0.25rem)] z-50 rounded-[28px] p-4 xl:hidden sm:inset-x-6">
                    <div class="grid gap-2">
                        <Link
                            v-for="item in visibleNavItems"
                            :key="`${item.label}-mobile`"
                            :href="item.href"
                            class="app-nav-link !justify-start !rounded-2xl"
                            :class="item.active ? 'app-nav-link-active' : ''"
                            @click="closeMenu"
                        >
                            <span class="shrink-0" v-html="iconSvg(item.icon)"></span>
                            {{ item.label }}
                        </Link>
                        <Link v-if="!user && canLogin" :href="route('login')" class="app-outline-btn !justify-start" @click="closeMenu">Sign In</Link>
                        <Link v-if="!user && canRegister" :href="route('register')" class="app-primary-btn !justify-start" @click="closeMenu">Create Account</Link>
                    </div>
                    <div v-if="user" class="mt-4 border-t pt-4" style="border-color: var(--app-border);">
                        <NotificationBell :notifications="notifications" :view-all-href="notificationHref" />
                    </div>
                </div>
            </transition>
        </div>
    </header>
</template>

