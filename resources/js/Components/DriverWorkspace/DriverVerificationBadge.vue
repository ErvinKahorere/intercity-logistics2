<script setup>
import StatusBadge from "@/Components/AppShell/StatusBadge.vue";

const props = defineProps({
    status: {
        type: String,
        default: "unverified",
    },
    small: {
        type: Boolean,
        default: false,
    },
});

function toneFor(status) {
    return {
        verified: "success",
        pending: "warning",
        rejected: "danger",
        unverified: "neutral",
    }[String(status || "unverified").toLowerCase()] || "neutral";
}

function labelFor(status) {
    return {
        verified: "Verified",
        pending: "Pending review",
        rejected: "Rejected",
        unverified: "Unverified",
    }[String(status || "unverified").toLowerCase()] || "Unverified";
}
</script>

<template>
    <StatusBadge :label="labelFor(status)" :tone="toneFor(status)" :small="small" />
</template>
