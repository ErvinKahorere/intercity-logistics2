<script setup>
import { onBeforeUnmount, onMounted, ref } from "vue";

const visible = ref(false);

function onScroll() {
    visible.value = window.scrollY > 420;
}

function scrollToTop() {
    window.scrollTo({ top: 0, behavior: "smooth" });
}

onMounted(() => {
    onScroll();
    window.addEventListener("scroll", onScroll, { passive: true });
});

onBeforeUnmount(() => window.removeEventListener("scroll", onScroll));
</script>

<template>
    <transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="translate-y-3 opacity-0 scale-90"
        enter-to-class="translate-y-0 opacity-100 scale-100"
        leave-active-class="transition duration-150 ease-in"
        leave-from-class="translate-y-0 opacity-100 scale-100"
        leave-to-class="translate-y-3 opacity-0 scale-90"
    >
        <button
            v-if="visible"
            type="button"
            class="fixed bottom-5 right-4 z-[60] flex h-12 w-12 items-center justify-center rounded-2xl border text-white transition duration-200 hover:-translate-y-0.5 sm:bottom-6 sm:right-6"
            style="background: #2F2E7C; border-color: #2F2E7C; box-shadow: 0 16px 30px rgba(31, 31, 31, 0.16);"
            @click="scrollToTop"
        >
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
            </svg>
        </button>
    </transition>
</template>
