<script setup>
import { computed } from "vue";

const props = defineProps({
    tone: {
        type: String,
        default: "neutral",
    },
    label: {
        type: String,
        default: "",
    },
    small: {
        type: Boolean,
        default: false,
    },
});

const toneStyles = computed(() => {
    const tones = {
        neutral: { background: "var(--app-surface-soft)", color: "var(--app-text)", border: "var(--app-border)" },
        info: { background: "rgba(47, 46, 124, 0.08)", color: "#2F2E7C", border: "rgba(47, 46, 124, 0.18)" },
        success: { background: "rgba(242, 201, 0, 0.22)", color: "#1F1F1F", border: "rgba(242, 201, 0, 0.4)" },
        warning: { background: "#F2C900", color: "#1F1F1F", border: "#F2C900" },
        danger: { background: "#1F1F1F", color: "#FFFFFF", border: "#1F1F1F" },
        brand: { background: "#2F2E7C", color: "#FFFFFF", border: "#2F2E7C" },
        dark: { background: "#1F1F1F", color: "#FFFFFF", border: "#1F1F1F" },
    };

    return tones[props.tone] || tones.neutral;
});
</script>

<template>
    <span
        class="inline-flex items-center rounded-full border font-bold uppercase"
        :class="small ? 'px-2.5 py-1 text-[10px] tracking-[0.18em]' : 'px-3 py-1.5 text-[11px] tracking-[0.18em]'"
        :style="{ background: toneStyles.background, color: toneStyles.color, borderColor: toneStyles.border }"
    >
        {{ label }}
    </span>
</template>
