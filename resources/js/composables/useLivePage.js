import { onBeforeUnmount, onMounted } from "vue";
import { router } from "@inertiajs/vue3";

export function refreshPage({ only = [], preserveScroll = true, preserveState = true, onSuccess } = {}) {
    router.reload({
        only,
        preserveScroll,
        preserveState,
        onSuccess,
    });
}

export function emitAppRefresh(detail = {}) {
    window.dispatchEvent(new CustomEvent("app:refresh", { detail }));
}

export function useAppRefreshListener(handler) {
    const listener = (event) => handler(event.detail || {});

    onMounted(() => window.addEventListener("app:refresh", listener));
    onBeforeUnmount(() => window.removeEventListener("app:refresh", listener));
}

export function usePolling(callback, interval = 20000, { immediate = false, enabled = () => true } = {}) {
    let timer = null;

    const tick = () => {
        if (document.hidden || !enabled()) {
            return;
        }

        callback();
    };

    onMounted(() => {
        if (immediate) {
            tick();
        }

        timer = window.setInterval(tick, interval);
    });

    onBeforeUnmount(() => {
        if (timer) {
            window.clearInterval(timer);
        }
    });
}
