<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from "vue";

const props = defineProps({
    initialToasts: {
        type: Array,
        default: () => [],
    },
});

const toasts = ref([]);
let idCounter = 0;
let audioContext = null;
let pendingTone = null;
let unlockHandlersBound = false;

const canUseAudio = typeof window !== "undefined";

const toneMap = {
    success: { border: "rgba(22,163,74,0.25)", accent: "#166534", iconBg: "#166534", iconText: "#FFFFFF", bg: "rgba(255,255,255,0.96)" },
    info: { border: "rgba(47,46,124,0.22)", accent: "#2F2E7C", iconBg: "rgba(47,46,124,0.12)", iconText: "#2F2E7C", bg: "rgba(255,255,255,0.96)" },
    warning: { border: "rgba(242,201,0,0.45)", accent: "#A67C00", iconBg: "#F2C900", iconText: "#1F1F1F", bg: "rgba(255,255,255,0.96)" },
    error: { border: "rgba(220,38,38,0.25)", accent: "#B91C1C", iconBg: "#B91C1C", iconText: "#FFFFFF", bg: "rgba(255,255,255,0.96)" },
    dark: { border: "rgba(31,31,31,0.24)", accent: "#1F1F1F", iconBg: "#1F1F1F", iconText: "#FFFFFF", bg: "rgba(255,255,255,0.96)" },
};

const toneSoundMap = {
    success: [
        { frequency: 740, duration: 0.08, delay: 0 },
        { frequency: 988, duration: 0.14, delay: 0.09 },
    ],
    info: [
        { frequency: 620, duration: 0.11, delay: 0 },
    ],
    warning: [
        { frequency: 540, duration: 0.1, delay: 0 },
        { frequency: 460, duration: 0.14, delay: 0.11 },
    ],
    error: [
        { frequency: 360, duration: 0.12, delay: 0 },
        { frequency: 280, duration: 0.18, delay: 0.1 },
    ],
    dark: [
        { frequency: 420, duration: 0.1, delay: 0 },
    ],
};

function removeUnlockListeners() {
    if (!unlockHandlersBound || typeof window === "undefined") return;

    ["pointerdown", "keydown", "touchstart"].forEach((eventName) => {
        window.removeEventListener(eventName, unlockAudioOnInteraction);
    });

    unlockHandlersBound = false;
}

async function ensureAudioContext() {
    if (!canUseAudio) return null;

    const AudioCtor = window.AudioContext || window.webkitAudioContext;
    if (!AudioCtor) return null;

    if (!audioContext) {
        audioContext = new AudioCtor();
    }

    if (audioContext.state === "suspended") {
        await audioContext.resume();
    }

    return audioContext;
}

async function unlockAudioOnInteraction() {
    try {
        const context = await ensureAudioContext();
        if (!context || context.state !== "running") return;

        removeUnlockListeners();

        if (pendingTone) {
            const tone = pendingTone;
            pendingTone = null;
            playTone(tone);
        }
    } catch (error) {
        // Audio is optional. Toast UI should still work even if the browser blocks sound.
    }
}

function bindUnlockListeners() {
    if (!canUseAudio || unlockHandlersBound) return;

    ["pointerdown", "keydown", "touchstart"].forEach((eventName) => {
        window.addEventListener(eventName, unlockAudioOnInteraction, { passive: true });
    });

    unlockHandlersBound = true;
}

async function playTone(tone = "info") {
    if (!canUseAudio || document.hidden) return;

    try {
        const context = await ensureAudioContext();
        if (!context || context.state !== "running") {
            pendingTone = tone;
            bindUnlockListeners();
            return;
        }

        const notes = toneSoundMap[tone] || toneSoundMap.info;
        const startAt = context.currentTime + 0.01;

        notes.forEach((note) => {
            const oscillator = context.createOscillator();
            const gainNode = context.createGain();

            oscillator.type = tone === "error" ? "sawtooth" : tone === "warning" ? "triangle" : "sine";
            oscillator.frequency.setValueAtTime(note.frequency, startAt + note.delay);

            gainNode.gain.setValueAtTime(0.0001, startAt + note.delay);
            gainNode.gain.exponentialRampToValueAtTime(0.06, startAt + note.delay + 0.02);
            gainNode.gain.exponentialRampToValueAtTime(0.0001, startAt + note.delay + note.duration);

            oscillator.connect(gainNode);
            gainNode.connect(context.destination);

            oscillator.start(startAt + note.delay);
            oscillator.stop(startAt + note.delay + note.duration + 0.02);
        });
    } catch (error) {
        pendingTone = null;
    }
}

function addToast(toast) {
    const id = ++idCounter;
    const normalized = {
        id,
        tone: toast.tone || "info",
        title: toast.title || "Update",
        message: toast.message || "",
    };

    toasts.value.push(normalized);
    playTone(normalized.tone);
    window.setTimeout(() => dismissToast(id), toast.duration || 4200);
}

function dismissToast(id) {
    toasts.value = toasts.value.filter((toast) => toast.id !== id);
}

function handleToastEvent(event) {
    addToast(event.detail || {});
}

watch(
    () => props.initialToasts,
    (value) => {
        value.forEach((toast) => addToast(toast));
    },
    { immediate: true, deep: true }
);

onMounted(() => {
    bindUnlockListeners();
    window.addEventListener("app-toast", handleToastEvent);
});

onBeforeUnmount(() => {
    removeUnlockListeners();
    window.removeEventListener("app-toast", handleToastEvent);

    if (audioContext && typeof audioContext.close === "function") {
        audioContext.close().catch(() => {});
    }
});
</script>

<template>
    <div class="pointer-events-none fixed bottom-4 left-4 z-[70] flex w-[calc(100vw-2rem)] max-w-sm flex-col gap-3 sm:bottom-6 sm:left-6">
        <transition-group
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="translate-y-3 opacity-0 scale-95"
            enter-to-class="translate-y-0 opacity-100 scale-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="translate-y-0 opacity-100 scale-100"
            leave-to-class="translate-y-2 opacity-0 scale-95"
        >
            <article
                v-for="toast in toasts"
                :key="toast.id"
                class="app-dropdown pointer-events-auto relative overflow-hidden rounded-[24px] border p-4"
                :style="{ borderColor: (toneMap[toast.tone] || toneMap.info).border, background: (toneMap[toast.tone] || toneMap.info).bg }"
            >
                <div class="absolute inset-y-0 left-0 w-1.5 rounded-l-[24px]" :style="{ background: (toneMap[toast.tone] || toneMap.info).accent }" />
                <div class="flex items-start gap-3">
                    <div
                        class="ml-2 mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl text-xs font-black"
                        :style="{ background: (toneMap[toast.tone] || toneMap.info).iconBg, color: (toneMap[toast.tone] || toneMap.info).iconText }"
                    >
                        {{ toast.tone === "success" ? "OK" : toast.tone === "warning" ? "!" : toast.tone === "error" ? "X" : toast.tone === "dark" ? "N" : "i" }}
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-black app-title">{{ toast.title }}</p>
                        <p class="mt-1 text-sm leading-6 app-muted">{{ toast.message }}</p>
                    </div>
                    <button type="button" class="app-muted transition hover:opacity-70" @click="dismissToast(toast.id)">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </article>
        </transition-group>
    </div>
</template>
