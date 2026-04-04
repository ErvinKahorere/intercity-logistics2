<script setup>
import GuestLayout from "@/Layouts/GuestLayout.vue";
import InputError from "@/Components/InputError.vue";
import InputLabel from "@/Components/InputLabel.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import TextInput from "@/Components/TextInput.vue";
import { Head, Link, useForm } from "@inertiajs/vue3";
import { ref } from "vue";

const props = defineProps({
    email: String,
    token: String,
});

const form = useForm({
    token: props.token,
    email: props.email,
    password: "",
    password_confirmation: "",
});

const showPassword = ref(false);
const showPasswordConfirmation = ref(false);

function submit() {
    form.post(route("password.store"), {
        onFinish: () => form.reset("password", "password_confirmation"),
    });
}
</script>

<template>
    <Head title="Reset Password" />

    <GuestLayout>
        <div class="space-y-6">
            <div class="text-center">
                <p class="text-[11px] font-bold uppercase tracking-[0.28em]" style="color: #2F2E7C;">Password reset</p>
                <h1 class="mt-3 text-3xl font-black app-title">Create a new password</h1>
                <p class="mt-2 text-sm leading-6 app-muted">Enter a new password below to regain access to your account.</p>
            </div>

            <form @submit.prevent="submit" class="space-y-5">
                <div>
                    <InputLabel for="email" value="Email" />
                    <TextInput id="email" v-model="form.email" type="email" required autocomplete="username" />
                    <InputError class="mt-2" :message="form.errors.email" />
                </div>

                <div>
                    <InputLabel for="password" value="New Password" />
                    <div class="relative">
                        <TextInput id="password" v-model="form.password" :type="showPassword ? 'text' : 'password'" required autocomplete="new-password" class="pr-12" />
                        <button type="button" class="absolute inset-y-0 right-3 text-sm font-semibold app-muted" @click="showPassword = !showPassword">{{ showPassword ? 'Hide' : 'Show' }}</button>
                    </div>
                    <InputError class="mt-2" :message="form.errors.password" />
                </div>

                <div>
                    <InputLabel for="password_confirmation" value="Confirm Password" />
                    <div class="relative">
                        <TextInput id="password_confirmation" v-model="form.password_confirmation" :type="showPasswordConfirmation ? 'text' : 'password'" required autocomplete="new-password" class="pr-12" />
                        <button type="button" class="absolute inset-y-0 right-3 text-sm font-semibold app-muted" @click="showPasswordConfirmation = !showPasswordConfirmation">{{ showPasswordConfirmation ? 'Hide' : 'Show' }}</button>
                    </div>
                    <InputError class="mt-2" :message="form.errors.password_confirmation" />
                </div>

                <PrimaryButton class="w-full justify-center" :disabled="form.processing">
                    {{ form.processing ? 'Resetting password...' : 'Reset Password' }}
                </PrimaryButton>
            </form>

            <p class="text-sm app-muted">
                Remembered your password?
                <Link :href="route('login')" class="font-semibold" style="color: #2F2E7C;">Sign in</Link>
            </p>
        </div>
    </GuestLayout>
</template>
