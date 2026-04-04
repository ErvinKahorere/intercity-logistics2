<script setup>
import { ref, computed, watch } from "vue";
import { Head, Link, useForm } from "@inertiajs/vue3";
import Checkbox from "@/Components/Checkbox.vue";
import InputError from "@/Components/InputError.vue";
import InputLabel from "@/Components/InputLabel.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import TextInput from "@/Components/TextInput.vue";
import GuestLayout from "@/Layouts/GuestLayout.vue";
import api from "@/lib/api";

const form = useForm({
    name: "",
    email: "",
    password: "",
    password_confirmation: "",
    terms: false,
});

const showPassword = ref(false);
const showConfirmPassword = ref(false);
const emailAvailable = ref(null);
const checkingEmail = ref(false);

const emailValid = computed(() => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email || ""));

watch(
    () => form.email,
    async (newEmail) => {
        if (!newEmail || !emailValid.value) {
            emailAvailable.value = null;
            return;
        }
        checkingEmail.value = true;
        try {
            const response = await api.post("/api/email-check", { email: newEmail });
            emailAvailable.value = response.data.available;
        } catch {
            emailAvailable.value = null;
        } finally {
            checkingEmail.value = false;
        }
    }
);

function submit() {
    form.post(route("driver.register"), {
        onFinish: () => form.reset("password", "password_confirmation"),
    });
}
</script>

<template>
    <Head title="Driver Register" />

    <GuestLayout>
        <div class="space-y-6">
            <div class="text-center">
                <p class="text-[11px] font-bold uppercase tracking-[0.28em]" style="color: #2F2E7C;">Driver signup</p>
                <h1 class="mt-3 text-3xl font-black app-title">Join InterCity Logistics as a driver</h1>
                <p class="mt-2 text-sm leading-6 app-muted">Create your driver account to receive matched parcel requests and manage routes.</p>
            </div>

            <form class="space-y-5" @submit.prevent="submit">
                <div>
                    <InputLabel for="name" value="Full name" />
                    <TextInput id="name" v-model="form.name" type="text" required autofocus autocomplete="name" placeholder="Driver full name" />
                    <InputError class="mt-2" :message="form.errors.name" />
                </div>

                <div>
                    <InputLabel for="email" value="Email address" />
                    <TextInput id="email" v-model="form.email" type="email" required autocomplete="email" placeholder="driver@example.com" />
                    <p v-if="checkingEmail" class="mt-2 text-sm app-muted">Checking email availability...</p>
                    <p v-else-if="emailValid && emailAvailable === true" class="mt-2 text-sm" style="color: #2F2E7C;">Email is available.</p>
                    <p v-else-if="emailValid && emailAvailable === false" class="mt-2 text-sm" style="color: #1F1F1F;">Email is already in use.</p>
                    <InputError class="mt-2" :message="form.errors.email" />
                </div>

                <div>
                    <InputLabel for="password" value="Password" />
                    <div class="relative">
                        <TextInput id="password" v-model="form.password" :type="showPassword ? 'text' : 'password'" required autocomplete="new-password" placeholder="Create a strong password" class="pr-12" />
                        <button type="button" class="absolute inset-y-0 right-3 text-sm font-semibold app-muted" @click="showPassword = !showPassword">{{ showPassword ? 'Hide' : 'Show' }}</button>
                    </div>
                    <InputError class="mt-2" :message="form.errors.password" />
                </div>

                <div>
                    <InputLabel for="password_confirmation" value="Confirm password" />
                    <div class="relative">
                        <TextInput id="password_confirmation" v-model="form.password_confirmation" :type="showConfirmPassword ? 'text' : 'password'" required autocomplete="new-password" placeholder="Repeat your password" class="pr-12" />
                        <button type="button" class="absolute inset-y-0 right-3 text-sm font-semibold app-muted" @click="showConfirmPassword = !showConfirmPassword">{{ showConfirmPassword ? 'Hide' : 'Show' }}</button>
                    </div>
                    <InputError class="mt-2" :message="form.errors.password_confirmation" />
                </div>

                <label class="flex items-start gap-3 rounded-[20px] border px-4 py-4 text-sm" style="border-color: var(--app-border); background: var(--app-surface-soft);">
                    <Checkbox name="terms" v-model:checked="form.terms" class="mt-0.5" />
                    <span class="app-muted">I agree to the platform terms and I am ready to receive parcel requests as a driver.</span>
                </label>
                <InputError class="mt-2" :message="form.errors.terms" />

                <PrimaryButton class="w-full justify-center" :disabled="form.processing || !form.terms">
                    {{ form.processing ? 'Creating driver account...' : 'Create Driver Account' }}
                </PrimaryButton>
            </form>

            <div class="rounded-[20px] border px-4 py-4 text-sm" style="border-color: var(--app-border); background: var(--app-surface-soft);">
                <p class="app-muted">
                    Looking for a customer account instead?
                    <Link :href="route('register')" class="font-semibold" style="color: #2F2E7C;">Register here</Link>
                    or
                    <Link :href="route('login')" class="font-semibold" style="color: #2F2E7C;">sign in</Link>.
                </p>
            </div>
        </div>
    </GuestLayout>
</template>

