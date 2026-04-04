<script setup>
import { computed, ref } from "vue";
import { Head, useForm } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import PageHeader from "@/Components/AppShell/PageHeader.vue";
import InputLabel from "@/Components/InputLabel.vue";
import InputError from "@/Components/InputError.vue";
import TextInput from "@/Components/TextInput.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import StatusBadge from "@/Components/AppShell/StatusBadge.vue";
import api from "@/lib/api";
import { emitAppRefresh } from "@/composables/useLivePage";
import { errorToast, successToast } from "@/composables/useAppToast";
import { AlertCircle, BadgeCheck, BellRing, Building2, CreditCard, LoaderCircle, LockKeyhole, ShieldCheck, Upload } from "lucide-vue-next";

const props = defineProps({
    user: { type: Object, default: () => ({}) },
    driverProfile: { type: Object, default: () => ({}) },
    licenceTypes: { type: Array, default: () => [] },
});

const activeTab = ref("overview");
const user = ref({ ...(props.user || {}) });
const driverProfile = ref({ ...(props.driverProfile || {}) });
const defaultProfileImage = "/images/Default_pfp.jpg";
const previewImage = ref(user.value.profile_photo_url || defaultProfileImage);
const selectedProfilePhoto = ref(null);
const fileInput = ref(null);

const profileForm = useForm({
    name: user.value.name || "",
    email: user.value.email || "",
    phone: user.value.phone || "",
    location: user.value.location || "",
});

const verificationForm = useForm({
    licence_type_code: driverProfile.value.primary_licence?.licence_type_code || "",
    licence_type_name: driverProfile.value.primary_licence?.licence_type_name || "",
    licence_number: driverProfile.value.primary_licence?.licence_number || "",
    issue_date: driverProfile.value.primary_licence?.issue_date || "",
    expiry_date: driverProfile.value.primary_licence?.expiry_date || "",
    licence_document: null,
});

const bankingForm = useForm({
    account_holder_name: driverProfile.value.bank_account?.account_holder_name || user.value.name || "",
    bank_name: driverProfile.value.bank_account?.bank_name || "",
    branch_name: driverProfile.value.bank_account?.branch_name || "",
    branch_code: driverProfile.value.bank_account?.branch_code || "",
    account_number: "",
    account_type: driverProfile.value.bank_account?.account_type || "current",
    payout_reference_name: driverProfile.value.bank_account?.payout_reference_name || "",
});

const verificationStatusTone = computed(() => ({
    verified: "background:#E7F7EE;color:#156B45;",
    pending: "background:rgba(47,46,124,0.08);color:#2F2E7C;",
    rejected: "background:rgba(220,38,38,0.08);color:#b91c1c;",
    unverified: "background:var(--app-surface-soft);color:var(--app-text);",
}[driverProfile.value.verification_status || "unverified"]));

const verificationStatusLabel = computed(() =>
    String(driverProfile.value.verification_status || "unverified").replaceAll("_", " ").replace(/\b\w/g, (char) => char.toUpperCase())
);

const maskedAccount = computed(() => driverProfile.value.bank_account?.masked_account_number || "No account saved");
const bankingStatus = computed(() =>
    String(driverProfile.value.bank_account?.status || "incomplete").replaceAll("_", " ").replace(/\b\w/g, (char) => char.toUpperCase())
);
const primaryLicence = computed(() => driverProfile.value.primary_licence || null);
const licenceExpiryMeta = computed(() => {
    if (!primaryLicence.value?.expiry_date) {
        return null;
    }

    const today = new Date();
    today.setHours(0, 0, 0, 0);

    const expiry = new Date(primaryLicence.value.expiry_date);
    expiry.setHours(0, 0, 0, 0);

    const daysRemaining = Math.round((expiry.getTime() - today.getTime()) / 86400000);

    if (Number.isNaN(daysRemaining)) {
        return null;
    }

    if (daysRemaining < 0) {
        return {
            tone: "danger",
            title: "Licence expired",
            description: `This licence expired ${Math.abs(daysRemaining)} day${Math.abs(daysRemaining) === 1 ? "" : "s"} ago. Update it to keep matching and payouts operational.`,
        };
    }

    if (daysRemaining <= 30) {
        return {
            tone: "warning",
            title: "Licence expiring soon",
            description: `Your primary licence expires in ${daysRemaining} day${daysRemaining === 1 ? "" : "s"}. Renew it early to avoid interruptions.`,
        };
    }

    return {
        tone: "success",
        title: "Licence on file",
        description: `Your current licence remains valid for another ${daysRemaining} day${daysRemaining === 1 ? "" : "s"}.`,
    };
});
const profileReadiness = computed(() => [
    {
        label: "Verification",
        value: verificationStatusLabel.value,
        meta: driverProfile.value.verification_status === "verified" ? "Trusted for customer-facing matching" : "Complete your licence review to strengthen trust",
        tone: driverProfile.value.verification_status === "verified" ? "success" : driverProfile.value.verification_status === "pending" ? "brand" : driverProfile.value.verification_status === "rejected" ? "danger" : "neutral",
    },
    {
        label: "Licence",
        value: primaryLicence.value?.licence_type_name || "Not uploaded",
        meta: licenceExpiryMeta.value?.title || "No licence expiry recorded yet",
        tone: licenceExpiryMeta.value?.tone || "neutral",
    },
    {
        label: "Payouts",
        value: bankingStatus.value,
        meta: driverProfile.value.bank_account?.masked_account_number ? "Banking details stored securely" : "Add banking to receive payouts faster",
        tone: driverProfile.value.bank_account?.masked_account_number ? "success" : "neutral",
    },
]);
const verificationTrustMessage = computed(() => {
    if (driverProfile.value.verification_status === "verified") {
        return "Verified drivers surface with stronger trust cues during matching and customer review.";
    }

    if (driverProfile.value.verification_status === "pending") {
        return "Your review is in progress. We keep your latest licence details on file while verification is checked.";
    }

    if (driverProfile.value.verification_status === "rejected") {
        return "A corrected document and updated licence details are required before the verification badge can return.";
    }

    return "Submit your latest licence to unlock verified-driver trust cues and keep future payout checks straightforward.";
});

function applyDriverProfile(payload) {
    driverProfile.value = { ...(payload || {}) };
    verificationForm.licence_type_code = driverProfile.value.primary_licence?.licence_type_code || "";
    verificationForm.licence_type_name = driverProfile.value.primary_licence?.licence_type_name || "";
    verificationForm.licence_number = driverProfile.value.primary_licence?.licence_number || "";
    verificationForm.issue_date = driverProfile.value.primary_licence?.issue_date || "";
    verificationForm.expiry_date = driverProfile.value.primary_licence?.expiry_date || "";
    verificationForm.licence_document = null;

    bankingForm.account_holder_name = driverProfile.value.bank_account?.account_holder_name || user.value.name || "";
    bankingForm.bank_name = driverProfile.value.bank_account?.bank_name || "";
    bankingForm.branch_name = driverProfile.value.bank_account?.branch_name || "";
    bankingForm.branch_code = driverProfile.value.bank_account?.branch_code || "";
    bankingForm.account_number = "";
    bankingForm.account_type = driverProfile.value.bank_account?.account_type || "current";
    bankingForm.payout_reference_name = driverProfile.value.bank_account?.payout_reference_name || "";
}

function handlePhotoChange(event) {
    const file = event.target.files?.[0] || null;
    selectedProfilePhoto.value = file;

    if (file) {
        const reader = new FileReader();
        reader.onload = (e) => {
            previewImage.value = e.target?.result || defaultProfileImage;
        };
        reader.readAsDataURL(file);
    } else {
        previewImage.value = user.value.profile_photo_url || defaultProfileImage;
    }
}

async function saveProfile() {
    const payload = new FormData();
    payload.append("name", profileForm.name || "");
    payload.append("email", profileForm.email || "");
    payload.append("phone", profileForm.phone || "");
    payload.append("location", profileForm.location || "");

    if (selectedProfilePhoto.value) {
        payload.append("profile_photo", selectedProfilePhoto.value);
    }

    profileForm.processing = true;
    profileForm.clearErrors();

    try {
        const { data } = await api.post(route("driver.profile.update"), payload, {
            headers: {
                "Content-Type": "multipart/form-data",
                Accept: "application/json",
            },
        });

        user.value = data.user || user.value;
        profileForm.name = user.value.name || "";
        profileForm.email = user.value.email || "";
        profileForm.phone = user.value.phone || "";
        profileForm.location = user.value.location || "";
        previewImage.value = user.value.profile_photo_url || defaultProfileImage;
        selectedProfilePhoto.value = null;
        successToast(data.message || "Profile updated.", "Profile saved");
        emitAppRefresh({ only: ["auth", "appNotifications"] });
    } catch (error) {
        const errors = error.response?.data?.errors || {};
        profileForm.setError(errors);
        errorToast(error.response?.data?.message || "Could not save profile.", "Save failed");
    } finally {
        profileForm.processing = false;
    }
}

async function submitVerification() {
    const payload = new FormData();
    payload.append("licence_type_code", verificationForm.licence_type_code);
    payload.append("licence_type_name", verificationForm.licence_type_name);
    payload.append("licence_number", verificationForm.licence_number || "");
    payload.append("issue_date", verificationForm.issue_date || "");
    payload.append("expiry_date", verificationForm.expiry_date || "");

    if (verificationForm.licence_document) {
        payload.append("licence_document", verificationForm.licence_document);
    }

    verificationForm.processing = true;
    verificationForm.clearErrors();

    try {
        const { data } = await api.post(route("driver.profile.verification.submit"), payload, {
            headers: {
                "Content-Type": "multipart/form-data",
                Accept: "application/json",
            },
        });

        applyDriverProfile(data.driverProfile);
        successToast(data.message || "Verification submitted.", "Verification");
    } catch (error) {
        verificationForm.setError(error.response?.data?.errors || {});
        errorToast(error.response?.data?.message || "Could not submit verification.", "Submission failed");
    } finally {
        verificationForm.processing = false;
    }
}

async function saveBanking() {
    bankingForm.processing = true;
    bankingForm.clearErrors();

    try {
        const { data } = await api.post(route("driver.profile.banking.save"), bankingForm.data(), {
            headers: { Accept: "application/json" },
        });

        applyDriverProfile(data.driverProfile);
        successToast(data.message || "Banking details saved.", "Banking updated");
    } catch (error) {
        bankingForm.setError(error.response?.data?.errors || {});
        errorToast(error.response?.data?.message || "Could not save banking details.", "Save failed");
    } finally {
        bankingForm.processing = false;
    }
}

function selectLicenceType(event) {
    const selected = props.licenceTypes.find((item) => item.code === event.target.value);
    verificationForm.licence_type_code = selected?.code || "";
    verificationForm.licence_type_name = selected?.label || "";
}
</script>

<template>
    <Head title="Driver Profile" />

    <AuthenticatedLayout>
        <PageHeader eyebrow="Driver account" title="Profile & Compliance" description="Keep your profile, licence verification, and payout details operationally ready." />

        <div class="grid gap-6 xl:grid-cols-[320px_minmax(0,1fr)]">
            <aside class="app-panel rounded-[30px] p-6">
                <div class="flex flex-col items-center text-center">
                    <img :src="previewImage" alt="Driver profile" class="h-28 w-28 rounded-[28px] object-cover" />
                    <div class="mt-4 text-2xl font-black app-title">{{ user.name }}</div>
                    <div class="mt-1 text-sm app-muted">{{ user.email }}</div>
                    <div class="mt-3">
                        <StatusBadge :label="verificationStatusLabel" :tone="driverProfile.verification_status === 'verified' ? 'success' : driverProfile.verification_status === 'pending' ? 'brand' : driverProfile.verification_status === 'rejected' ? 'danger' : 'neutral'" />
                    </div>
                    <p class="mt-3 text-sm leading-6 app-muted">{{ verificationTrustMessage }}</p>
                </div>

                <div class="mt-6 space-y-3">
                    <button type="button" class="w-full rounded-[20px] px-4 py-3 text-left text-sm font-bold uppercase tracking-[0.14em]" :style="activeTab === 'overview' ? 'background:#2F2E7C;color:#FFFFFF;' : 'background:var(--app-surface-soft);color:var(--app-text);'" @click="activeTab = 'overview'">Overview</button>
                    <button type="button" class="w-full rounded-[20px] px-4 py-3 text-left text-sm font-bold uppercase tracking-[0.14em]" :style="activeTab === 'profile' ? 'background:#2F2E7C;color:#FFFFFF;' : 'background:var(--app-surface-soft);color:var(--app-text);'" @click="activeTab = 'profile'">Profile Details</button>
                    <button type="button" class="w-full rounded-[20px] px-4 py-3 text-left text-sm font-bold uppercase tracking-[0.14em]" :style="activeTab === 'verification' ? 'background:#2F2E7C;color:#FFFFFF;' : 'background:var(--app-surface-soft);color:var(--app-text);'" @click="activeTab = 'verification'">Verification</button>
                    <button type="button" class="w-full rounded-[20px] px-4 py-3 text-left text-sm font-bold uppercase tracking-[0.14em]" :style="activeTab === 'banking' ? 'background:#2F2E7C;color:#FFFFFF;' : 'background:var(--app-surface-soft);color:var(--app-text);'" @click="activeTab = 'banking'">Banking</button>
                    <button type="button" class="w-full rounded-[20px] px-4 py-3 text-left text-sm font-bold uppercase tracking-[0.14em]" :style="activeTab === 'accounting' ? 'background:#2F2E7C;color:#FFFFFF;' : 'background:var(--app-surface-soft);color:var(--app-text);'" @click="activeTab = 'accounting'">Accounting</button>
                </div>

                <div class="mt-6 rounded-[24px] border p-4" style="border-color: var(--app-border); background: var(--app-surface-soft);">
                    <div class="text-[11px] font-bold uppercase tracking-[0.16em] app-muted">Operational readiness</div>
                    <div class="mt-4 space-y-3">
                        <div v-for="item in profileReadiness" :key="item.label" class="flex items-start justify-between gap-3 rounded-[18px] border px-4 py-3" style="border-color: rgba(31,31,31,0.06); background: rgba(255,255,255,0.62);">
                            <div class="min-w-0">
                                <div class="text-[10px] font-bold uppercase tracking-[0.16em] app-muted">{{ item.label }}</div>
                                <div class="mt-1 text-sm font-black app-title">{{ item.value }}</div>
                                <div class="mt-1 text-xs leading-5 app-muted">{{ item.meta }}</div>
                            </div>
                            <StatusBadge :label="item.label" :tone="item.tone" small />
                        </div>
                    </div>
                </div>
            </aside>

            <section class="space-y-6">
                <div v-if="activeTab === 'overview'" class="grid gap-6 lg:grid-cols-2">
                    <article class="app-panel rounded-[30px] p-6">
                        <div class="flex items-center gap-3">
                            <ShieldCheck class="h-5 w-5" style="color:#2F2E7C;" />
                            <div>
                                <div class="text-[11px] font-bold uppercase tracking-[0.18em] app-muted">Verification status</div>
                                <div class="mt-1 text-2xl font-black app-title">{{ verificationStatusLabel }}</div>
                            </div>
                        </div>
                        <p class="mt-4 text-sm leading-6 app-muted">
                            <span v-if="driverProfile.verification_status === 'verified'">Your licence is verified and your profile is ready for trusted matching.</span>
                            <span v-else-if="driverProfile.verification_status === 'pending'">Your verification is under review. We will update your status once the licence has been checked.</span>
                            <span v-else-if="driverProfile.verification_status === 'rejected'">{{ driverProfile.verification_rejection_reason || "Please correct the licence details and resubmit." }}</span>
                            <span v-else>Upload your licence and submit it for verification to unlock stronger trust signals and payout readiness.</span>
                        </p>
                        <div v-if="licenceExpiryMeta" class="mt-5">
                            <div class="rounded-[24px] border px-4 py-4" :style="licenceExpiryMeta.tone === 'danger' ? 'border-color: rgba(220,38,38,0.18); background: rgba(220,38,38,0.06); color:#991b1b;' : licenceExpiryMeta.tone === 'warning' ? 'border-color: rgba(242,201,0,0.55); background: rgba(242,201,0,0.12); color:#1F1F1F;' : 'border-color: rgba(21,107,69,0.18); background: rgba(21,107,69,0.06); color:#156B45;'">
                                <div class="inline-flex items-center gap-2 text-[11px] font-bold uppercase tracking-[0.16em]">
                                    <BellRing class="h-4 w-4" />
                                    {{ licenceExpiryMeta.title }}
                                </div>
                                <p class="mt-2 text-sm leading-6">{{ licenceExpiryMeta.description }}</p>
                            </div>
                        </div>
                        <div v-if="driverProfile.primary_licence" class="mt-5 rounded-[24px] border p-4" style="border-color: var(--app-border); background: var(--app-surface-soft);">
                            <div class="text-[11px] font-bold uppercase tracking-[0.16em] app-muted">Primary licence</div>
                            <div class="mt-2 text-lg font-black app-title">{{ driverProfile.primary_licence.licence_type_name }}</div>
                            <div class="mt-1 text-sm app-muted">Expires {{ driverProfile.primary_licence.expiry_date }}</div>
                            <div class="mt-3 flex flex-wrap gap-2">
                                <StatusBadge :label="driverProfile.primary_licence.status_summary || verificationStatusLabel" :tone="driverProfile.verification_status === 'verified' ? 'success' : driverProfile.verification_status === 'pending' ? 'brand' : driverProfile.verification_status === 'rejected' ? 'danger' : 'neutral'" small />
                                <StatusBadge v-if="driverProfile.primary_licence.issue_date" :label="`Issued ${driverProfile.primary_licence.issue_date}`" tone="neutral" small />
                            </div>
                        </div>
                    </article>

                    <article class="app-panel rounded-[30px] p-6">
                        <div class="flex items-center gap-3">
                            <Building2 class="h-5 w-5" style="color:#2F2E7C;" />
                            <div>
                                <div class="text-[11px] font-bold uppercase tracking-[0.18em] app-muted">Payout readiness</div>
                                <div class="mt-1 text-2xl font-black app-title">{{ bankingStatus }}</div>
                            </div>
                        </div>
                        <p class="mt-4 text-sm leading-6 app-muted">
                            Banking details are masked after save and only exposed to authorized operational users.
                        </p>
                        <div class="mt-5 grid gap-3 sm:grid-cols-2">
                            <div class="rounded-[22px] p-4" style="background: var(--app-surface-soft);">
                                <div class="text-[11px] font-bold uppercase tracking-[0.16em] app-muted">Bank</div>
                                <div class="mt-2 text-base font-black app-title">{{ driverProfile.bank_account?.bank_name || "Not added" }}</div>
                            </div>
                            <div class="rounded-[22px] p-4" style="background: var(--app-surface-soft);">
                                <div class="text-[11px] font-bold uppercase tracking-[0.16em] app-muted">Account</div>
                                <div class="mt-2 text-base font-black app-title">{{ maskedAccount }}</div>
                            </div>
                        </div>
                        <div class="mt-5 rounded-[24px] border px-4 py-4" style="border-color: var(--app-border); background: rgba(47,46,124,0.05);">
                            <div class="inline-flex items-center gap-2 text-[11px] font-bold uppercase tracking-[0.16em]" style="color:#2F2E7C;">
                                <LockKeyhole class="h-4 w-4" />
                                Security note
                            </div>
                            <p class="mt-2 text-sm leading-6 app-muted">
                                Only masked account details are shown after save. Full banking data is reserved for authorized payout operations.
                            </p>
                        </div>
                    </article>
                </div>

                <div v-else-if="activeTab === 'profile'" class="app-panel rounded-[30px] p-6">
                    <div class="grid gap-6 lg:grid-cols-[220px_minmax(0,1fr)]">
                        <div>
                            <img :src="previewImage" alt="Preview" class="h-40 w-40 rounded-[28px] object-cover" />
                            <div class="mt-4">
                                <label class="inline-flex cursor-pointer items-center gap-2 rounded-[18px] border px-4 py-3 text-sm font-semibold" style="border-color: var(--app-border); background: var(--app-surface-soft); color: var(--app-text);">
                                    <Upload class="h-4 w-4" />
                                    Replace photo
                                    <input ref="fileInput" type="file" class="hidden" accept="image/*,.heic,.heif" @change="handlePhotoChange" />
                                </label>
                            </div>
                        </div>

                        <form class="space-y-5" @submit.prevent="saveProfile">
                            <div>
                                <InputLabel for="driver-name" value="Full Name" />
                                <TextInput id="driver-name" v-model="profileForm.name" type="text" />
                                <InputError class="mt-2" :message="profileForm.errors.name" />
                            </div>
                            <div>
                                <InputLabel for="driver-email" value="Email" />
                                <TextInput id="driver-email" v-model="profileForm.email" type="email" />
                                <InputError class="mt-2" :message="profileForm.errors.email" />
                            </div>
                            <div class="grid gap-5 md:grid-cols-2">
                                <div>
                                    <InputLabel for="driver-phone" value="Phone" />
                                    <TextInput id="driver-phone" v-model="profileForm.phone" type="text" />
                                    <InputError class="mt-2" :message="profileForm.errors.phone" />
                                </div>
                                <div>
                                    <InputLabel for="driver-location" value="Location" />
                                    <TextInput id="driver-location" v-model="profileForm.location" type="text" />
                                    <InputError class="mt-2" :message="profileForm.errors.location" />
                                </div>
                            </div>
                            <PrimaryButton :disabled="profileForm.processing">
                                <LoaderCircle v-if="profileForm.processing" class="h-4 w-4 animate-spin" />
                                {{ profileForm.processing ? "Saving..." : "Save Profile" }}
                            </PrimaryButton>
                        </form>
                    </div>
                </div>

                <div v-else-if="activeTab === 'verification'" class="space-y-6">
                    <article class="app-panel rounded-[30px] p-6">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                            <div>
                                <div class="text-[11px] font-bold uppercase tracking-[0.18em] app-muted">Driver verification</div>
                                <h2 class="mt-2 text-3xl font-black app-title">Licence details and document upload</h2>
                            </div>
                            <div class="rounded-full px-4 py-2 text-[11px] font-bold uppercase tracking-[0.16em]" :style="verificationStatusTone">
                                {{ verificationStatusLabel }}
                            </div>
                        </div>

                        <div v-if="driverProfile.verification_status === 'rejected'" class="mt-5 rounded-[22px] border px-4 py-4 text-sm" style="border-color: rgba(220,38,38,0.16); background: rgba(220,38,38,0.06); color:#b91c1c;">
                            <div class="inline-flex items-center gap-2 font-bold uppercase tracking-[0.16em]">
                                <AlertCircle class="h-4 w-4" />
                                Rejection reason
                            </div>
                            <p class="mt-2">{{ driverProfile.verification_rejection_reason }}</p>
                        </div>
                        <div v-else-if="licenceExpiryMeta" class="mt-5 rounded-[22px] border px-4 py-4 text-sm" :style="licenceExpiryMeta.tone === 'danger' ? 'border-color: rgba(220,38,38,0.16); background: rgba(220,38,38,0.06); color:#991b1b;' : licenceExpiryMeta.tone === 'warning' ? 'border-color: rgba(242,201,0,0.55); background: rgba(242,201,0,0.12); color:#1F1F1F;' : 'border-color: rgba(21,107,69,0.16); background: rgba(21,107,69,0.06); color:#156B45;'">
                            <div class="inline-flex items-center gap-2 font-bold uppercase tracking-[0.16em]">
                                <BellRing class="h-4 w-4" />
                                {{ licenceExpiryMeta.title }}
                            </div>
                            <p class="mt-2">{{ licenceExpiryMeta.description }}</p>
                        </div>

                        <form class="mt-6 grid gap-5 md:grid-cols-2" @submit.prevent="submitVerification">
                            <div>
                                <InputLabel value="Licence Type" />
                                <select v-model="verificationForm.licence_type_code" class="app-field" @change="selectLicenceType">
                                    <option value="">Select licence type</option>
                                    <option v-for="type in licenceTypes" :key="type.code" :value="type.code">{{ type.label }}</option>
                                </select>
                                <p class="mt-2 text-xs leading-5 app-muted">Choose the transport licence category that best matches the vehicle work you take on.</p>
                                <InputError class="mt-2" :message="verificationForm.errors.licence_type_code" />
                            </div>

                            <div>
                                <InputLabel value="Licence Number" />
                                <TextInput v-model="verificationForm.licence_number" type="text" />
                                <p class="mt-2 text-xs leading-5 app-muted">Enter the printed licence number exactly as it appears on the document.</p>
                                <InputError class="mt-2" :message="verificationForm.errors.licence_number" />
                            </div>

                            <div>
                                <InputLabel value="Issue Date" />
                                <TextInput v-model="verificationForm.issue_date" type="date" />
                                <InputError class="mt-2" :message="verificationForm.errors.issue_date" />
                            </div>

                            <div>
                                <InputLabel value="Expiry Date" />
                                <TextInput v-model="verificationForm.expiry_date" type="date" />
                                <InputError class="mt-2" :message="verificationForm.errors.expiry_date" />
                            </div>

                            <div class="md:col-span-2">
                                <InputLabel value="Licence Document" />
                                <label class="mt-2 flex cursor-pointer items-center gap-3 rounded-[20px] border px-4 py-4" style="border-color: var(--app-border); background: var(--app-surface-soft); color: var(--app-text);">
                                    <Upload class="h-4 w-4" />
                                    <span>{{ verificationForm.licence_document?.name || "Upload PDF, JPG, JPEG, or PNG" }}</span>
                                    <input type="file" class="hidden" accept=".pdf,.jpg,.jpeg,.png" @change="verificationForm.licence_document = $event.target.files?.[0] || null" />
                                </label>
                                <p class="mt-2 text-xs leading-5 app-muted">Accepted formats: PDF, JPG, JPEG, PNG. Use a clear image with all licence details visible.</p>
                                <InputError class="mt-2" :message="verificationForm.errors.licence_document" />
                            </div>

                            <div class="md:col-span-2">
                                <PrimaryButton :disabled="verificationForm.processing">
                                    <LoaderCircle v-if="verificationForm.processing" class="h-4 w-4 animate-spin" />
                                    {{ verificationForm.processing ? "Submitting..." : "Submit for Verification" }}
                                </PrimaryButton>
                            </div>
                        </form>
                    </article>

                    <article class="app-panel rounded-[30px] p-6">
                        <div class="text-[11px] font-bold uppercase tracking-[0.18em] app-muted">Licence history</div>
                        <div v-if="driverProfile.licences?.length" class="mt-5 grid gap-4">
                            <div v-for="licence in driverProfile.licences" :key="licence.id" class="rounded-[24px] border p-4" style="border-color: var(--app-border); background: var(--app-surface-soft);">
                                <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                                    <div>
                                        <div class="text-lg font-black app-title">{{ licence.licence_type_name }}</div>
                                        <div class="mt-1 text-sm app-muted">Number: {{ licence.licence_number || "Not provided" }}</div>
                                        <div class="mt-1 text-sm app-muted">Expiry: {{ licence.expiry_date }}</div>
                                    </div>
                                    <StatusBadge :label="licence.status_summary" :tone="licence.verification_status === 'verified' ? 'success' : licence.verification_status === 'pending' ? 'brand' : ['rejected', 'expired'].includes(licence.verification_status) ? 'danger' : 'neutral'" />
                                </div>
                                <a v-if="licence.document_url" :href="licence.document_url" target="_blank" class="mt-4 inline-flex items-center gap-2 text-sm font-bold uppercase tracking-[0.14em]" style="color:#2F2E7C;">
                                    View uploaded document
                                    <BadgeCheck class="h-4 w-4" />
                                </a>
                            </div>
                        </div>
                        <div v-else class="mt-5 rounded-[24px] p-5 text-sm app-muted" style="background: var(--app-surface-soft);">
                            No licence submissions yet.
                        </div>
                    </article>
                </div>

                <div v-else-if="activeTab === 'banking'" class="space-y-6">
                    <article class="app-panel rounded-[30px] p-6">
                        <div class="flex items-center gap-3">
                            <CreditCard class="h-5 w-5" style="color:#2F2E7C;" />
                            <div>
                                <div class="text-[11px] font-bold uppercase tracking-[0.18em] app-muted">Banking details</div>
                                <h2 class="mt-1 text-3xl font-black app-title">Payout account</h2>
                            </div>
                        </div>

                        <div class="mt-5 grid gap-4 md:grid-cols-2">
                            <div class="rounded-[22px] p-4" style="background: var(--app-surface-soft);">
                                <div class="text-[11px] font-bold uppercase tracking-[0.16em] app-muted">Current account</div>
                                <div class="mt-2 text-lg font-black app-title">{{ maskedAccount }}</div>
                                <div class="mt-1 text-sm app-muted">{{ bankingStatus }}</div>
                                <div class="mt-2 text-xs leading-5 app-muted">Only the masked number is displayed after save for privacy and payout security.</div>
                            </div>
                            <div class="rounded-[22px] p-4" style="background: var(--app-surface-soft);">
                                <div class="text-[11px] font-bold uppercase tracking-[0.16em] app-muted">Bank</div>
                                <div class="mt-2 text-lg font-black app-title">{{ driverProfile.bank_account?.bank_name || "Not added" }}</div>
                                <div class="mt-1 text-sm app-muted">{{ driverProfile.bank_account?.account_type || "Account type pending" }}</div>
                            </div>
                        </div>

                        <form class="mt-6 grid gap-5 md:grid-cols-2" @submit.prevent="saveBanking">
                            <div>
                                <InputLabel value="Account Holder Name" />
                                <TextInput v-model="bankingForm.account_holder_name" type="text" />
                                <InputError class="mt-2" :message="bankingForm.errors.account_holder_name" />
                            </div>
                            <div>
                                <InputLabel value="Bank Name" />
                                <TextInput v-model="bankingForm.bank_name" type="text" />
                                <InputError class="mt-2" :message="bankingForm.errors.bank_name" />
                            </div>
                            <div>
                                <InputLabel value="Branch Name" />
                                <TextInput v-model="bankingForm.branch_name" type="text" />
                                <InputError class="mt-2" :message="bankingForm.errors.branch_name" />
                            </div>
                            <div>
                                <InputLabel value="Branch Code" />
                                <TextInput v-model="bankingForm.branch_code" type="text" />
                                <InputError class="mt-2" :message="bankingForm.errors.branch_code" />
                            </div>
                            <div>
                                <InputLabel value="Account Number" />
                                <TextInput v-model="bankingForm.account_number" type="text" />
                                <p class="mt-2 text-xs leading-5 app-muted">Use digits only where possible. The account number will be masked after it is stored.</p>
                                <InputError class="mt-2" :message="bankingForm.errors.account_number" />
                            </div>
                            <div>
                                <InputLabel value="Account Type" />
                                <select v-model="bankingForm.account_type" class="app-field">
                                    <option value="current">Current</option>
                                    <option value="savings">Savings</option>
                                    <option value="business">Business</option>
                                    <option value="cheque">Cheque</option>
                                </select>
                                <InputError class="mt-2" :message="bankingForm.errors.account_type" />
                            </div>
                            <div class="md:col-span-2">
                                <InputLabel value="Payout Reference Name" />
                                <TextInput v-model="bankingForm.payout_reference_name" type="text" />
                                <p class="mt-2 text-xs leading-5 app-muted">Optional label to help operations identify this payout account quickly.</p>
                                <InputError class="mt-2" :message="bankingForm.errors.payout_reference_name" />
                            </div>
                            <div class="md:col-span-2">
                                <PrimaryButton :disabled="bankingForm.processing">
                                    <LoaderCircle v-if="bankingForm.processing" class="h-4 w-4 animate-spin" />
                                    {{ bankingForm.processing ? "Saving..." : "Save Banking Details" }}
                                </PrimaryButton>
                            </div>
                        </form>
                    </article>
                </div>

                <div v-else class="space-y-6">
                    <article class="app-panel rounded-[30px] p-6">
                        <div class="flex items-center gap-3">
                            <CreditCard class="h-5 w-5" style="color:#2F2E7C;" />
                            <div>
                                <div class="text-[11px] font-bold uppercase tracking-[0.18em] app-muted">Accounting</div>
                                <h2 class="mt-1 text-3xl font-black app-title">Invoice history and payout view</h2>
                            </div>
                        </div>

                        <div class="mt-5 grid gap-4 md:grid-cols-4">
                            <div class="rounded-[22px] p-4" style="background: var(--app-surface-soft);">
                                <div class="text-[11px] font-bold uppercase tracking-[0.16em] app-muted">Invoices</div>
                                <div class="mt-2 text-2xl font-black app-title">{{ driverProfile.accounting?.invoice_count || 0 }}</div>
                            </div>
                            <div class="rounded-[22px] p-4" style="background: var(--app-surface-soft);">
                                <div class="text-[11px] font-bold uppercase tracking-[0.16em] app-muted">Issued value</div>
                                <div class="mt-2 text-2xl font-black app-title">N$ {{ Number(driverProfile.accounting?.issued_total || 0).toFixed(2) }}</div>
                            </div>
                            <div class="rounded-[22px] p-4" style="background: var(--app-surface-soft);">
                                <div class="text-[11px] font-bold uppercase tracking-[0.16em] app-muted">Paid value</div>
                                <div class="mt-2 text-2xl font-black app-title">N$ {{ Number(driverProfile.accounting?.paid_total || 0).toFixed(2) }}</div>
                            </div>
                            <div class="rounded-[22px] p-4" style="background: var(--app-surface-soft);">
                                <div class="text-[11px] font-bold uppercase tracking-[0.16em] app-muted">Pending value</div>
                                <div class="mt-2 text-2xl font-black app-title">N$ {{ Number(driverProfile.accounting?.pending_total || 0).toFixed(2) }}</div>
                            </div>
                        </div>
                    </article>

                    <article class="app-panel rounded-[30px] p-6">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <div class="text-[11px] font-bold uppercase tracking-[0.18em] app-muted">Latest invoices</div>
                                <h3 class="mt-1 text-2xl font-black app-title">Commercial record history</h3>
                            </div>
                        </div>

                        <div v-if="driverProfile.accounting?.latest_invoices?.length" class="mt-5 space-y-4">
                            <div v-for="invoice in driverProfile.accounting.latest_invoices" :key="invoice.id" class="rounded-[24px] border p-4" style="border-color: var(--app-border); background: var(--app-surface-soft);">
                                <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                                    <div>
                                        <div class="text-lg font-black app-title">{{ invoice.invoice_number }}</div>
                                        <div class="mt-1 text-sm app-muted">{{ invoice.route || 'Route pending' }}</div>
                                        <div class="mt-1 text-sm app-muted">Tracking {{ invoice.tracking_number || 'Pending' }}</div>
                                    </div>
                                    <div class="flex flex-wrap gap-2">
                                        <StatusBadge :label="String(invoice.status || 'issued').replaceAll('_', ' ')" :tone="invoice.status === 'cancelled' ? 'danger' : 'brand'" />
                                        <StatusBadge :label="String(invoice.payment_status || 'pending').replaceAll('_', ' ')" :tone="invoice.payment_status === 'paid' ? 'success' : invoice.payment_status === 'failed' ? 'danger' : 'warning'" />
                                    </div>
                                </div>
                                <div class="mt-4 grid gap-3 md:grid-cols-3">
                                    <div class="rounded-[18px] border px-4 py-3" style="border-color: rgba(31,31,31,0.06); background: rgba(255,255,255,0.72);">
                                        <div class="text-[10px] font-bold uppercase tracking-[0.16em] app-muted">Issue date</div>
                                        <div class="mt-1 text-sm font-black app-title">{{ invoice.issue_date || 'Pending' }}</div>
                                    </div>
                                    <div class="rounded-[18px] border px-4 py-3" style="border-color: rgba(31,31,31,0.06); background: rgba(255,255,255,0.72);">
                                        <div class="text-[10px] font-bold uppercase tracking-[0.16em] app-muted">Due date</div>
                                        <div class="mt-1 text-sm font-black app-title">{{ invoice.due_date || 'Pending' }}</div>
                                    </div>
                                    <div class="rounded-[18px] border px-4 py-3" style="border-color: rgba(31,31,31,0.06); background: rgba(255,255,255,0.72);">
                                        <div class="text-[10px] font-bold uppercase tracking-[0.16em] app-muted">Total</div>
                                        <div class="mt-1 text-sm font-black app-title">N$ {{ Number(invoice.total || 0).toFixed(2) }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div v-else class="mt-5 rounded-[24px] p-5 text-sm app-muted" style="background: var(--app-surface-soft);">
                            Invoice records will appear here once you start completing assigned jobs that generate billing documents.
                        </div>
                    </article>
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
