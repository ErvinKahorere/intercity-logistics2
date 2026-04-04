<script setup>
const props = defineProps({
    steps: { type: Array, default: () => [] },
    current: { type: Number, default: 1 },
    compact: { type: Boolean, default: false },
});

function stateFor(step) {
    if (step.id < props.current) return "complete";
    if (step.id === props.current) return "active";
    return "upcoming";
}
</script>

<template>
    <div class="rounded-[24px] border p-3 sm:p-4" style="border-color: var(--app-border); background: var(--app-surface);">
        <div class="grid gap-2" :class="compact ? 'grid-cols-4' : 'sm:grid-cols-4'">
            <div
                v-for="step in steps"
                :key="step.id"
                class="rounded-[20px] border px-3 py-3 transition"
                :style="stateFor(step) === 'active'
                    ? 'border-color:#2F2E7C;background:#2F2E7C;color:#FFFFFF;box-shadow:0 16px 28px rgba(47,46,124,0.16);'
                    : stateFor(step) === 'complete'
                        ? 'border-color:rgba(47,46,124,0.18);background:rgba(47,46,124,0.08);color:#2F2E7C;'
                        : 'border-color:var(--app-border);background:var(--app-surface-soft);color:var(--app-text);'"
            >
                <div class="flex items-center gap-3" :class="compact ? 'justify-center' : ''">
                    <div
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-[16px] text-sm font-black"
                        :style="stateFor(step) === 'active'
                            ? 'background:rgba(255,255,255,0.14);color:#FFFFFF;'
                            : stateFor(step) === 'complete'
                                ? 'background:rgba(255,255,255,0.8);color:#2F2E7C;'
                                : 'background:rgba(47,46,124,0.08);color:#2F2E7C;'"
                    >
                        <component v-if="step.icon" :is="step.icon" class="h-4 w-4" />
                        <span v-else>{{ step.id }}</span>
                    </div>

                    <div v-if="!compact" class="min-w-0">
                        <div class="text-[11px] font-bold uppercase tracking-[0.16em]" :style="stateFor(step) === 'active' ? 'color:rgba(255,255,255,0.7);' : ''">
                            Step {{ step.id }}
                        </div>
                        <div class="mt-1 text-sm font-black">{{ step.label }}</div>
                    </div>
                </div>

                <div v-if="compact" class="mt-2 text-center text-[10px] font-bold uppercase tracking-[0.14em]">
                    {{ step.label }}
                </div>
            </div>
        </div>
    </div>
</template>
