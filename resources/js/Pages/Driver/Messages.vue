<script setup>
import { onMounted, ref } from "vue";
import { Head } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import PageHeader from "@/Components/AppShell/PageHeader.vue";
import EmptyState from "@/Components/AppShell/EmptyState.vue";
import api from "@/lib/api";

const messages = ref([]);
const loading = ref(false);

async function loadMessages() {
    loading.value = true;

    try {
        const { data } = await api.get(route("driver.messages.list"));
        messages.value = Array.isArray(data) ? data : [];
    } catch (error) {
        messages.value = [];
        window.dispatchEvent(new CustomEvent("app-toast", {
            detail: {
                tone: "error",
                title: "Messages unavailable",
                message: error.response?.data?.message || "Could not load driver messages.",
            },
        }));
    } finally {
        loading.value = false;
    }
}

onMounted(loadMessages);
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Driver Messages" />

        <PageHeader
            eyebrow="Driver inbox"
            title="Messages"
            description="Read admin updates, schedule mentions, and route instructions in one place."
        />

        <section class="app-panel p-6 sm:p-8">
            <div v-if="loading" class="space-y-3">
                <div v-for="index in 3" :key="index" class="h-24 animate-pulse rounded-[24px]" style="background: var(--app-surface-soft);"></div>
            </div>

            <div v-else-if="messages.length" class="space-y-4">
                <article
                    v-for="(message, index) in messages"
                    :key="message.id"
                    class="relative rounded-[24px] border p-5"
                    style="border-color: var(--app-border); background: var(--app-surface-soft);"
                >
                    <span
                        v-if="index === 0"
                        class="absolute right-4 top-4 rounded-full px-3 py-1 text-[11px] font-bold uppercase tracking-[0.18em]"
                        style="background: #F2C900; color: #1F1F1F;"
                    >
                        New
                    </span>

                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-[0.18em] app-muted">From</p>
                            <h2 class="mt-1 text-xl font-black app-title">{{ message.admin_name }}</h2>
                        </div>
                        <div class="text-xs font-bold uppercase tracking-[0.16em] app-muted">
                            {{ message.created_at }}
                        </div>
                    </div>

                    <p class="mt-4 text-sm leading-7 app-muted">{{ message.message }}</p>

                    <div
                        v-if="message.schedule_info"
                        class="mt-4 rounded-2xl border px-4 py-3 text-sm"
                        style="border-color: var(--app-border); background: var(--app-surface); color: var(--app-text);"
                    >
                        {{ message.schedule_info }}
                    </div>
                </article>
            </div>

            <EmptyState
                v-else
                title="No messages yet"
                description="Admin notes and schedule mentions will appear here when there is something to review."
                icon="MS"
            />
        </section>
    </AuthenticatedLayout>
</template>
