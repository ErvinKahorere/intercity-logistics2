<script setup>
import StatusBadge from "@/Components/AppShell/StatusBadge.vue";

const props = defineProps({
    status: {
        type: String,
        default: "offline",
    },
    small: {
        type: Boolean,
        default: false,
    },
});

function toneFor(status) {
    const normalized = String(status || "").toLowerCase();

    if (normalized === "online") return "success";
    if (normalized === "busy") return "brand";
    return "dark";
}
</script>

<template>
    <StatusBadge :label="status" :tone="toneFor(status)" :small="small" />
</template>
