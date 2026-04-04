<script setup>
import { ref } from "vue";

const props = defineProps({
    tabs: {
        type: Array,
        required: true,
    },
});

const activeTabId = ref(props.tabs[0]?.id ?? null);
</script>

<template>
    <section class="px-4 py-12 sm:px-6 lg:px-10 lg:py-14 2xl:px-12">
        <div class="grid gap-6 xl:grid-cols-[0.78fr_1.22fr] xl:items-start">
            <div class="space-y-5">
                <div>
                    <p class="text-[11px] font-bold uppercase tracking-[0.28em]" style="color: #2F2E7C;">Platform activity</p>
                    <h2 class="mt-4 text-3xl font-black tracking-tight app-title sm:text-4xl lg:text-5xl">
                        Live, clear, and operational
                    </h2>
                    <p class="mt-3 max-w-xl text-sm leading-6 app-muted">
                        Switch views and follow what is moving across the network.
                    </p>
                </div>

                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-1">
                    <button
                        v-for="tab in tabs"
                        :key="tab.id"
                        type="button"
                        class="rounded-[24px] border px-5 py-4 text-left transition duration-300 hover:-translate-y-0.5"
                        :style="activeTabId === tab.id ? 'border-color:#2F2E7C;background:rgba(47,46,124,0.94);color:#FFFFFF;box-shadow:0 18px 34px rgba(47,46,124,0.14);' : 'border-color:var(--app-border);background:var(--app-surface);color:var(--app-text);box-shadow:var(--app-shadow-soft);'"
                        @click="activeTabId = tab.id"
                    >
                        <p class="text-sm font-black uppercase tracking-[0.16em]">{{ tab.label }}</p>
                        <p class="mt-2 text-sm leading-6" :style="activeTabId === tab.id ? 'color: rgba(255,255,255,0.76);' : 'color: var(--app-text-muted);'">
                            {{ tab.description }}
                        </p>
                    </button>
                </div>
            </div>

            <div class="app-panel rounded-[36px] p-5 sm:p-6">
                <div v-for="tab in tabs" v-show="activeTabId === tab.id" :key="`${tab.id}-panel`" class="space-y-4">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p class="text-[11px] font-bold uppercase tracking-[0.24em]" style="color: #2F2E7C;">{{ tab.label }}</p>
                            <h3 class="mt-2 text-3xl font-black app-title">{{ tab.headline }}</h3>
                        </div>
                        <span class="rounded-full px-4 py-2 text-xs font-bold uppercase tracking-[0.18em]" style="background: #F2C900; color: #1F1F1F;">
                            {{ tab.badge }}
                        </span>
                    </div>

                    <div class="grid gap-3">
                        <article v-for="entry in tab.items" :key="entry.title" class="rounded-[24px] border p-4 sm:p-5 transition duration-300 hover:-translate-y-0.5" style="border-color: var(--app-border); background: var(--app-surface-soft); box-shadow: var(--app-shadow-soft);">
                            <div class="flex flex-col gap-4 sm:grid sm:grid-cols-[auto_1fr_auto] sm:items-start">
                                <div class="flex h-12 w-12 items-center justify-center rounded-[18px] text-lg font-black" style="background: rgba(47, 46, 124, 0.1); color: #2F2E7C;">
                                    {{ entry.icon }}
                                </div>
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h4 class="text-lg font-black app-title">{{ entry.title }}</h4>
                                        <span class="rounded-full border px-3 py-1 text-[11px] font-bold uppercase tracking-[0.18em]" :style="entry.badgeStyle">
                                            {{ entry.badge }}
                                        </span>
                                    </div>
                                    <p class="mt-2 text-sm leading-6 app-muted">{{ entry.meta }}</p>
                                </div>
                                <div class="text-xs font-bold uppercase tracking-[0.16em] app-muted sm:text-right">{{ entry.time }}</div>
                            </div>
                        </article>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>

