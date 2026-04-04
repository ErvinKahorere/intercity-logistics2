<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from "vue";
import { Link } from "@inertiajs/vue3";
import api from "@/lib/api";
import StatusBadge from "@/Components/AppShell/StatusBadge.vue";
import { emitAppRefresh } from "@/composables/useLivePage";

const props = defineProps({
    notifications: {
        type: Array,
        default: () => [],
    },
    viewAllHref: {
        type: String,
        default: "",
    },
});

const open = ref(false);
const root = ref(null);
const localNotifications = ref([]);
const markingRead = ref(false);

watch(
    () => props.notifications,
    (value) => {
        localNotifications.value = Array.isArray(value) ? value.map((item) => ({ ...item })) : [];
    },
    { immediate: true, deep: true }
);

const unreadCount = computed(() => localNotifications.value.filter((item) => !item.read).length);
const toneStyles = {
    success: { card: "border-color: rgba(22,163,74,0.18); background: rgba(22,163,74,0.06);", icon: "background:#166534;color:#FFFFFF;" },
    info: { card: "border-color: rgba(47,46,124,0.14); background: rgba(47,46,124,0.05);", icon: "background:#2F2E7C;color:#FFFFFF;" },
    warning: { card: "border-color: rgba(242,201,0,0.3); background: rgba(242,201,0,0.14);", icon: "background:#F2C900;color:#1F1F1F;" },
    error: { card: "border-color: rgba(220,38,38,0.18); background: rgba(220,38,38,0.08);", icon: "background:#B91C1C;color:#FFFFFF;" },
    dark: { card: "border-color: rgba(31,31,31,0.16); background: rgba(31,31,31,0.05);", icon: "background:#1F1F1F;color:#FFFFFF;" },
};

async function toggleOpen() {
    open.value = !open.value;

    if (open.value) {
        emitAppRefresh({ only: ["appNotifications"] });
        await markVisibleNotificationsAsRead();
    }
}

async function markVisibleNotificationsAsRead() {
    const unreadIds = localNotifications.value.filter((item) => !item.read).map((item) => item.id);
    if (!unreadIds.length || markingRead.value) {
        return;
    }

    const previous = localNotifications.value.map((item) => ({ ...item }));
    localNotifications.value = localNotifications.value.map((item) => ({ ...item, read: true }));
    markingRead.value = true;

    try {
        await api.post(route("notifications.read"), { ids: unreadIds });
        emitAppRefresh({ only: ["appNotifications"] });
    } catch (error) {
        localNotifications.value = previous;
    } finally {
        markingRead.value = false;
    }
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
        <button type="button" class="app-icon-button relative h-11 w-11" @click="toggleOpen">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 17h5l-1.4-1.4A2 2 0 0 1 18 14.17V11a6 6 0 1 0-12 0v3.17c0 .53-.2 1.04-.6 1.43L4 17h5m6 0v1a3 3 0 1 1-6 0v-1m6 0H9" />
            </svg>
            <span v-if="unreadCount" class="absolute -right-1 -top-1 inline-flex min-w-[1.2rem] items-center justify-center rounded-full px-1.5 py-0.5 text-[10px] font-bold" style="background: #F2C900; color: #1F1F1F;">
                {{ unreadCount }}
            </span>
        </button>

        <transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="translate-y-2 opacity-0 scale-95"
            enter-to-class="translate-y-0 opacity-100 scale-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="translate-y-0 opacity-100 scale-100"
            leave-to-class="translate-y-2 opacity-0 scale-95"
        >
            <div v-if="open" class="app-dropdown absolute right-0 z-50 mt-3 w-[24rem] overflow-hidden rounded-[28px]">
                <div class="border-b px-5 py-4" style="border-color: var(--app-border);">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-sm font-black app-title">Notifications</p>
                            <p class="mt-1 text-xs uppercase tracking-[0.18em] app-muted">Recent platform activity</p>
                        </div>
                        <StatusBadge tone="brand" small :label="`${unreadCount} unread`" />
                    </div>
                </div>

                <div class="max-h-[24rem] overflow-y-auto p-3">
                    <Link
                        v-for="item in localNotifications"
                        :key="item.id"
                        :href="item.href || viewAllHref || '#'"
                        class="block rounded-[24px] border p-4 transition duration-200 hover:-translate-y-0.5"
                        :style="item.read
                            ? 'border-color: rgba(47,46,124,0.08); background: rgba(255,255,255,0.58); opacity:0.88;'
                            : (toneStyles[item.tone] || toneStyles.info).card"
                        @click="open = false"
                    >
                        <div class="flex items-start gap-3">
                            <div
                                class="flex h-11 w-11 shrink-0 items-center justify-center rounded-[16px] text-sm font-black"
                                :style="(toneStyles[item.tone] || toneStyles.info).icon"
                            >
                                {{ item.icon }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center justify-between gap-2">
                                    <p class="truncate text-sm font-black app-title">{{ item.title }}</p>
                                    <StatusBadge :tone="item.tone" small :label="item.badge" />
                                </div>
                                <p class="mt-1 text-sm leading-6 app-muted">{{ item.message }}</p>
                                <div class="mt-3 flex items-center justify-between gap-3">
                                    <p class="text-[11px] font-semibold uppercase tracking-[0.16em] app-muted">{{ item.time }}</p>
                                    <span class="text-[11px] font-bold uppercase tracking-[0.16em]" style="color:#2F2E7C;">
                                        {{ item.action_label || "Open" }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </Link>
                    <div v-if="!localNotifications.length" class="rounded-[22px] px-4 py-6 text-sm app-muted">No notifications yet.</div>
                </div>

                <div v-if="viewAllHref" class="border-t px-5 py-4" style="border-color: var(--app-border);">
                    <Link :href="viewAllHref" class="text-sm font-bold uppercase tracking-[0.16em]" style="color: #2F2E7C;">
                        View all notifications
                    </Link>
                </div>
            </div>
        </transition>
    </div>
</template>
