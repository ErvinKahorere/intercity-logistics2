<script setup>
import AppFooter from "@/Components/AppShell/AppFooter.vue";

defineProps({
    coverageCities: {
        type: Array,
        default: () => [],
    },
});
</script>

<template>
    <AppFooter :coverage-cities="coverageCities" />
</template>
