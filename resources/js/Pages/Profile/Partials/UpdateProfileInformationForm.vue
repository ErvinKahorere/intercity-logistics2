<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';

const props = defineProps({
    mustVerifyEmail: Boolean,
    status: String,
});

const user = usePage().props.auth.user;

const form = useForm({
    name: user.name,
    email: user.email,
});

function submit() {
    form.patch(route('profile.update'));
}
</script>

<template>
    <section>
        <header>
            <h2 class="text-lg font-medium app-title">Profile information</h2>
            <p class="mt-1 text-sm app-muted">Update your account name and email address.</p>
        </header>

        <form @submit.prevent="submit" class="mt-6 space-y-5">
            <div>
                <InputLabel for="name" value="Name" />
                <TextInput id="name" v-model="form.name" type="text" class="mt-1 block w-full" required autofocus autocomplete="name" />
                <InputError class="mt-2" :message="form.errors.name" />
            </div>

            <div>
                <InputLabel for="email" value="Email" />
                <TextInput id="email" v-model="form.email" type="email" class="mt-1 block w-full" required autocomplete="username" />
                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <div v-if="mustVerifyEmail && user.email_verified_at === null" class="rounded-[20px] border px-4 py-4 text-sm" style="border-color: var(--app-border); background: var(--app-surface-soft);">
                <p class="app-muted">
                    Your email address is unverified.
                    <Link :href="route('verification.send')" method="post" as="button" class="font-semibold underline" style="color: #2F2E7C;">
                        Click here to resend the verification email.
                    </Link>
                </p>
                <div v-show="status === 'verification-link-sent'" class="mt-2 font-medium" style="color: #2F2E7C;">
                    A new verification link has been sent to your email address.
                </div>
            </div>

            <div class="flex items-center gap-4">
                <PrimaryButton :disabled="form.processing">Save</PrimaryButton>
                <Transition enter-from-class="opacity-0" leave-to-class="opacity-0" class="transition ease-in-out">
                    <p v-if="form.recentlySuccessful" class="text-sm app-muted">Saved.</p>
                </Transition>
            </div>
        </form>
    </section>
</template>
