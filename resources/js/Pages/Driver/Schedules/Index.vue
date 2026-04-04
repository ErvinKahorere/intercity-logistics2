<script setup>
import { computed, onMounted, reactive, ref } from "vue";
import { Head } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import PageHeader from "@/Components/AppShell/PageHeader.vue";
import EmptyState from "@/Components/AppShell/EmptyState.vue";
import api from "@/lib/api";

const days = [
    { v: "sat", t: "Saturday" },
    { v: "sun", t: "Sunday" },
    { v: "mon", t: "Monday" },
    { v: "tue", t: "Tuesday" },
    { v: "wed", t: "Wednesday" },
    { v: "thu", t: "Thursday" },
    { v: "fri", t: "Friday" },
];

const items = ref([]);
const loading = ref(false);
const saving = ref(false);
const editing = ref(null);
const showForm = ref(false);

const form = reactive({
    day_of_week: [],
    start_time: "09:00",
    end_time: "13:00",
    slot_minutes: 15,
    max_users_per_day: 20,
    fee: 500,
});

const isEditing = computed(() => editing.value !== null);
const hasSchedules = computed(() => items.value.length > 0);

function toast(tone, title, message) {
    window.dispatchEvent(new CustomEvent("app-toast", {
        detail: { tone, title, message },
    }));
}

function resetForm() {
    editing.value = null;
    showForm.value = false;
    Object.assign(form, {
        day_of_week: [],
        start_time: "09:00",
        end_time: "13:00",
        slot_minutes: 15,
        max_users_per_day: 20,
        fee: 500,
    });
}

async function fetchList() {
    loading.value = true;
    try {
        const { data } = await api.get(route("driver.schedules.list"));
        items.value = Array.isArray(data) ? data : [];
    } catch (error) {
        toast("error", "Schedules unavailable", error.response?.data?.message || "Failed to load schedules.");
    } finally {
        loading.value = false;
    }
}

function startCreating() {
    resetForm();
    showForm.value = true;
}

function edit(row) {
    editing.value = row;
    showForm.value = true;
    Object.assign(form, {
        day_of_week: Array.isArray(row.day_of_week) ? row.day_of_week : [],
        start_time: row.start_time?.slice(0, 5) || "09:00",
        end_time: row.end_time?.slice(0, 5) || "13:00",
        slot_minutes: row.slot_minutes,
        max_users_per_day: row.max_users_per_day,
        fee: row.fee,
    });
}

async function save() {
    saving.value = true;

    try {
        if (editing.value) {
            const { data } = await api.put(route("driver.schedules.update", editing.value.id), form);
            const index = items.value.findIndex((item) => item.id === editing.value.id);
            if (index >= 0) items.value[index] = data;
            toast("success", "Schedule updated", "Your availability settings were saved.");
        } else {
            const { data } = await api.post(route("driver.schedules.store"), form);
            items.value.unshift(data);
            toast("success", "Schedule created", "A new availability schedule has been added.");
        }

        resetForm();
    } catch (error) {
        toast("error", "Save failed", error.response?.data?.message || "Could not save the schedule.");
    } finally {
        saving.value = false;
    }
}

async function removeRow(row) {
    if (!confirm("Delete this schedule?")) return;

    try {
        await api.delete(route("driver.schedules.destroy", row.id));
        items.value = items.value.filter((item) => item.id !== row.id);
        toast("success", "Schedule deleted", "The schedule has been removed.");
    } catch (error) {
        toast("error", "Delete failed", error.response?.data?.message || "Could not delete the schedule.");
    }
}

function dayName(value) {
    return days.find((day) => day.v === value)?.t || value;
}

onMounted(fetchList);
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Driver Schedules" />

        <PageHeader
            eyebrow="Availability"
            title="Schedules"
            description="Set when you are available so bookings and route planning stay predictable."
        >
            <template #actions>
                <button type="button" class="app-primary-btn" @click="startCreating">
                    New Schedule
                </button>
            </template>
        </PageHeader>

        <section v-if="showForm" class="app-panel p-6 sm:p-8">
            <div class="flex flex-col gap-3 border-b pb-6 sm:flex-row sm:items-center sm:justify-between" style="border-color: var(--app-border);">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.2em] app-muted">Schedule form</p>
                    <h2 class="mt-2 text-2xl font-black app-title">{{ isEditing ? "Edit schedule" : "Create schedule" }}</h2>
                </div>
                <span
                    v-if="isEditing"
                    class="rounded-full px-4 py-2 text-xs font-bold uppercase tracking-[0.18em]"
                    style="background: #F2C900; color: #1F1F1F;"
                >
                    Editing
                </span>
            </div>

            <div class="mt-6 grid gap-6 lg:grid-cols-3">
                <div class="space-y-3 lg:col-span-2">
                    <label class="text-xs font-bold uppercase tracking-[0.18em] app-muted">Days</label>
                    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                        <label
                            v-for="day in days"
                            :key="day.v"
                            class="flex items-center gap-3 rounded-2xl border px-4 py-3 text-sm font-semibold"
                            style="border-color: var(--app-border); background: var(--app-surface-soft); color: var(--app-text);"
                        >
                            <input v-model="form.day_of_week" :value="day.v" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-[#2F2E7C] focus:ring-[#2F2E7C]" />
                            {{ day.t }}
                        </label>
                    </div>
                </div>

                <div class="space-y-4">
                    <label class="block space-y-2">
                        <span class="text-xs font-bold uppercase tracking-[0.18em] app-muted">Start time</span>
                        <input v-model="form.start_time" type="time" class="app-control h-12 w-full rounded-2xl border px-4" style="border-color: var(--app-border);" />
                    </label>
                    <label class="block space-y-2">
                        <span class="text-xs font-bold uppercase tracking-[0.18em] app-muted">End time</span>
                        <input v-model="form.end_time" type="time" class="app-control h-12 w-full rounded-2xl border px-4" style="border-color: var(--app-border);" />
                    </label>
                </div>
            </div>

            <div class="mt-6 grid gap-4 md:grid-cols-3">
                <label class="block space-y-2">
                    <span class="text-xs font-bold uppercase tracking-[0.18em] app-muted">Slot minutes</span>
                    <input v-model.number="form.slot_minutes" type="number" min="5" max="240" class="app-control h-12 w-full rounded-2xl border px-4" style="border-color: var(--app-border);" />
                </label>
                <label class="block space-y-2">
                    <span class="text-xs font-bold uppercase tracking-[0.18em] app-muted">Max users per day</span>
                    <input v-model.number="form.max_users_per_day" type="number" min="1" max="500" class="app-control h-12 w-full rounded-2xl border px-4" style="border-color: var(--app-border);" />
                </label>
                <label class="block space-y-2">
                    <span class="text-xs font-bold uppercase tracking-[0.18em] app-muted">Fee</span>
                    <input v-model.number="form.fee" type="number" min="0" max="1000000" class="app-control h-12 w-full rounded-2xl border px-4" style="border-color: var(--app-border);" />
                </label>
            </div>

            <div class="mt-6 flex flex-col gap-3 sm:flex-row">
                <button type="button" class="app-primary-btn" :disabled="saving" @click="save">
                    {{ saving ? "Saving..." : isEditing ? "Update Schedule" : "Create Schedule" }}
                </button>
                <button type="button" class="app-outline-btn" @click="resetForm">
                    Cancel
                </button>
            </div>
        </section>

        <section class="app-panel p-6 sm:p-8">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.2em] app-muted">Availability list</p>
                    <h2 class="mt-2 text-2xl font-black app-title">Configured schedules</h2>
                </div>
                <span class="rounded-full px-4 py-2 text-xs font-bold uppercase tracking-[0.18em]" style="background: var(--app-surface-soft); color: var(--app-text); border: 1px solid var(--app-border);">
                    {{ items.length }} total
                </span>
            </div>

            <div v-if="loading" class="mt-6 space-y-3">
                <div v-for="index in 3" :key="index" class="h-24 animate-pulse rounded-[24px]" style="background: var(--app-surface-soft);"></div>
            </div>

            <div v-else-if="hasSchedules" class="mt-6 grid gap-4">
                <article
                    v-for="row in items"
                    :key="row.id"
                    class="rounded-[24px] border p-5"
                    style="border-color: var(--app-border); background: var(--app-surface-soft);"
                >
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div>
                            <div class="flex flex-wrap gap-2">
                                <span
                                    v-for="day in row.day_of_week"
                                    :key="`${row.id}-${day}`"
                                    class="rounded-full px-3 py-1.5 text-xs font-bold uppercase tracking-[0.16em]"
                                    style="background: var(--app-surface); color: var(--app-text); border: 1px solid var(--app-border);"
                                >
                                    {{ dayName(day) }}
                                </span>
                            </div>
                            <p class="mt-4 text-lg font-black app-title">{{ row.start_time }} - {{ row.end_time }}</p>
                            <p class="mt-2 text-sm app-muted">
                                {{ row.slot_minutes }} min slots · {{ row.max_users_per_day }} users max · Fee {{ row.fee }}
                            </p>
                        </div>

                        <div class="flex flex-wrap gap-3">
                            <button type="button" class="app-outline-btn" @click="edit(row)">Edit</button>
                            <button type="button" class="app-primary-btn" @click="removeRow(row)">Delete</button>
                        </div>
                    </div>
                </article>
            </div>

            <EmptyState
                v-else
                class="mt-6"
                title="No schedules yet"
                description="Add your first schedule to show when you can take bookings and route work."
                icon="SC"
            >
                <template #action>
                    <button type="button" class="app-primary-btn" @click="startCreating">Create Schedule</button>
                </template>
            </EmptyState>
        </section>
    </AuthenticatedLayout>
</template>
