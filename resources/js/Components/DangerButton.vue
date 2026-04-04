<script setup>
defineProps({
    type: {
        type: String,
        default: 'submit',
    },
});
</script>

<template>
    <button :type="type" class="inline-flex items-center justify-center gap-2 rounded-2xl px-4 py-3 text-sm font-bold uppercase tracking-[0.14em]" style="background: #1F1F1F; color: #FFFFFF;">
        <slot />
    </button>
</template>
