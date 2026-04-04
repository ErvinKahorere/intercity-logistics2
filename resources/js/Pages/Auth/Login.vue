<script setup>
import { ref } from "vue";
import Checkbox from "@/Components/Checkbox.vue";
import InputError from "@/Components/InputError.vue";
import InputLabel from "@/Components/InputLabel.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import TextInput from "@/Components/TextInput.vue";
import { Head, Link, useForm } from "@inertiajs/vue3";
import GuestLayout from "@/Layouts/GuestLayout.vue";

defineProps({
    canResetPassword: Boolean,
    status: String,
});

const form = useForm({
    email: "",
    password: "",
    remember: false,
});

const showPassword = ref(false);

function submit() {
    form.post(route("login"), {
        onFinish: () => form.reset("password"),
    });
}
</script>

<template>
    <Head title="Login" />

    <GuestLayout>
        <div class="space-y-6">
            <div class="text-center">
                <p class="text-[11px] font-bold uppercase tracking-[0.28em]" style="color: #2F2E7C;">Account access</p>
                <h1 class="mt-3 text-3xl font-black app-title">Welcome back</h1>
                <p class="mt-2 text-sm leading-6 app-muted">Sign in to manage parcels, drivers, and route activity.</p>
            </div>

            <div v-if="status" class="rounded-[20px] border px-4 py-3 text-sm font-medium" style="border-color: #F2C900; background: rgba(242,201,0,0.16); color: #1F1F1F;">
                {{ status }}
            </div>

            <form class="space-y-5" @submit.prevent="submit">
                <div>
                    <InputLabel for="email" value="Email address" />
                    <TextInput id="email" v-model="form.email" type="email" autocomplete="username" required autofocus placeholder="you@example.com" />
                    <InputError class="mt-2" :message="form.errors.email" />
                </div>

                <div>
                    <InputLabel for="password" value="Password" />
                    <div class="relative">
                        <TextInput id="password" v-model="form.password" :type="showPassword ? 'text' : 'password'" autocomplete="current-password" required placeholder="Enter your password" class="pr-12" />
                        <button type="button" class="absolute inset-y-0 right-3 text-sm font-semibold app-muted" @click="showPassword = !showPassword">
                            {{ showPassword ? 'Hide' : 'Show' }}
                        </button>
                    </div>
                    <InputError class="mt-2" :message="form.errors.password" />
                </div>

                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <label class="flex items-center gap-3 text-sm app-muted">
                        <Checkbox name="remember" v-model:checked="form.remember" />
                        Remember me
                    </label>
                    <Link v-if="canResetPassword" :href="route('password.request')" class="text-sm font-semibold" style="color: #2F2E7C;">
                        Forgot password?
                    </Link>
                </div>

                <PrimaryButton class="w-full justify-center" :disabled="form.processing">
                    {{ form.processing ? 'Signing in...' : 'Sign In' }}
                </PrimaryButton>
            </form>

            <div class="rounded-[20px] border px-4 py-4 text-sm" style="border-color: var(--app-border); background: var(--app-surface-soft);">
                <p class="app-muted">
                    New here?
                    <Link :href="route('register')" class="font-semibold" style="color: #2F2E7C;">Create an account</Link>
                    or
                    <Link :href="route('driver.register')" class="font-semibold" style="color: #2F2E7C;">join as a driver</Link>.
                </p>
            </div>
        </div>
    </GuestLayout>
</template>
