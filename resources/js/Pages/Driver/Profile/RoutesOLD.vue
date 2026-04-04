<script setup>
import { ref, computed } from 'vue';
import { usePage, useForm, Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const page = usePage();
const user = page.props.user;
const driverRoute = page.props.driverRoute;

// Tabs
const activeTab = ref('overview');

// Vehicle & routes
const vehicle = ref(page.props.vehicle || {});
const routes = ref(page.props.routes || []);
const packages = ref(page.props.packages || []);
const locations = page.props.locations || [];
const packageTypes = page.props.packageTypes || [];

// Form
const form = useForm({
    car_make: vehicle.value.car_make || '',
    car_model: vehicle.value.car_model || '',
    car_number: vehicle.value.car_number || '',
    available: vehicle.value.available || false,
    locations: routes.value.map(r => r.id), // pre-select route locations
    packages: packages.value.map(p => p.id), // pre-select route packages
});

// Helpers
function getLocationNames(ids) {
    return locations.filter(l => ids.includes(l.id)).map(l => l.name).join(', ');
}

function getPackageNames(ids) {
    return packageTypes.filter(p => ids.includes(p.id)).map(p => p.name).join(', ');
}

function tabClass(tab) {
    return `px-4 py-2 font-semibold ${activeTab.value === tab ? 'border-b-2 border-blue-600' : 'text-gray-500'}`;
}

// Submit form


function submitForm() {
    if (!driverRoute || !driverRoute.id) {
        console.error('No driverRoute found');
        return; // STOP submission if driverRoute is missing
    }

    form.put(
        route('driver.routes.update', driverRoute.id),
        {
            onSuccess: () => {
                vehicle.value.car_make = form.car_make;
                vehicle.value.car_model = form.car_model;
                vehicle.value.car_number = form.car_number;
                vehicle.value.available = form.available;

                if (routes.value.length) {
                    routes.value = locations.filter(l => form.locations.includes(l.id));
                    packages.value = packageTypes.filter(p => form.packages.includes(p.id));
                }

                activeTab.value = 'overview';
            },
        }
    );
}


</script>

<template>
    <AuthenticatedLayout :user="user">
        <Head title="Driver Dashboard" />

        <div class="p-6">
            <!-- Tabs -->
            <div class="flex mb-6 border-b">
                <button @click="activeTab = 'overview'" :class="tabClass('overview')">Overview</button>
                <button @click="activeTab = 'edit'" :class="tabClass('edit')">Edit Details</button>
            </div>

            <!-- Overview -->
            <div v-if="activeTab === 'overview'">
                <h2 class="text-xl font-bold mb-4">Your Vehicle & Routes</h2>

                <div class="mb-4 border p-4 rounded bg-gray-50">
                    <h3 class="font-semibold mb-2">Vehicle</h3>
                    <p>{{ vehicle.car_make }} {{ vehicle.car_model }} ({{ vehicle.car_number }})</p>
                    <p>Available: {{ vehicle.available ? 'Yes' : 'No' }}</p>
                </div>


                <div class="border p-4 rounded bg-gray-50">
                    <h3 class="font-semibold mb-2">Routes</h3>

                    <div v-if="routes && routes.length">
                        <ul>
                            <li v-for="loc in routes" :key="loc.id">
                                {{ loc.name }}
                            </li>
                        </ul>
                    </div>

                    <div v-else>No routes registered yet.</div>
                </div>

                <div class="border p-4 rounded bg-gray-50 mt-4">
                    <h3 class="font-semibold mb-2">Packages</h3>

                    <div v-if="packages && packages.length">
                        <ul>
                            <li v-for="p in packages" :key="p.id">
                                {{ p.name }}
                            </li>
                        </ul>
                    </div>

                    <div v-else>No packages registered yet.</div>
                </div>

            </div>

            <!-- Edit -->
            <div v-if="activeTab === 'edit'">
                <h2 class="text-xl font-bold mb-4">Edit Vehicle & Routes</h2>

                <form @submit.prevent="submitForm" class="bg-white p-4 rounded shadow">
                    <div class="mb-4">
                        <label class="block font-semibold mb-1">Locations</label>
                        <div class="flex flex-wrap gap-2">
                            <label v-for="loc in locations" :key="loc.id" class="inline-flex items-center">
                                <input type="checkbox" :value="loc.id" v-model="form.locations" class="mr-2">
                                {{ loc.name }}
                            </label>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="block font-semibold mb-1">Packages You Carry</label>
                        <div class="flex flex-wrap gap-2">
                            <label v-for="pkg in packageTypes" :key="pkg.id" class="inline-flex items-center">
                                <input type="checkbox" :value="pkg.id" v-model="form.packages" class="mr-2">
                                {{ pkg.name }}
                            </label>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="block font-semibold mb-1">Vehicle Details</label>
                        <input v-model="form.car_make" type="text" placeholder="Car Make" class="border p-2 rounded w-full mb-2">
                        <input v-model="form.car_model" type="text" placeholder="Car Model" class="border p-2 rounded w-full mb-2">
                        <input v-model="form.car_number" type="text" placeholder="Car Number" class="border p-2 rounded w-full">
                    </div>

                    <div class="mb-4">
                        <label class="inline-flex items-center">
                            <input type="checkbox" v-model="form.available" class="mr-2">
                            Available
                        </label>
                    </div>

                    <button @click="submitForm" :disabled="!driverRoute" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                        Save Changes
                    </button>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
