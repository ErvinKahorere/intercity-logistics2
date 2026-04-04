<script setup>
import { computed, ref, watch } from "vue";
import { Head, useForm, usePage } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import PageHeader from "@/Components/AppShell/PageHeader.vue";
import EmptyState from "@/Components/AppShell/EmptyState.vue";
import { emitAppRefresh } from "@/composables/useLivePage";
import { errorToast, successToast } from "@/composables/useAppToast";

const page = usePage();
const user = computed(() => page.props.user || {});
const driverRoute = ref({});
const vehicle = ref({});
const locations = computed(() => page.props.locations || []);
const packageTypes = computed(() => page.props.packageTypes || []);
const selectedLocations = ref([]);
const packages = ref([]);

watch(() => page.props.driverRoute, (value) => { driverRoute.value = value || {}; }, { immediate: true, deep: true });
watch(() => page.props.vehicle, (value) => { vehicle.value = value || {}; }, { immediate: true, deep: true });
watch(() => page.props.selectedLocations, (value) => { selectedLocations.value = value || []; }, { immediate: true, deep: true });
watch(() => page.props.packages, (value) => { packages.value = value || []; }, { immediate: true, deep: true });

const vehicleTypeOptions = [
    { value: 'car', label: 'Car' },
    { value: 'bakkie', label: 'Bakkie' },
    { value: 'van', label: 'Van' },
    { value: 'truck', label: 'Truck' },
    { value: 'refrigerated_truck', label: 'Refrigerated truck' },
];

const loadSizeOptions = [
    { value: 'small', label: 'Small' },
    { value: 'medium', label: 'Medium' },
    { value: 'large', label: 'Large' },
    { value: 'heavy', label: 'Heavy' },
    { value: 'oversized', label: 'Oversized' },
];

const form = useForm({
    vehicle_type: '',
    max_load_size: 'medium',
    is_refrigerated: false,
    car_make: '',
    car_model: '',
    car_number: '',
    available: false,
    locations: [],
    packages: [],
});

watch(vehicle, (value) => {
    form.defaults({
        vehicle_type: value.vehicle_type || 'bakkie',
        max_load_size: value.max_load_size || 'medium',
        is_refrigerated: !!value.is_refrigerated,
        car_make: value.car_make || '',
        car_model: value.car_model || '',
        car_number: value.car_number || '',
        available: !!value.available,
        locations: selectedLocations.value.map((location) => location.id),
        packages: packages.value.map((pkg) => pkg.id),
    });
    form.reset();
}, { immediate: true, deep: true });

watch(selectedLocations, (value) => { form.locations = value.map((location) => location.id); }, { immediate: true, deep: true });
watch(packages, (value) => { form.packages = value.map((pkg) => pkg.id); }, { immediate: true, deep: true });

const selectedLocationNames = computed(() => locations.value.filter((location) => form.locations.includes(location.id)));
const selectedPackageNames = computed(() => packageTypes.value.filter((pkg) => form.packages.includes(pkg.id)));
const selectedVehicleType = computed(() => vehicleTypeOptions.find((option) => option.value === form.vehicle_type)?.label || form.vehicle_type);
const selectedLoadSize = computed(() => loadSizeOptions.find((option) => option.value === form.max_load_size)?.label || form.max_load_size);


function submit() {
    form.put(route("driver.routes.update", driverRoute.value.id), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            successToast("Vehicle and route details updated.", "Routes saved");
            emitAppRefresh({ only: ["appNotifications"] });
        },
        onError: () => {
            errorToast("Could not save route settings.", "Save failed");
        },
    });
}
</script>

<template>
    <AuthenticatedLayout :user="user">
        <Head title="Driver Routes" />

        <PageHeader
            eyebrow="Route manager"
            title="Routes and capability"
            description="Set the routes, vehicle type, and parcel limits you want the matching engine to use."
        />

        <div class="grid gap-6 xl:grid-cols-[0.84fr_1.16fr]">
            <section class="app-panel p-6 sm:p-8">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[0.2em] app-muted">Current setup</p>
                        <h2 class="mt-2 text-2xl font-black app-title">Route snapshot</h2>
                    </div>
                    <span class="rounded-full px-4 py-2 text-xs font-bold uppercase tracking-[0.18em]" :style="form.available ? 'background:#F2C900;color:#1F1F1F;' : 'background:var(--app-surface-soft);color:var(--app-text);border:1px solid var(--app-border);'">
                        {{ form.available ? 'Available' : 'Offline' }}
                    </span>
                </div>

                <div class="mt-6 grid gap-4">
                    <div class="rounded-[24px] border p-5" style="border-color: var(--app-border); background: var(--app-surface-soft);">
                        <div class="text-xs font-bold uppercase tracking-[0.18em] app-muted">Vehicle</div>
                        <div class="mt-2 text-xl font-black app-title">{{ selectedVehicleType }}</div>
                        <div class="mt-1 text-sm app-muted">{{ form.car_make || 'Vehicle make' }} {{ form.car_model || '' }} {{ form.car_number ? `| ${form.car_number}` : '' }}</div>
                    </div>

                    <div class="rounded-[24px] border p-5" style="border-color: var(--app-border); background: var(--app-surface-soft);">
                        <div class="text-xs font-bold uppercase tracking-[0.18em] app-muted">Load capacity</div>
                        <div class="mt-2 text-xl font-black app-title">{{ selectedLoadSize }}</div>
                        <div class="mt-1 text-sm app-muted">{{ form.is_refrigerated ? 'Refrigerated support enabled' : 'Standard cargo only' }}</div>
                    </div>

                    <div class="rounded-[24px] border p-5" style="border-color: var(--app-border); background: var(--app-surface-soft);">
                        <div class="text-xs font-bold uppercase tracking-[0.18em] app-muted">Route coverage</div>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <span v-for="location in selectedLocationNames" :key="location.id" class="rounded-full border px-3 py-2 text-sm font-semibold" style="border-color: var(--app-border); background: var(--app-surface); color: var(--app-text);">
                                {{ location.name }}
                            </span>
                            <span v-if="!selectedLocationNames.length" class="text-sm app-muted">No route stops selected yet.</span>
                        </div>
                    </div>

                    <div class="rounded-[24px] border p-5" style="border-color: var(--app-border); background: var(--app-surface-soft);">
                        <div class="text-xs font-bold uppercase tracking-[0.18em] app-muted">Parcel support</div>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <span v-for="pkg in selectedPackageNames" :key="pkg.id" class="rounded-full px-3 py-2 text-sm font-semibold" style="background: #F2C900; color: #1F1F1F;">
                                {{ pkg.name }}
                            </span>
                            <span v-if="!selectedPackageNames.length" class="text-sm app-muted">No parcel categories selected yet.</span>
                        </div>
                    </div>
                </div>
            </section>

            <form class="app-panel p-6 sm:p-8" @submit.prevent="submit">
                <div class="flex flex-col gap-3 border-b pb-6 sm:flex-row sm:items-center sm:justify-between" style="border-color: var(--app-border);">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[0.2em] app-muted">Update route</p>
                        <h2 class="mt-2 text-2xl font-black app-title">Vehicle and matching settings</h2>
                    </div>
                    <button type="submit" class="app-primary-btn" :disabled="form.processing">
                        {{ form.processing ? 'Saving...' : 'Save Changes' }}
                    </button>
                </div>

                <div class="mt-6 grid gap-6 md:grid-cols-2">
                    <label class="space-y-2">
                        <span class="text-xs font-bold uppercase tracking-[0.18em] app-muted">Vehicle type</span>
                        <select v-model="form.vehicle_type" class="app-control h-12 w-full rounded-2xl border px-4" style="border-color: var(--app-border);">
                            <option v-for="option in vehicleTypeOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                        </select>
                    </label>

                    <label class="space-y-2">
                        <span class="text-xs font-bold uppercase tracking-[0.18em] app-muted">Max load size</span>
                        <select v-model="form.max_load_size" class="app-control h-12 w-full rounded-2xl border px-4" style="border-color: var(--app-border);">
                            <option v-for="option in loadSizeOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                        </select>
                    </label>

                    <label class="space-y-2">
                        <span class="text-xs font-bold uppercase tracking-[0.18em] app-muted">Vehicle make</span>
                        <input v-model="form.car_make" type="text" class="app-control h-12 w-full rounded-2xl border px-4" style="border-color: var(--app-border);" />
                    </label>

                    <label class="space-y-2">
                        <span class="text-xs font-bold uppercase tracking-[0.18em] app-muted">Vehicle model</span>
                        <input v-model="form.car_model" type="text" class="app-control h-12 w-full rounded-2xl border px-4" style="border-color: var(--app-border);" />
                    </label>

                    <label class="space-y-2 md:col-span-2">
                        <span class="text-xs font-bold uppercase tracking-[0.18em] app-muted">Number plate</span>
                        <input v-model="form.car_number" type="text" class="app-control h-12 w-full rounded-2xl border px-4" style="border-color: var(--app-border);" />
                    </label>
                </div>

                <div class="mt-6 grid gap-4 md:grid-cols-2">
                    <div class="rounded-[24px] border p-5" style="border-color: var(--app-border); background: var(--app-surface-soft);">
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <p class="text-xs font-bold uppercase tracking-[0.18em] app-muted">Availability</p>
                                <p class="mt-1 text-lg font-black app-title">{{ form.available ? 'Visible to new requests' : 'Hidden from new requests' }}</p>
                            </div>
                            <button type="button" class="relative h-8 w-16 rounded-full transition" :class="form.available ? 'bg-[#2F2E7C]' : 'bg-neutral-400'" @click="form.available = !form.available">
                                <span class="absolute top-1 h-6 w-6 rounded-full bg-white transition" :class="form.available ? 'left-9' : 'left-1'"></span>
                            </button>
                        </div>
                    </div>

                    <div class="rounded-[24px] border p-5" style="border-color: var(--app-border); background: var(--app-surface-soft);">
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <p class="text-xs font-bold uppercase tracking-[0.18em] app-muted">Cold chain</p>
                                <p class="mt-1 text-lg font-black app-title">{{ form.is_refrigerated ? 'Enabled' : 'Not enabled' }}</p>
                            </div>
                            <button type="button" class="relative h-8 w-16 rounded-full transition" :class="form.is_refrigerated ? 'bg-[#2F2E7C]' : 'bg-neutral-400'" @click="form.is_refrigerated = !form.is_refrigerated">
                                <span class="absolute top-1 h-6 w-6 rounded-full bg-white transition" :class="form.is_refrigerated ? 'left-9' : 'left-1'"></span>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="mt-6 grid gap-6 lg:grid-cols-2">
                    <section class="rounded-[24px] border p-5" style="border-color: var(--app-border); background: var(--app-surface-soft);">
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <p class="text-xs font-bold uppercase tracking-[0.18em] app-muted">Locations</p>
                                <h3 class="mt-1 text-xl font-black app-title">Route stops</h3>
                            </div>
                            <span class="rounded-full px-3 py-1 text-xs font-bold uppercase tracking-[0.18em]" style="background: var(--app-surface); color: var(--app-text); border: 1px solid var(--app-border);">{{ form.locations.length }}</span>
                        </div>

                        <div class="mt-4 grid gap-3 sm:grid-cols-2">
                            <label v-for="location in locations" :key="location.id" class="flex items-center gap-3 rounded-2xl border px-4 py-3 text-sm font-semibold transition" style="border-color: var(--app-border); background: var(--app-surface); color: var(--app-text);">
                                <input v-model="form.locations" type="checkbox" :value="location.id" class="h-4 w-4 rounded border-gray-300 text-[#2F2E7C] focus:ring-[#2F2E7C]" />
                                {{ location.name }}
                            </label>
                        </div>
                    </section>

                    <section class="rounded-[24px] border p-5" style="border-color: var(--app-border); background: var(--app-surface-soft);">
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <p class="text-xs font-bold uppercase tracking-[0.18em] app-muted">Packages</p>
                                <h3 class="mt-1 text-xl font-black app-title">Parcel types</h3>
                            </div>
                            <span class="rounded-full px-3 py-1 text-xs font-bold uppercase tracking-[0.18em]" style="background: var(--app-surface); color: var(--app-text); border: 1px solid var(--app-border);">{{ form.packages.length }}</span>
                        </div>

                        <div class="mt-4 grid gap-3 sm:grid-cols-2">
                            <label v-for="pkg in packageTypes" :key="pkg.id" class="flex items-center gap-3 rounded-2xl border px-4 py-3 text-sm font-semibold transition" style="border-color: var(--app-border); background: var(--app-surface); color: var(--app-text);">
                                <input v-model="form.packages" type="checkbox" :value="pkg.id" class="h-4 w-4 rounded border-gray-300 text-[#2F2E7C] focus:ring-[#2F2E7C]" />
                                {{ pkg.name }}
                            </label>
                        </div>
                    </section>
                </div>

                <EmptyState
                    v-if="!locations.length || !packageTypes.length"
                    class="mt-6"
                    title="Route setup data is missing"
                    description="Locations or parcel categories are not available yet. Seed the platform data to finish route setup."
                    icon="RT"
                />
            </form>
        </div>
    </AuthenticatedLayout>
</template>
