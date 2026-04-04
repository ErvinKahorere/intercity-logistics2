<script setup>
import { Head, Link, useForm } from "@inertiajs/vue3";
import { ref } from "vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import PageHeader from "@/Components/AppShell/PageHeader.vue";
import InputLabel from "@/Components/InputLabel.vue";
import InputError from "@/Components/InputError.vue";
import TextInput from "@/Components/TextInput.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import { emitAppRefresh } from "@/composables/useLivePage";
import { errorToast, successToast } from "@/composables/useAppToast";

const props = defineProps({ user: Object });
const profile = ref({ ...(props.user || {}) });
const activeTab = ref("overview");
const form = useForm({
    name: profile.value.name || '',
    email: profile.value.email || '',
    phone: profile.value.phone || '',
    profile_photo: null,
});
const previewImage = ref(profile.value.profile_photo_url || '/images/Default_pfp.jpg');

function handleFileChange(event) {
    const file = event.target.files[0];
    form.profile_photo = file;
    if (file) {
        const reader = new FileReader();
        reader.onload = (e) => { previewImage.value = e.target.result; };
        reader.readAsDataURL(file);
    }
}

function submitForm() {
    form.post(route('user.profile.update'), {
        preserveScroll: true,
        forceFormData: true,
        preserveState: true,
        onSuccess: () => {
            profile.value = { ...profile.value, name: form.name, email: form.email, phone: form.phone, profile_photo_url: previewImage.value };
            successToast('Profile updated successfully.', 'Profile saved');
            emitAppRefresh({ only: ['auth', 'appNotifications'] });
        },
        onError: () => errorToast('Could not update your profile.', 'Save failed'),
    });
}
</script>

<template>
    <Head title="User Profile" />

    <AuthenticatedLayout>
        <PageHeader eyebrow="User account" title="Profile" description="Review and update your account details.">
            <template #actions>
                <Link href="/dashboard" class="app-outline-btn">Back to Dashboard</Link>
            </template>
        </PageHeader>

        <div class="app-panel p-6">
            <div class="mb-6 flex gap-4 border-b pb-3" style="border-color: var(--app-border);">
                <button class="px-4 py-2 text-sm font-bold uppercase tracking-[0.14em]" :style="activeTab === 'overview' ? 'color:#2F2E7C;border-bottom:2px solid #2F2E7C;' : 'color:var(--app-text-muted);'" @click="activeTab = 'overview'">Overview</button>
                <button class="px-4 py-2 text-sm font-bold uppercase tracking-[0.14em]" :style="activeTab === 'edit' ? 'color:#2F2E7C;border-bottom:2px solid #2F2E7C;' : 'color:var(--app-text-muted);'" @click="activeTab = 'edit'">Edit</button>
            </div>

            <div v-if="activeTab === 'overview'" class="flex flex-col gap-6 md:flex-row">
                <img :src="profile.profile_photo_url || '/images/Default_pfp.jpg'" alt="Profile" class="h-32 w-32 rounded-full object-cover" />
                <div class="grid flex-1 gap-4 md:grid-cols-2">
                    <div class="rounded-[22px] border p-4" style="border-color: var(--app-border); background: var(--app-surface-soft);"><div class="text-xs font-bold uppercase tracking-[0.16em] app-muted">Full Name</div><div class="mt-2 text-lg font-black app-title">{{ profile.name }}</div></div>
                    <div class="rounded-[22px] border p-4" style="border-color: var(--app-border); background: var(--app-surface-soft);"><div class="text-xs font-bold uppercase tracking-[0.16em] app-muted">Email</div><div class="mt-2 text-lg font-black app-title">{{ profile.email }}</div></div>
                    <div class="rounded-[22px] border p-4" style="border-color: var(--app-border); background: var(--app-surface-soft);"><div class="text-xs font-bold uppercase tracking-[0.16em] app-muted">Phone</div><div class="mt-2 text-lg font-black app-title">{{ profile.phone || 'Not added' }}</div></div>
                </div>
            </div>

            <form v-else class="grid gap-6 md:grid-cols-[180px_1fr]" @submit.prevent="submitForm">
                <div>
                    <img :src="previewImage" alt="Preview" class="h-36 w-36 rounded-full object-cover" />
                    <label class="mt-4 inline-flex cursor-pointer rounded-2xl border px-4 py-3 text-sm font-semibold" style="border-color: var(--app-border); background: var(--app-surface-soft); color: var(--app-text);">
                        Change photo
                        <input type="file" class="hidden" @change="handleFileChange" />
                    </label>
                </div>
                <div class="space-y-5">
                    <div>
                        <InputLabel for="name" value="Full Name" />
                        <TextInput id="name" v-model="form.name" type="text" />
                        <InputError class="mt-2" :message="form.errors.name" />
                    </div>
                    <div>
                        <InputLabel for="email" value="Email" />
                        <TextInput id="email" v-model="form.email" type="email" />
                        <InputError class="mt-2" :message="form.errors.email" />
                    </div>
                    <div>
                        <InputLabel for="phone" value="Phone" />
                        <TextInput id="phone" v-model="form.phone" type="text" />
                        <InputError class="mt-2" :message="form.errors.phone" />
                    </div>
                    <PrimaryButton :disabled="form.processing">Save Changes</PrimaryButton>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
