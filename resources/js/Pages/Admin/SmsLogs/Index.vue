<script setup>
import { computed, ref } from "vue";
import { Head } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import PageHeader from "@/Components/AppShell/PageHeader.vue";
import EmptyState from "@/Components/AppShell/EmptyState.vue";
import StatusBadge from "@/Components/AppShell/StatusBadge.vue";
import api from "@/lib/api";
import { errorToast } from "@/composables/useAppToast";

const props = defineProps({
    smsLogs: { type: Array, default: () => [] },
});

const logs = ref([...(props.smsLogs || [])]);
const loading = ref(false);
const filters = ref({
    search: "",
    status: "",
    provider: "",
});

const filteredLogs = computed(() => {
    const term = filters.value.search.trim().toLowerCase();

    return logs.value.filter((log) => {
        const matchesStatus = !filters.value.status || log.status === filters.value.status;
        const matchesProvider = !filters.value.provider || log.provider === filters.value.provider;
        const matchesSearch = !term || [
            log.recipient_name,
            log.recipient_phone,
            log.provider_message_id,
            log.tracking_number,
            log.event_type,
            log.message,
            log.skip_reason,
            log.provider_status,
            log.error_message,
        ].some((value) => String(value || "").toLowerCase().includes(term));

        return matchesStatus && matchesProvider && matchesSearch;
    });
});

const logStats = computed(() => {
    const items = filteredLogs.value;

    return [
        { label: "Queued", value: items.filter((item) => item.status === "queued").length },
        { label: "Delivered", value: items.filter((item) => item.status === "delivered").length },
        { label: "Failed", value: items.filter((item) => ["failed", "undelivered"].includes(item.status)).length },
        { label: "Skipped", value: items.filter((item) => item.status === "skipped").length },
    ];
});

function badgeTone(status) {
    return {
        queued: "warning",
        sent: "info",
        delivered: "success",
        failed: "danger",
        undelivered: "danger",
        skipped: "default",
    }[status] || "default";
}

async function refresh() {
    if (loading.value) return;
    loading.value = true;

    try {
        const { data } = await api.get(route("admin.sms-logs.data"), {
            params: { ...filters.value },
        });
        logs.value = Array.isArray(data?.smsLogs) ? data.smsLogs : [];
    } catch (error) {
        errorToast("Could not refresh SMS logs.", "Refresh failed");
    } finally {
        loading.value = false;
    }
}
</script>

<template>
    <AuthenticatedLayout>
        <Head title="SMS Logs" />

        <PageHeader eyebrow="Admin operations" title="SMS logs" description="Track Twilio trial sends, callback outcomes, failures, and skipped notifications in one place.">
            <template #actions>
                <button type="button" class="app-outline-btn" :disabled="loading" @click="refresh">{{ loading ? "Refreshing..." : "Refresh" }}</button>
            </template>
        </PageHeader>

        <div class="space-y-6">
            <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <article v-for="card in logStats" :key="card.label" class="rounded-[26px] border px-5 py-5 shadow-[0_18px_48px_rgba(15,23,42,0.06)]" style="border-color: var(--app-border); background: rgba(255,255,255,0.96);">
                    <div class="text-[11px] font-bold uppercase tracking-[0.16em] app-muted">{{ card.label }}</div>
                    <div class="mt-3 text-3xl font-black app-title">{{ card.value }}</div>
                </article>
            </section>

            <section class="rounded-[30px] border p-5 shadow-[0_18px_48px_rgba(15,23,42,0.06)] sm:p-6" style="border-color: var(--app-border); background: rgba(255,255,255,0.94);">
                <div class="grid gap-3 lg:grid-cols-[1.2fr_0.7fr_0.7fr_auto]">
                    <input v-model="filters.search" type="text" class="w-full rounded-[18px] border px-4 py-3 text-sm" style="border-color: var(--app-border); background: rgba(255,255,255,0.92);" placeholder="Search recipient, SID, event, tracking, message, or skip reason" />
                    <select v-model="filters.status" class="w-full rounded-[18px] border px-4 py-3 text-sm" style="border-color: var(--app-border); background: rgba(255,255,255,0.92);">
                        <option value="">All statuses</option>
                        <option value="queued">Queued</option>
                        <option value="sent">Sent</option>
                        <option value="delivered">Delivered</option>
                        <option value="failed">Failed</option>
                        <option value="undelivered">Undelivered</option>
                        <option value="skipped">Skipped</option>
                    </select>
                    <select v-model="filters.provider" class="w-full rounded-[18px] border px-4 py-3 text-sm" style="border-color: var(--app-border); background: rgba(255,255,255,0.92);">
                        <option value="">All providers</option>
                        <option value="twilio">Twilio</option>
                        <option value="log">Log</option>
                    </select>
                    <button type="button" class="app-primary-btn" :disabled="loading" @click="refresh">Apply</button>
                </div>
            </section>

            <section class="rounded-[30px] border shadow-[0_18px_48px_rgba(15,23,42,0.06)]" style="border-color: var(--app-border); background: rgba(255,255,255,0.96);">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y" style="divide-color: var(--app-border);">
                        <thead>
                            <tr class="text-left text-[11px] font-bold uppercase tracking-[0.16em] app-muted">
                                <th class="px-5 py-4">Status</th>
                                <th class="px-5 py-4">Recipient</th>
                                <th class="px-5 py-4">Event</th>
                                <th class="px-5 py-4">Message</th>
                                <th class="px-5 py-4">Reason / Callback</th>
                                <th class="px-5 py-4">Provider</th>
                                <th class="px-5 py-4">Attempts</th>
                                <th class="px-5 py-4">Updated</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y" style="divide-color: var(--app-border);">
                            <tr v-for="log in filteredLogs" :key="log.id">
                                <td class="px-5 py-4 align-top">
                                    <StatusBadge :label="log.status" :tone="badgeTone(log.status)" />
                                </td>
                                <td class="px-5 py-4 align-top">
                                    <div class="text-sm font-bold app-title">{{ log.recipient_name || "Unknown" }}</div>
                                    <div class="mt-1 text-sm app-muted">{{ log.recipient_phone || "No number" }}</div>
                                </td>
                                <td class="px-5 py-4 align-top">
                                    <div class="text-sm font-bold app-title">{{ log.event_type }}</div>
                                    <div class="mt-1 text-xs app-muted">{{ log.template_key || "custom" }}</div>
                                    <div class="mt-1 text-xs app-muted">{{ log.tracking_number || "-" }}</div>
                                </td>
                                <td class="px-5 py-4 align-top">
                                    <div class="max-w-md text-sm app-muted">{{ log.message }}</div>
                                    <div v-if="log.error_message" class="mt-2 text-xs font-semibold" style="color: #B91C1C;">{{ log.error_message }}</div>
                                </td>
                                <td class="px-5 py-4 align-top">
                                    <div class="text-sm app-muted">{{ log.skip_reason || log.last_callback_status || log.provider_status || "-" }}</div>
                                    <div v-if="log.callback_history?.length" class="mt-2 space-y-1">
                                        <div v-for="entry in log.callback_history.slice(0, 2)" :key="`${log.id}-${entry.time}`" class="text-xs app-muted">
                                            {{ entry.status }} - {{ new Date(entry.time).toLocaleString() }}
                                        </div>
                                    </div>
                                </td>
                                <td class="px-5 py-4 align-top">
                                    <div class="text-sm font-bold app-title">{{ log.provider }}</div>
                                    <div class="mt-1 text-xs app-muted">{{ log.provider_message_id || "No SID yet" }}</div>
                                </td>
                                <td class="px-5 py-4 align-top text-sm app-muted">{{ log.attempts ?? 0 }}</td>
                                <td class="px-5 py-4 align-top text-sm app-muted">{{ log.updated_at ? new Date(log.updated_at).toLocaleString() : "-" }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <EmptyState v-if="!filteredLogs.length" class="m-5" title="No SMS logs found" description="Trial sends, callbacks, skips, and failures will appear here." icon="SM" />
            </section>
        </div>
    </AuthenticatedLayout>
</template>
