<script setup>
const props = defineProps({
    label: String,
    value: [String, Number],
    meta: String,
    icon: {
        type: String,
        default: '•',
    },
    tone: {
        type: String,
        default: 'default',
    },
});

function iconStyle() {
    if (props.tone === 'accent') return 'background: rgba(242,201,0,0.2); color: #1F1F1F;';
    if (props.tone === 'brand') return 'background: rgba(47,46,124,0.12); color: #2F2E7C;';
    if (props.tone === 'success') return 'background: rgba(242,201,0,0.24); color: #1F1F1F;';
    return 'background: var(--app-surface-soft); color: var(--app-text);';
}
</script>

<template>
    <article class="app-panel rounded-[26px] p-4 sm:p-5">
        <div class="flex items-start justify-between gap-4">
            <div>
                <p class="text-[11px] font-bold uppercase tracking-[0.18em] app-muted">{{ label }}</p>
                <p class="mt-2 text-2xl font-black app-title sm:text-3xl">{{ value }}</p>
                <p v-if="meta" class="mt-2 text-sm app-muted">{{ meta }}</p>
            </div>
            <div class="flex h-11 w-11 items-center justify-center rounded-[18px] text-sm font-black" :style="iconStyle()">
                {{ icon }}
            </div>
        </div>
    </article>
</template>
