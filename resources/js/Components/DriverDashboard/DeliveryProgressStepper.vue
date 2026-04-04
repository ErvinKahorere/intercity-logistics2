<script setup>
const props = defineProps({
    status: {
        type: String,
        default: 'accepted',
    },
});

const steps = ['accepted', 'picked_up', 'in_transit', 'arrived', 'delivered'];

function isComplete(step) {
    return steps.indexOf(step) <= steps.indexOf(props.status);
}

function label(step) {
    return step.replace('_', ' ');
}
</script>

<template>
    <div class="flex flex-wrap items-center gap-2">
        <template v-for="step in steps" :key="step">
            <div class="flex items-center gap-2">
                <span class="flex h-7 w-7 items-center justify-center rounded-full text-[10px] font-bold uppercase" :style="isComplete(step) ? 'background:#2F2E7C;color:#FFFFFF;' : 'background:var(--app-surface-soft);color:var(--app-text-muted);'">
                    {{ steps.indexOf(step) + 1 }}
                </span>
                <span class="text-[11px] font-bold uppercase tracking-[0.12em]" :style="isComplete(step) ? 'color:var(--app-text);' : 'color:var(--app-text-muted);'">
                    {{ label(step) }}
                </span>
            </div>
        </template>
    </div>
</template>
