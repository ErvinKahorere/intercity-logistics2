<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from "vue";
import { Link } from "@inertiajs/vue3";

const props = defineProps({
    user: {
        type: Object,
        default: null,
    },
    items: {
        type: Array,
        default: () => [],
    },
});

const open = ref(false);
const root = ref(null);
const initials = computed(() =>
    (props.user?.name || "U")
        .split(" ")
        .slice(0, 2)
        .map((part) => part.charAt(0))
        .join("")
        .toUpperCase()
);
const visibleItems = computed(() => props.items.filter((item) => item?.href));

const iconMap = {
    profile: '<svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 12a4 4 0 1 0-4-4 4 4 0 0 0 4 4Zm0 2c-4.42 0-8 2.24-8 5v1h16v-1c0-2.76-3.58-5-8-5Z" /></svg>',
    parcels: '<svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="m3 7 9-4 9 4-9 4-9-4Zm0 0v10l9 4 9-4V7" /></svg>',
    requests: '<svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 6h8M8 12h8M8 18h5M5 4h14v16H5z" /></svg>',
    dashboard: '<svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 13h7V4H4v9Zm9 7h7V4h-7v16ZM4 20h7v-5H4v5Z" /></svg>',
    drivers: '<svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M1 5h15v11H1zM16 8h3l4 4v4h-7zM5.5 18.5a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3Zm13 0a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3Z" /></svg>',
    routes: '<svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M5 19a2 2 0 1 1 0-4 2 2 0 0 1 0 4Zm14-10a2 2 0 1 1 0-4 2 2 0 0 1 0 4ZM7 17h4a4 4 0 0 0 4-4V9" /></svg>',
    messages: '<svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M7 10h10M7 14h6M5 4h14a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H9l-4 3v-3H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z" /></svg>',
    users: '<svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16 11a4 4 0 1 0-4-4 4 4 0 0 0 4 4Zm-8 8a4 4 0 0 1 8 0v1H8Zm10 1v-1a5.98 5.98 0 0 0-1.6-4.09A4 4 0 0 1 20 18v2Z" /></svg>',
    news: '<svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 5h12v14H6zM8 8h8M8 12h8M8 16h5" /></svg>',
    bell: '<svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 17h5l-1.4-1.4A2 2 0 0 1 18 14.17V11a6 6 0 1 0-12 0v3.17c0 .53-.2 1.04-.6 1.43L4 17h5m6 0v1a3 3 0 1 1-6 0v-1m6 0H9" /></svg>',
    settings: '<svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M10.33 4.32 9 2h6l-1.33 2.32a8.09 8.09 0 0 1 2.13.88L18 3l3 3-2.2 2.2c.38.66.67 1.36.86 2.1L22 11v2l-2.34.7a8.2 8.2 0 0 1-.86 2.1L21 18l-3 3-2.2-2.2a8.09 8.09 0 0 1-2.13.88L15 22H9l1.33-2.32a8.09 8.09 0 0 1-2.13-.88L6 21l-3-3 2.2-2.2a8.2 8.2 0 0 1-.86-2.1L2 13v-2l2.34-.7a8.2 8.2 0 0 1 .86-2.1L3 6l3-3 2.2 2.2a8.09 8.09 0 0 1 2.13-.88ZM12 15.5A3.5 3.5 0 1 0 12 8a3.5 3.5 0 0 0 0 7.5Z" /></svg>',
    help: '<svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9.09 9a3 3 0 1 1 5.82 1c0 2-3 3-3 3m.09 4h.01M12 22a10 10 0 1 1 10-10 10 10 0 0 1-10 10Z" /></svg>',
    logout: '<svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4M10 17l5-5-5-5M15 12H3" /></svg>',
};

function iconSvg(icon) {
    return iconMap[icon] || iconMap.profile;
}

function toggleOpen() {
    open.value = !open.value;
}

function handleClickOutside(event) {
    if (root.value && !root.value.contains(event.target)) {
        open.value = false;
    }
}

onMounted(() => document.addEventListener("click", handleClickOutside));
onBeforeUnmount(() => document.removeEventListener("click", handleClickOutside));
</script>

<template>
    <div ref="root" class="relative">
        <button type="button" class="app-control flex items-center gap-3 rounded-[22px] px-3 py-2" @click="toggleOpen">
            <div class="app-avatar h-10 w-10 text-sm">{{ initials }}</div>
            <div class="hidden text-left sm:block">
                <p class="text-sm font-black app-title">{{ user?.name || "Account" }}</p>
                <p class="text-xs uppercase tracking-[0.16em] app-muted">{{ user?.role || "User" }}</p>
            </div>
            <svg class="h-4 w-4 app-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M19 9l-7 7-7-7" />
            </svg>
        </button>

        <transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="translate-y-2 opacity-0 scale-95"
            enter-to-class="translate-y-0 opacity-100 scale-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="translate-y-0 opacity-100 scale-100"
            leave-to-class="translate-y-2 opacity-0 scale-95"
        >
            <div v-if="open" class="app-dropdown absolute right-0 z-50 mt-3 w-[19rem] overflow-hidden rounded-[28px]">
                <div class="border-b px-5 py-4" style="border-color: var(--app-border); background: var(--app-surface-soft);">
                    <p class="text-sm font-black app-title">{{ user?.name || "Account" }}</p>
                    <p class="mt-1 text-sm app-muted">{{ user?.email || "No email available" }}</p>
                </div>
                <div class="p-3">
                    <component
                        :is="Link"
                        v-for="item in visibleItems"
                        :key="item.label"
                        :href="item.href"
                        :method="item.method"
                        :as="item.as"
                        class="flex items-center justify-between rounded-[22px] px-4 py-3 text-sm font-semibold transition duration-200 hover:translate-x-1"
                        :style="item.highlight ? 'color: #2F2E7C;' : 'color: var(--app-text);'"
                    >
                        <span class="flex items-center gap-3">
                            <span class="flex h-9 w-9 items-center justify-center rounded-2xl border" style="background: var(--app-surface-soft); border-color: var(--app-border);" v-html="iconSvg(item.icon)"></span>
                            {{ item.label }}
                        </span>
                        <span v-if="item.suffix" class="text-xs uppercase tracking-[0.16em] app-muted">{{ item.suffix }}</span>
                    </component>
                </div>
            </div>
        </transition>
    </div>
</template>
