<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from "vue";
import { Link, useForm, usePage } from "@inertiajs/vue3";
import {
    ArrowRight,
    CircleDollarSign,
    Clock3,
    MapPinned,
    PackageCheck,
    Phone,
    Route,
    ShieldCheck,
    Truck,
    UserRound,
    Weight,
    Zap,
} from "lucide-vue-next";
import api from "@/lib/api";
import { errorToast, successToast } from "@/composables/useAppToast";

const props = defineProps({
    locations: { type: Array, default: () => [] },
    packageTypes: { type: Array, default: () => [] },
    cityRoutes: { type: Array, default: () => [] },
    driverReadyCount: { type: Number, default: 0 },
});

const page = usePage();
const user = computed(() => page.props.auth?.user ?? null);
const isAuthenticated = computed(() => !!user.value);
const step = ref(1);
const previewBusy = ref(false);
const previewError = ref("");
const previewDrivers = ref([]);
const previewQuote = ref(null);
let previewTimer = null;
let heroWordTimer = null;
let heroTypeTimer = null;
const rotatingWords = ["less waiting", "more clarity", "better matches", "faster booking"];
const heroWordIndex = ref(0);
const heroTypedWord = ref(rotatingWords[0]);

const urgencyOptions = [
    { value: "standard", label: "Standard", icon: ShieldCheck, helper: "Balanced pricing" },
    { value: "express", label: "Express", icon: Zap, helper: "Faster dispatch" },
    { value: "same_day", label: "Same day", icon: Clock3, helper: "Highest priority" },
];

const loadSizeOptions = [
    { value: "small", label: "Small" },
    { value: "medium", label: "Medium" },
    { value: "large", label: "Large" },
    { value: "heavy", label: "Heavy" },
    { value: "oversized", label: "Oversized" },
];

const form = useForm({
    pickup_location_id: props.locations[0]?.id || "",
    dropoff_location_id: props.locations[1]?.id || "",
    package_type_id: props.packageTypes[0]?.id || "",
    pickup_address: "",
    dropoff_address: "",
    receiver_name: "",
    receiver_phone: "",
    weight_kg: "",
    load_size: "small",
    urgency_level: "standard",
    client_offer_price: "",
    declared_value: "",
    notes: "",
});

const selectedRoute = computed(() =>
    (props.cityRoutes || []).find(
        (routeItem) =>
            Number(routeItem.origin_location_id) === Number(form.pickup_location_id) &&
            Number(routeItem.destination_location_id) === Number(form.dropoff_location_id)
    )
);

const selectedPickup = computed(() =>
    (props.locations || []).find((location) => Number(location.id) === Number(form.pickup_location_id))
);

const selectedDropoff = computed(() =>
    (props.locations || []).find((location) => Number(location.id) === Number(form.dropoff_location_id))
);

const localPricingPreview = computed(() => {
    const distance = Number(selectedRoute.value?.distance_km || 320);
    const basePrice = Number(selectedRoute.value?.base_fare || distance * 2.35);
    const weight = Math.max(Number(form.weight_kg || 0), 0);
    const weightSurcharge =
        weight <= 5 ? 0 :
        weight <= 15 ? 55 :
        weight <= 30 ? 120 :
        weight <= 60 ? 240 :
        240 + (weight - 60) * 4.8;

    const urgencySurcharge =
        form.urgency_level === "same_day"
            ? (basePrice + weightSurcharge) * 0.5
            : form.urgency_level === "express"
              ? (basePrice + weightSurcharge) * 0.25
              : 0;

    const totalPrice = basePrice + weightSurcharge + urgencySurcharge;

    return {
        distance_km: distance,
        estimated_hours: Number(selectedRoute.value?.estimated_hours || Math.max(4, (distance / 70).toFixed(1))),
        weight_surcharge: weightSurcharge,
        urgency_surcharge: urgencySurcharge,
        total_price: totalPrice,
    };
});

const pricingPreview = computed(() => previewQuote.value || localPricingPreview.value);

function createDriverInitials(name) {
    return (name || "Route Driver")
        .split(" ")
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part.charAt(0))
        .join("")
        .toUpperCase();
}

const normalizedPreviewDrivers = computed(() =>
    (previewDrivers.value || []).map((driver, index) => ({
        ...driver,
        image: driver.image || null,
        initials: createDriverInitials(driver.name),
        matchBadge: driver.match_label || (index === 0 ? "Best Match" : index < 3 ? "Strong Match" : "Good Match"),
        badges: (driver.badges || []).slice(0, 3),
    }))
);

const steps = [
    { id: 1, label: "Route", icon: MapPinned, detail: "Pickup and destination" },
    { id: 2, label: "Load", icon: PackageCheck, detail: "Parcel, weight, urgency" },
    { id: 3, label: "Receiver", icon: UserRound, detail: "Contact and handoff notes" },
    { id: 4, label: "Matches", icon: ShieldCheck, detail: "Estimate and ranked drivers" },
];

const activeStep = computed(() => steps.find((item) => item.id === step.value) || steps[0]);
const activeHeroWord = computed(() => rotatingWords[heroWordIndex.value] || rotatingWords[0]);
const canMoveNext = computed(() => {
    if (step.value === 1) {
        return !!form.pickup_location_id && !!form.dropoff_location_id && form.pickup_location_id !== form.dropoff_location_id;
    }
    if (step.value === 2) {
        return !!form.package_type_id && !!form.load_size;
    }
    if (step.value === 3) {
        return !!form.receiver_name && !!form.receiver_phone;
    }
    return true;
});

const canPreview = computed(() =>
    !!form.pickup_location_id &&
    !!form.dropoff_location_id &&
    !!form.package_type_id &&
    !!form.load_size &&
    form.pickup_location_id !== form.dropoff_location_id
);

function goNext() {
    if (canMoveNext.value && step.value < 4) step.value += 1;
}

function goBack() {
    if (step.value > 1) step.value -= 1;
}

async function fetchPreview() {
    if (!canPreview.value) {
        previewDrivers.value = [];
        previewQuote.value = null;
        previewError.value = "";
        return;
    }

    previewBusy.value = true;
    previewError.value = "";

    try {
        const { data } = await api.get(route("parcel-requests.preview"), {
            params: {
                pickup_location_id: form.pickup_location_id,
                dropoff_location_id: form.dropoff_location_id,
                package_type_id: form.package_type_id,
                weight_kg: form.weight_kg || null,
                load_size: form.load_size,
                urgency_level: form.urgency_level,
            },
        });

        previewQuote.value = data.quote || null;
        previewDrivers.value = data.drivers || [];
    } catch (error) {
        previewQuote.value = null;
        previewDrivers.value = [];
        previewError.value = error.response?.data?.message || "Could not load top matches right now.";
    } finally {
        previewBusy.value = false;
    }
}

watch(
    () => [form.pickup_location_id, form.dropoff_location_id, form.package_type_id, form.weight_kg, form.load_size, form.urgency_level, step.value],
    () => {
        window.clearTimeout(previewTimer);
        if (step.value !== 4) return;
        previewTimer = window.setTimeout(fetchPreview, 250);
    }
);

function submitWizard() {
    if (!isAuthenticated.value) {
        errorToast("Sign in to send this parcel request.", "Login required");
        return;
    }

    form.post(route("parcel-requests.store"), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            successToast("Parcel request created and shared with matching drivers.", "Request sent");
            form.reset("pickup_address", "dropoff_address", "receiver_name", "receiver_phone", "weight_kg", "client_offer_price", "declared_value", "notes");
            form.pickup_location_id = props.locations[0]?.id || "";
            form.dropoff_location_id = props.locations[1]?.id || "";
            form.package_type_id = props.packageTypes[0]?.id || "";
            form.load_size = "small";
            form.urgency_level = "standard";
            previewDrivers.value = [];
            previewQuote.value = null;
            previewError.value = "";
            step.value = 1;
        },
        onError: () => {
            errorToast("Please check the request details and try again.", "Could not submit");
        },
    });
}

function typeHeroWord(nextWord) {
    window.clearInterval(heroTypeTimer);
    heroTypedWord.value = "";

    let letterIndex = 0;
    heroTypeTimer = window.setInterval(() => {
        letterIndex += 1;
        heroTypedWord.value = nextWord.slice(0, letterIndex);

        if (letterIndex >= nextWord.length) {
            window.clearInterval(heroTypeTimer);
        }
    }, 55);
}

onMounted(() => {
    typeHeroWord(activeHeroWord.value);

    heroWordTimer = window.setInterval(() => {
        heroWordIndex.value = (heroWordIndex.value + 1) % rotatingWords.length;
        typeHeroWord(activeHeroWord.value);
    }, 2800);
});

onBeforeUnmount(() => {
    window.clearTimeout(previewTimer);
    window.clearInterval(heroWordTimer);
    window.clearInterval(heroTypeTimer);
});
</script>

<template>
    <section class="relative overflow-hidden px-4 pb-16 pt-14 sm:px-6 sm:pb-20 sm:pt-16 lg:px-10 lg:pb-24 lg:pt-20 2xl:px-12">
        <div class="absolute inset-x-0 top-0 -z-10 h-[560px]" style="background: radial-gradient(circle at 12% 10%, rgba(47,46,124,.07), transparent 34%), radial-gradient(circle at 84% 10%, rgba(242,201,0,.10), transparent 24%);" />
        <div class="relative grid gap-8 xl:grid-cols-[0.95fr_1.05fr] xl:items-center">
            <div class="max-w-3xl space-y-6 py-6 xl:py-12">
                <div class="app-fade-up inline-flex items-center rounded-full border px-4 py-2 text-[11px] font-bold uppercase tracking-[0.24em]" style="border-color: var(--app-border); background: var(--app-surface); color: #2F2E7C;">
                    Intercity delivery marketplace
                </div>

                <div class="app-fade-up app-delay-1 space-y-4">
                    <h1 class="max-w-4xl text-4xl font-black leading-[0.95] tracking-tight app-title sm:text-5xl lg:text-6xl xl:text-[4.45rem]">
                        Send parcels across Namibia with
                        <span class="app-type-hero">
                            {{ heroTypedWord }}
                            <span class="app-type-caret" />
                        </span>
                    </h1>
                    <p class="max-w-2xl text-lg leading-8 app-muted sm:text-xl">
                        Choose your route, see the estimate, and connect with available drivers fast.
                    </p>
                </div>

                <div class="app-fade-up app-delay-2 flex flex-wrap gap-3">
                    <Link :href="route('parcel-requests.create')" class="app-primary-btn">
                        Send Parcel
                    </Link>
                    <Link :href="route('driver.register')" class="app-outline-btn">
                        Become Driver
                    </Link>
                </div>

                <div class="app-fade-up app-delay-3 grid gap-3 sm:grid-cols-3">
                    <div class="app-lite-card app-float-soft">
                        <div class="app-kicker">Instant estimate</div>
                        <div class="mt-2 text-sm app-muted">Price, time, and route update live.</div>
                    </div>
                    <div class="app-lite-card app-float-soft app-delay-2">
                        <div class="app-kicker">Top matches</div>
                        <div class="mt-2 text-sm app-muted">Best drivers rank by fit and availability.</div>
                    </div>
                    <div class="app-lite-card app-float-soft app-delay-4">
                        <div class="app-kicker">Live delivery</div>
                        <div class="mt-2 text-sm app-muted">Track each move from pickup to handover.</div>
                    </div>
                </div>
            </div>

            <div class="app-hero-panel app-fade-up app-delay-2 p-4 sm:p-5 lg:p-6">
                <div class="rounded-[28px] border p-5 sm:p-6" style="border-color: rgba(47, 46, 124, 0.1); background: var(--app-surface-soft);">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="app-kicker">Quick request</p>
                            <h2 class="mt-2 inline-flex items-center gap-3 text-3xl font-black app-title">
                                <span class="app-pulse-dot" aria-hidden="true" />
                                Request a delivery
                            </h2>
                        </div>
                        <div class="flex flex-col items-end gap-3">
                            <span class="rounded-full px-3 py-1 text-[11px] font-bold uppercase tracking-[0.18em]" style="background: #F2C900; color: #1F1F1F;">
                                Step {{ step }}/4
                            </span>
                            <div class="inline-flex items-center gap-2 rounded-full border px-3 py-2 text-[11px] font-bold uppercase tracking-[0.16em]" style="border-color: var(--app-border); background: rgba(255,255,255,0.72); color: var(--app-text);">
                                <component :is="activeStep.icon" class="h-4 w-4" />
                                {{ activeStep.detail }}
                            </div>
                        </div>
                    </div>

                    <div class="mt-5 grid grid-cols-4 gap-2">
                        <div
                            v-for="item in steps"
                            :key="item.id"
                            class="rounded-[20px] border px-3 py-3 text-center"
                            :style="item.id === step ? 'border-color:#2F2E7C;background:#2F2E7C;color:#FFFFFF;box-shadow:0 16px 28px rgba(47,46,124,0.18);' : item.id < step ? 'border-color:rgba(47,46,124,0.22);background:rgba(47,46,124,0.08);color:#2F2E7C;' : 'background:var(--app-surface);color:var(--app-text);border-color:var(--app-border);'"
                        >
                            <div class="flex justify-center">
                                <div class="flex h-9 w-9 items-center justify-center rounded-2xl" :style="item.id === step ? 'background:rgba(255,255,255,0.14);' : item.id < step ? 'background:rgba(255,255,255,0.72);' : 'background:var(--app-surface-soft);'">
                                    <component :is="item.icon" class="h-4 w-4" />
                                </div>
                            </div>
                            <div class="mt-2 text-[11px] font-bold uppercase tracking-[0.16em]">{{ item.label }}</div>
                        </div>
                    </div>

                    <div v-if="step === 1" class="mt-6 grid gap-4">
                        <label class="space-y-2">
                            <span class="app-label app-field-label"><MapPinned class="h-4 w-4" />Pickup</span>
                            <select v-model="form.pickup_location_id" class="app-field">
                                <option value="">Select pickup city</option>
                                <option v-for="location in locations" :key="location.id" :value="location.id">{{ location.name }}</option>
                            </select>
                        </label>
                        <label class="space-y-2">
                            <span class="app-label app-field-label"><Route class="h-4 w-4" />Destination</span>
                            <select v-model="form.dropoff_location_id" class="app-field">
                                <option value="">Select destination city</option>
                                <option v-for="location in locations" :key="`to-${location.id}`" :value="location.id">{{ location.name }}</option>
                            </select>
                        </label>
                        <div class="app-summary-card !rounded-[24px]">
                            <div class="app-kicker inline-flex items-center gap-2"><Truck class="h-4 w-4" />Route</div>
                            <div class="mt-2 text-base font-black app-title">{{ selectedPickup?.name || "Pickup" }} -> {{ selectedDropoff?.name || "Destination" }}</div>
                            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                <div class="rounded-2xl px-4 py-3" style="background: var(--app-surface-soft);">
                                    <div class="inline-flex items-center gap-2 text-[11px] font-bold uppercase tracking-[0.16em] app-muted"><MapPinned class="h-4 w-4" />Distance</div>
                                    <div class="mt-2 text-sm font-black app-title">{{ pricingPreview.distance_km }} km</div>
                                </div>
                                <div class="rounded-2xl px-4 py-3" style="background: var(--app-surface-soft);">
                                    <div class="inline-flex items-center gap-2 text-[11px] font-bold uppercase tracking-[0.16em] app-muted"><Clock3 class="h-4 w-4" />Travel time</div>
                                    <div class="mt-2 text-sm font-black app-title">{{ Number(pricingPreview.estimated_hours || 0).toFixed(1) }} hrs</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-else-if="step === 2" class="mt-6 grid gap-4">
                        <label class="space-y-2">
                            <span class="app-label app-field-label"><PackageCheck class="h-4 w-4" />Parcel type</span>
                            <select v-model="form.package_type_id" class="app-field">
                                <option value="">Select parcel type</option>
                                <option v-for="pkg in packageTypes" :key="pkg.id" :value="pkg.id">{{ pkg.name }}</option>
                            </select>
                        </label>
                        <div class="grid gap-4 md:grid-cols-2">
                            <label class="space-y-2">
                                <span class="app-label app-field-label"><Weight class="h-4 w-4" />Weight (kg)</span>
                                <input v-model="form.weight_kg" type="number" step="0.1" min="0" class="app-field" placeholder="Optional" />
                            </label>
                            <label class="space-y-2">
                                <span class="app-label app-field-label"><Truck class="h-4 w-4" />Load size</span>
                                <select v-model="form.load_size" class="app-field">
                                    <option v-for="option in loadSizeOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                                </select>
                            </label>
                        </div>
                        <div class="space-y-2">
                            <span class="app-label app-field-label"><Zap class="h-4 w-4" />Urgency</span>
                            <div class="grid gap-3 sm:grid-cols-3">
                                <button
                                    v-for="option in urgencyOptions"
                                    :key="option.value"
                                    type="button"
                                    class="rounded-[22px] border px-4 py-4 text-left transition duration-200"
                                    :style="form.urgency_level === option.value ? 'border-color:#2F2E7C;background:#2F2E7C;color:#FFFFFF;box-shadow:0 16px 28px rgba(47,46,124,0.18);' : 'border-color:var(--app-border);background:var(--app-surface);color:var(--app-text);'"
                                    @click="form.urgency_level = option.value"
                                >
                                    <div class="inline-flex h-10 w-10 items-center justify-center rounded-2xl" :style="form.urgency_level === option.value ? 'background:rgba(255,255,255,0.14);' : 'background:var(--app-surface-soft);'">
                                        <component :is="option.icon" class="h-4 w-4" />
                                    </div>
                                    <div class="mt-3 text-sm font-black">{{ option.label }}</div>
                                    <div class="mt-1 text-xs uppercase tracking-[0.16em]" :style="form.urgency_level === option.value ? 'color:rgba(255,255,255,0.72);' : 'color:var(--app-text-muted);'">
                                        {{ option.helper }}
                                    </div>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div v-else-if="step === 3" class="mt-6 grid gap-4">
                        <label class="space-y-2">
                            <span class="app-label app-field-label"><UserRound class="h-4 w-4" />Receiver name</span>
                            <input v-model="form.receiver_name" type="text" class="app-field" />
                        </label>
                        <label class="space-y-2">
                            <span class="app-label app-field-label"><Phone class="h-4 w-4" />Receiver phone</span>
                            <input v-model="form.receiver_phone" type="text" class="app-field" />
                        </label>
                        <label class="space-y-2">
                            <span class="app-label app-field-label"><MapPinned class="h-4 w-4" />Pickup note</span>
                            <input v-model="form.pickup_address" type="text" class="app-field" placeholder="Optional" />
                        </label>
                        <label class="space-y-2">
                            <span class="app-label app-field-label"><Route class="h-4 w-4" />Dropoff note</span>
                            <input v-model="form.dropoff_address" type="text" class="app-field" placeholder="Optional" />
                        </label>
                    </div>

                    <div v-else class="mt-6 space-y-4">
                        <div class="grid gap-3 md:grid-cols-[0.86fr_1.14fr]">
                            <div class="app-summary-card !rounded-[24px]">
                                <div class="app-kicker inline-flex items-center gap-2"><CircleDollarSign class="h-4 w-4" />Estimate</div>
                                <div class="mt-2 text-3xl font-black app-title">N$ {{ Number(pricingPreview.total_price || 0).toFixed(2) }}</div>
                                <div class="mt-2 text-sm app-muted">
                                    {{ selectedPickup?.name || "Pickup" }} -> {{ selectedDropoff?.name || "Destination" }}
                                </div>
                                <div class="mt-4 grid gap-2 text-sm app-muted">
                                    <div class="flex items-center justify-between"><span class="inline-flex items-center gap-2"><MapPinned class="h-4 w-4" />Distance</span><strong class="app-title">{{ pricingPreview.distance_km }} km</strong></div>
                                    <div class="flex items-center justify-between"><span class="inline-flex items-center gap-2"><Clock3 class="h-4 w-4" />Travel time</span><strong class="app-title">{{ Number(pricingPreview.estimated_hours || 0).toFixed(1) }} hrs</strong></div>
                                    <div class="flex items-center justify-between"><span class="inline-flex items-center gap-2"><Weight class="h-4 w-4" />Weight charge</span><strong class="app-title">N$ {{ Number(pricingPreview.weight_surcharge || 0).toFixed(2) }}</strong></div>
                                    <div class="flex items-center justify-between"><span class="inline-flex items-center gap-2"><Zap class="h-4 w-4" />Urgency charge</span><strong class="app-title">N$ {{ Number(pricingPreview.urgency_surcharge || 0).toFixed(2) }}</strong></div>
                                </div>
                            </div>

                            <div class="app-summary-card !rounded-[24px]">
                                <div class="flex items-center justify-between gap-3">
                                    <div>
                                        <div class="app-kicker inline-flex items-center gap-2"><ShieldCheck class="h-4 w-4" />Top drivers</div>
                                        <div class="mt-1 text-sm app-muted">Best route matches right now.</div>
                                    </div>
                                    <span v-if="previewBusy" class="rounded-full px-3 py-1 text-[11px] font-bold uppercase tracking-[0.16em]" style="background: var(--app-surface-soft); color: var(--app-text); border:1px solid var(--app-border);">Loading</span>
                                </div>

                                <div v-if="previewError" class="mt-4 rounded-2xl border px-4 py-3 text-sm" style="border-color: rgba(220, 38, 38, 0.18); background: rgba(220, 38, 38, 0.06); color: #b91c1c;">
                                    {{ previewError }}
                                </div>

                                <div v-else class="mt-4 space-y-3">
                                    <div
                                        v-for="(driver, index) in normalizedPreviewDrivers"
                                        :key="driver.id"
                                        class="rounded-[24px] border px-4 py-4"
                                        :style="index === 0 ? 'border-color:rgba(47,46,124,0.22); background:rgba(255,255,255,0.98); box-shadow:0 14px 28px rgba(31,31,31,0.06);' : 'border-color:var(--app-border); background:var(--app-surface);'"
                                    >
                                        <div class="flex items-start justify-between gap-3">
                                            <div class="flex items-center gap-3">
                                                <img
                                                    v-if="driver.image"
                                                    :src="driver.image"
                                                    :alt="driver.name"
                                                    class="h-16 w-16 rounded-[20px] object-cover border"
                                                    style="border-color:rgba(47,46,124,0.12); background:var(--app-surface-soft);"
                                                />
                                                <div
                                                    v-else
                                                    class="flex h-16 w-16 items-center justify-center rounded-[20px] border text-sm font-black"
                                                    style="border-color:rgba(47,46,124,0.12); background:var(--app-surface-soft); color:#2F2E7C;"
                                                >
                                                    {{ driver.initials }}
                                                </div>

                                                <div>
                                                    <div class="flex flex-wrap items-center gap-2">
                                                        <span
                                                            class="rounded-full px-2.5 py-1 text-[10px] font-bold uppercase tracking-[0.16em]"
                                                            :style="driver.available_now ? 'background:#E7F7EE;color:#156B45;' : 'background:var(--app-surface-soft);color:var(--app-text);border:1px solid var(--app-border);'"
                                                        >
                                                            {{ driver.available_now ? "Available" : "Busy" }}
                                                        </span>
                                                        <span
                                                            class="rounded-full px-2.5 py-1 text-[10px] font-bold uppercase tracking-[0.16em]"
                                                            :style="index === 0 ? 'background:rgba(242,201,0,0.18);color:#7A6200;' : 'background:rgba(47,46,124,0.08);color:#2F2E7C;'"
                                                        >
                                                            {{ driver.matchBadge }}
                                                        </span>
                                                    </div>
                                                    <div class="mt-2 text-base font-black app-title">{{ driver.name }}</div>
                                                    <div class="mt-1 inline-flex items-center gap-2 text-sm app-muted"><Truck class="h-4 w-4" />{{ driver.vehicle }}</div>
                                                </div>
                                            </div>
                                            <div class="rounded-[18px] border px-3 py-2 text-right" style="border-color:rgba(47,46,124,0.10); background:var(--app-surface-soft);">
                                                <div class="text-[10px] font-bold uppercase tracking-[0.16em] app-muted">Match</div>
                                                <div class="text-lg font-black app-title">{{ driver.match_score }}%</div>
                                            </div>
                                        </div>

                                        <div class="mt-4 rounded-[20px] border px-3 py-3" style="border-color:rgba(47,46,124,0.10); background:var(--app-surface-soft);">
                                            <div class="inline-flex items-center gap-2 text-[10px] font-bold uppercase tracking-[0.16em]" style="color:#2F2E7C;">
                                                <Route class="h-4 w-4" />
                                                Route
                                            </div>
                                            <div class="mt-2 text-sm font-black app-title">{{ driver.route_summary }}</div>
                                        </div>

                                        <div class="mt-3 flex flex-wrap gap-2">
                                            <span
                                                v-for="badge in driver.badges"
                                                :key="badge"
                                                class="rounded-full px-3 py-1 text-[11px] font-bold uppercase tracking-[0.14em]"
                                                style="background: var(--app-surface-soft); color: var(--app-text); border:1px solid var(--app-border);"
                                            >
                                                {{ badge }}
                                            </span>
                                        </div>
                                    </div>

                                    <div v-if="!previewBusy && !normalizedPreviewDrivers.length" class="rounded-[22px] border px-4 py-4 text-sm app-muted" style="border-color: var(--app-border); background: var(--app-surface);">
                                        No ranked drivers yet for this exact route. You can still send the request and notify available drivers nearby.
                                    </div>
                                </div>
                            </div>
                        </div>

                        <label class="space-y-2">
                            <span class="app-label app-field-label"><CircleDollarSign class="h-4 w-4" />Your offer</span>
                            <input v-model="form.client_offer_price" type="number" step="0.01" min="1" class="app-field" placeholder="Optional offer amount" />
                        </label>

                        <label class="space-y-2">
                            <span class="app-label app-field-label"><PackageCheck class="h-4 w-4" />Handling notes</span>
                            <textarea v-model="form.notes" rows="3" class="app-field !h-auto !py-3" placeholder="Fragile cargo, access notes, special handling..." />
                        </label>
                    </div>

                    <div class="mt-5 flex flex-col gap-3 border-t pt-5 sm:flex-row sm:items-center sm:justify-between" style="border-color: var(--app-border);">
                        <button v-if="step > 1" type="button" class="app-outline-btn" @click="goBack">
                            Back
                        </button>
                        <div v-else class="hidden sm:block" />

                        <div class="flex flex-1 flex-col gap-3 sm:flex-row sm:justify-end">
                            <button v-if="step < 4" type="button" class="app-primary-btn sm:min-w-[11rem]" :disabled="!canMoveNext" @click="goNext">
                                Continue
                                <ArrowRight class="h-4 w-4" />
                            </button>
                            <button
                                v-else
                                type="button"
                                class="app-primary-btn sm:min-w-[12rem]"
                                :disabled="form.processing"
                                @click="submitWizard"
                            >
                                {{ form.processing ? "Submitting..." : isAuthenticated ? "Send request" : "Sign in to send" }}
                                <ArrowRight class="h-4 w-4" />
                            </button>
                            <Link v-if="!isAuthenticated && step === 4" :href="route('login')" class="app-outline-btn sm:min-w-[12rem]">
                                Sign in
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
