<script setup>
defineProps(['message']);
</script>

<template>
    <div v-show="message">
        <p class="text-sm font-medium" style="color: #1F1F1F;">
            {{ message }}
        </p>
    </div>
</template>
