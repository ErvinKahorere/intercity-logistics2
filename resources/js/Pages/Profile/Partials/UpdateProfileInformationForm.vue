<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';

const props = defineProps({ mustVerifyEmail: Boolean, status: String });
const user = usePage().props.auth.user;

const notificationEvents = [
    { key: 'driver_assigned', label: 'Driver assigned' },
    { key: 'job_accepted', label: 'Job accepted' },
    { key: 'parcel_picked_up', label: 'Picked up' },
    { key: 'parcel_in_transit', label: 'In transit' },
    { key: 'parcel_delivered', label: 'Delivered' },
    { key: 'driver_match', label: 'Driver match' },
    { key: 'urgent_job_alert', label: 'Urgent jobs' },
    { key: 'verification_updates', label: 'Verification updates' },
    { key: 'billing_updates', label: 'Billing updates' },
    { key: 'important_alerts', label: 'Important alerts' },
];

function initialPreferences(channel) {
    const source = user?.[`${channel}_notification_preferences`] || {};
    return notificationEvents.reduce((carry, event) => {
        carry[event.key] = source[event.key] ?? true;
        return carry;
    }, {});
}

const form = useForm({
    name: user.name,
    email: user.email,
    phone: user.phone || '',
    location: user.location || '',
    sms_notifications_enabled: !!user.sms_notifications_enabled,
    whatsapp_notifications_enabled: !!user.whatsapp_notifications_enabled,
    email_notifications_enabled: user.email_notifications_enabled ?? true,
    sms_notification_preferences: initialPreferences('sms'),
    whatsapp_notification_preferences: initialPreferences('whatsapp'),
    email_notification_preferences: initialPreferences('email'),
});

function submit() { form.patch(route('profile.update')); }
</script>

<template>
    <section>
        <header>
            <h2 class="text-lg font-medium app-title">Profile information</h2>
            <p class="mt-1 text-sm app-muted">Update your contact details and choose how InterCity should keep you updated.</p>
        </header>

        <form @submit.prevent="submit" class="mt-6 space-y-6">
            <div class="grid gap-5 md:grid-cols-2">
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
                <div>
                    <InputLabel for="phone" value="Phone" />
                    <TextInput id="phone" v-model="form.phone" type="text" class="mt-1 block w-full" autocomplete="tel" placeholder="+264..." />
                    <InputError class="mt-2" :message="form.errors.phone" />
                </div>
                <div>
                    <InputLabel for="location" value="Location" />
                    <TextInput id="location" v-model="form.location" type="text" class="mt-1 block w-full" autocomplete="address-level2" placeholder="Windhoek" />
                    <InputError class="mt-2" :message="form.errors.location" />
                </div>
            </div>

            <div v-if="mustVerifyEmail && user.email_verified_at === null" class="rounded-[20px] border px-4 py-4 text-sm" style="border-color: var(--app-border); background: var(--app-surface-soft);">
                <p class="app-muted">
                    Your email address is unverified.
                    <Link :href="route('verification.send')" method="post" as="button" class="font-semibold underline" style="color: #2F2E7C;">Click here to resend the verification email.</Link>
                </p>
                <div v-show="status === 'verification-link-sent'" class="mt-2 font-medium" style="color: #2F2E7C;">A new verification link has been sent to your email address.</div>
            </div>

            <div class="rounded-[24px] border p-5" style="border-color: var(--app-border); background: var(--app-surface-soft);">
                <div class="mb-4">
                    <h3 class="text-base font-black app-title">Notification channels</h3>
                    <p class="mt-1 text-sm app-muted">SMS stays best for urgent parcel updates. WhatsApp is great for chat-style alerts. Email works best for invoices, quotations, and summaries.</p>
                </div>

                <div class="grid gap-4 lg:grid-cols-3">
                    <label class="rounded-[20px] border p-4" style="border-color: var(--app-border); background: var(--app-surface);">
                        <div class="flex items-start justify-between gap-4">
                            <div><div class="text-sm font-black app-title">SMS</div><p class="mt-1 text-xs app-muted">Fast delivery alerts.</p></div>
                            <input v-model="form.sms_notifications_enabled" type="checkbox" class="mt-1 h-4 w-4 rounded border-gray-300 text-[#2F2E7C] focus:ring-[#2F2E7C]" />
                        </div>
                    </label>
                    <label class="rounded-[20px] border p-4" style="border-color: var(--app-border); background: var(--app-surface);">
                        <div class="flex items-start justify-between gap-4">
                            <div><div class="text-sm font-black app-title">WhatsApp</div><p class="mt-1 text-xs app-muted">Best for conversational updates.</p></div>
                            <input v-model="form.whatsapp_notifications_enabled" type="checkbox" class="mt-1 h-4 w-4 rounded border-gray-300 text-[#2F2E7C] focus:ring-[#2F2E7C]" />
                        </div>
                    </label>
                    <label class="rounded-[20px] border p-4" style="border-color: var(--app-border); background: var(--app-surface);">
                        <div class="flex items-start justify-between gap-4">
                            <div><div class="text-sm font-black app-title">Email</div><p class="mt-1 text-xs app-muted">Receipts, invoices, and summaries.</p></div>
                            <input v-model="form.email_notifications_enabled" type="checkbox" class="mt-1 h-4 w-4 rounded border-gray-300 text-[#2F2E7C] focus:ring-[#2F2E7C]" />
                        </div>
                    </label>
                </div>

                <div class="mt-5 grid gap-4 xl:grid-cols-3">
                    <div class="rounded-[20px] border p-4" style="border-color: var(--app-border); background: var(--app-surface);">
                        <div class="mb-3 text-sm font-black app-title">SMS events</div>
                        <div class="grid gap-2">
                            <label v-for="event in notificationEvents" :key="`sms-${event.key}`" class="flex items-center justify-between gap-3 rounded-2xl px-3 py-2" style="background: var(--app-surface-soft);">
                                <span class="text-sm">{{ event.label }}</span>
                                <input v-model="form.sms_notification_preferences[event.key]" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-[#2F2E7C] focus:ring-[#2F2E7C]" />
                            </label>
                        </div>
                    </div>
                    <div class="rounded-[20px] border p-4" style="border-color: var(--app-border); background: var(--app-surface);">
                        <div class="mb-3 text-sm font-black app-title">WhatsApp events</div>
                        <div class="grid gap-2">
                            <label v-for="event in notificationEvents" :key="`whatsapp-${event.key}`" class="flex items-center justify-between gap-3 rounded-2xl px-3 py-2" style="background: var(--app-surface-soft);">
                                <span class="text-sm">{{ event.label }}</span>
                                <input v-model="form.whatsapp_notification_preferences[event.key]" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-[#2F2E7C] focus:ring-[#2F2E7C]" />
                            </label>
                        </div>
                    </div>
                    <div class="rounded-[20px] border p-4" style="border-color: var(--app-border); background: var(--app-surface);">
                        <div class="mb-3 text-sm font-black app-title">Email events</div>
                        <div class="grid gap-2">
                            <label v-for="event in notificationEvents" :key="`email-${event.key}`" class="flex items-center justify-between gap-3 rounded-2xl px-3 py-2" style="background: var(--app-surface-soft);">
                                <span class="text-sm">{{ event.label }}</span>
                                <input v-model="form.email_notification_preferences[event.key]" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-[#2F2E7C] focus:ring-[#2F2E7C]" />
                            </label>
                        </div>
                    </div>
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
