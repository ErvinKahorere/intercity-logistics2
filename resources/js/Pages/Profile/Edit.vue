<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import PageHeader from '@/Components/AppShell/PageHeader.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    mustVerifyEmail: Boolean,
    status: String,
});
</script>

<template>
    <Head title="Profile" />

    <AuthenticatedLayout>
        <PageHeader eyebrow="Account settings" title="Profile" description="Manage your personal details, password, and account settings." />

        <div class="grid gap-6">
            <div class="app-panel p-6 sm:p-8">
                <UpdateProfileInformationForm :must-verify-email="mustVerifyEmail" :status="status" class="max-w-xl" />
            </div>

            <div class="app-panel p-6 sm:p-8">
                <UpdatePasswordForm class="max-w-xl" />
            </div>

            <div class="app-panel p-6 sm:p-8">
                <DeleteUserForm class="max-w-xl" />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
