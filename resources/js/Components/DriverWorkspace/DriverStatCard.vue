<script setup>
defineProps({
    label: { type: String, required: true },
    value: { type: [String, Number], required: true },
    meta: { type: String, default: "" },
    icon: { type: String, default: "DR" },
    tone: { type: String, default: "default" },
});

function panelStyle(tone) {
    return {
        brand: "border-color: rgba(47,46,124,0.18); background: linear-gradient(145deg, rgba(47,46,124,0.92), rgba(26,25,69,0.96)); color: #FFFFFF;",
        accent: "border-color: rgba(242,201,0,0.36); background: linear-gradient(145deg, rgba(242,201,0,0.18), rgba(255,255,255,0.98));",
        success: "border-color: rgba(242,201,0,0.4); background: linear-gradient(145deg, rgba(242,201,0,0.14), rgba(255,255,255,0.98));",
        warning: "border-color: rgba(31,31,31,0.18); background: linear-gradient(145deg, rgba(31,31,31,0.94), rgba(72,72,72,0.96)); color:#FFFFFF;",
    }[tone] || "border-color: var(--app-border); background: linear-gradient(145deg, rgba(255,255,255,0.98), rgba(246,243,237,0.92));";
}
</script>

<template>
    <article class="rounded-[28px] border p-5 shadow-[0_18px_48px_rgba(15,23,42,0.08)]" :style="panelStyle(tone)">
        <div class="flex items-start justify-between gap-4">
            <div class="min-w-0">
                <div class="text-[11px] font-bold uppercase tracking-[0.18em]" :class="tone === 'brand' || tone === 'warning' ? 'text-white/70' : 'app-muted'">{{ label }}</div>
                <div class="mt-3 text-3xl font-black leading-none" :class="tone === 'brand' || tone === 'warning' ? 'text-white' : 'app-title'">{{ value }}</div>
                <div v-if="meta" class="mt-3 text-sm" :class="tone === 'brand' || tone === 'warning' ? 'text-white/70' : 'app-muted'">{{ meta }}</div>
            </div>
            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-[18px] text-xs font-black uppercase tracking-[0.16em]" :style="tone === 'brand' || tone === 'warning' ? 'background: rgba(255,255,255,0.16); color:#FFFFFF;' : 'background: rgba(47,46,124,0.08); color:#2F2E7C;'">
                {{ icon }}
            </div>
        </div>
    </article>
</template>
