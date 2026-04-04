<script setup>
import { computed, ref } from "vue";
import { Head, Link } from "@inertiajs/vue3";
import { CircleDollarSign, Clock3, LifeBuoy, MapPinned, ShieldCheck, Truck } from "lucide-vue-next";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import BookingStepper from "@/Components/Booking/BookingStepper.vue";
import PaymentStatusCard from "@/Components/Booking/PaymentStatusCard.vue";
import PriceBreakdown from "@/Components/Booking/PriceBreakdown.vue";
import StickyBookingSummary from "@/Components/Booking/StickyBookingSummary.vue";
import SuccessStateCard from "@/Components/Booking/SuccessStateCard.vue";
import StatusBadge from "@/Components/AppShell/StatusBadge.vue";
import api from "@/lib/api";
import { errorToast, successToast } from "@/composables/useAppToast";

const props = defineProps({
    parcel: { type: Object, required: true },
});
const parcel = ref({ ...(props.parcel || {}) });

const bookingSteps = [
    { id: 1, label: "Find Driver", icon: Truck },
    { id: 2, label: "Confirm", icon: ShieldCheck },
    { id: 3, label: "Payment", icon: CircleDollarSign },
    { id: 4, label: "Track", icon: MapPinned },
];

const totalLabel = computed(() => `N$ ${Number(parcel.value.client_offer_price || parcel.value.final_price || parcel.value.estimated_price || parcel.value.total_price || 0).toFixed(2)}`);
const leadDriver = computed(() => parcel.value.preferred_driver || parcel.value.assigned_driver || null);
const verifiedDriver = computed(() => leadDriver.value?.verification_status === "verified");

const summaryItems = computed(() => [
    {
        label: "Route",
        value: `${parcel.value.pickup_location?.name || "Pickup"} -> ${parcel.value.dropoff_location?.name || "Destination"}`,
        meta: parcel.value.package_type?.name || "Parcel",
    },
    {
        label: "Driver",
        value: leadDriver.value?.name || "Matched driver",
        meta: leadDriver.value?.phone || "Contact follows when driver confirms",
    },
    {
        label: "Booking",
        value: parcel.value.booking_reference || parcel.value.tracking_number,
        meta: parcel.value.booking_status_label || "Booking confirmed",
    },
]);

const breakdownRows = computed(() => [
    { label: "Base fare", value: `N$ ${Number(parcel.value.base_price || 0).toFixed(2)}`, detail: "Booking setup and lane activation" },
    { label: "Distance fee", value: `N$ ${Number(parcel.value.distance_fee || 0).toFixed(2)}`, detail: `${Number(parcel.value.distance_km || 0).toFixed(0)} km route charge` },
    { label: "Weight fee", value: `N$ ${Number(parcel.value.weight_surcharge || 0).toFixed(2)}`, detail: `${Number(parcel.value.weight_kg || 0).toFixed(1)} kg shipment weight` },
    { label: "Urgency fee", value: `N$ ${Number(parcel.value.urgency_surcharge || 0).toFixed(2)}`, detail: String(parcel.value.urgency_level || "standard").replaceAll("_", " ") },
    { label: "Handling fee", value: `N$ ${Number(parcel.value.special_handling_fee || 0).toFixed(2)}`, detail: "Special access, fragility, or operational notes" },
    {
        label: "Payment mode",
        value: parcel.value.payment_status?.replaceAll("_", " ") || "ready",
        highlight: true,
        detail: "Status carried into invoice and future payment integrations",
    },
]);

const trustBadges = computed(() => [
    "Booking Confirmed",
    verifiedDriver.value ? "Verified Driver" : "Driver Assigned",
    "Transparent Pricing",
    "Tracking Included",
]);
const paymentMethodLabels = computed(() =>
    (parcel.value.payment_methods || []).map((method) =>
        method
            .replaceAll("_", " ")
            .replace(/\b\w/g, (char) => char.toUpperCase())
    )
);

const quoteProcessing = ref(false);

async function acceptQuote() {
    if (!parcel.value.quotation?.id || quoteProcessing.value) return;

    quoteProcessing.value = true;
    try {
        const { data } = await api.post(route("quotations.accept", parcel.value.quotation.id), {});
        parcel.value.quotation = data.quotation || parcel.value.quotation;
        parcel.value.invoice = data.invoice || parcel.value.invoice;
        successToast("Quotation accepted and invoice generated.", "Quote accepted");
    } catch (error) {
        errorToast(error.response?.data?.message || "Could not accept quotation.", "Action failed");
    } finally {
        quoteProcessing.value = false;
    }
}
</script>

<template>
    <Head title="Booking Confirmed" />

    <AuthenticatedLayout>
        <div class="space-y-6">
            <BookingStepper :steps="bookingSteps" :current="3" compact class="lg:hidden" />
            <BookingStepper :steps="bookingSteps" :current="3" class="hidden lg:block" />

            <SuccessStateCard
                eyebrow="Booking confirmed"
                title="Driver assigned and request submitted"
                description="Your shipment is now in a payment-ready state. The booking is stored, tracking is active, and the next steps are ready."
                :booking-reference="parcel.value.booking_reference || parcel.value.tracking_number"
                :tracking-number="parcel.value.tracking_number"
                :driver-name="parcel.value.preferred_driver?.name || parcel.value.assigned_driver?.name || 'Matched driver'"
                :route-label="`${parcel.value.pickup_location?.name || 'Pickup'} -> ${parcel.value.dropoff_location?.name || 'Destination'}`"
                :eta-label="`${Number(parcel.value.estimated_hours || 0).toFixed(1)} hrs`"
            />

            <section class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
                <div class="space-y-6">
                    <div class="app-panel rounded-[30px] p-6">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <div class="text-[11px] font-bold uppercase tracking-[0.18em]" style="color:#2F2E7C;">Payment-ready state</div>
                                <h2 class="mt-2 text-2xl font-black app-title">Everything is prepared for checkout and tracking</h2>
                            </div>
                            <StatusBadge :label="parcel.value.booking_status_label || 'Booking Confirmed'" tone="brand" />
                        </div>

                        <div class="mt-5 grid gap-3 md:grid-cols-3">
                            <div class="rounded-[22px] p-4" style="background: var(--app-surface-soft);">
                                <div class="inline-flex items-center gap-2 text-[11px] font-bold uppercase tracking-[0.16em] app-muted">
                                    <CircleDollarSign class="h-4 w-4" />
                                    Estimated total
                                </div>
                                <div class="mt-2 text-xl font-black app-title">{{ totalLabel }}</div>
                            </div>
                            <div class="rounded-[22px] p-4" style="background: var(--app-surface-soft);">
                                <div class="inline-flex items-center gap-2 text-[11px] font-bold uppercase tracking-[0.16em] app-muted">
                                    <Clock3 class="h-4 w-4" />
                                    Delivery timeline
                                </div>
                                <div class="mt-2 text-xl font-black app-title">{{ Number(parcel.estimated_hours || 0).toFixed(1) }} hrs</div>
                            </div>
                            <div class="rounded-[22px] p-4" style="background: var(--app-surface-soft);">
                                <div class="inline-flex items-center gap-2 text-[11px] font-bold uppercase tracking-[0.16em] app-muted">
                                    <ShieldCheck class="h-4 w-4" />
                                    Driver trust
                                </div>
                                <div class="mt-2 text-xl font-black app-title">{{ verifiedDriver ? "Verified" : "Assigned" }}</div>
                                <div class="mt-1 text-sm app-muted">{{ verifiedDriver ? "Driver verification checks completed." : "Driver assigned and awaiting verification cue." }}</div>
                            </div>
                        </div>
                    </div>

                    <div class="app-panel rounded-[30px] p-6">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <div class="text-[11px] font-bold uppercase tracking-[0.18em]" style="color:#2F2E7C;">Commercial documents</div>
                                <h2 class="mt-2 text-2xl font-black app-title">Quote and invoice access</h2>
                            </div>
                            <StatusBadge :label="verifiedDriver ? 'Trusted driver on booking' : 'Documents ready'" :tone="verifiedDriver ? 'success' : 'brand'" />
                        </div>

                        <div class="mt-5 grid gap-4 md:grid-cols-2">
                            <div class="rounded-[24px] border p-5" style="border-color: var(--app-border); background: var(--app-surface-soft);">
                                <div class="flex items-center justify-between gap-3">
                                    <div>
                                        <div class="text-[11px] font-bold uppercase tracking-[0.16em] app-muted">Quotation</div>
                                        <div class="mt-2 text-lg font-black app-title">{{ parcel.value.quotation?.quotation_number || "Not available" }}</div>
                                    </div>
                                    <StatusBadge :label="String(parcel.value.quotation?.status || 'not issued').replaceAll('_', ' ')" :tone="parcel.value.quotation?.status === 'converted' ? 'success' : parcel.value.quotation?.id ? 'brand' : 'neutral'" small />
                                </div>
                                <p class="mt-3 text-sm leading-6 app-muted">Route, parcel, and pricing snapshot captured before invoice creation.</p>
                                <a v-if="parcel.value.quotation?.id" :href="route('quotations.download', parcel.value.quotation.id)" class="mt-4 inline-flex items-center gap-2 text-sm font-bold uppercase tracking-[0.14em]" style="color:#2F2E7C;">Download quote PDF</a>
                            </div>

                            <div class="rounded-[24px] border p-5" style="border-color: var(--app-border); background: var(--app-surface-soft);">
                                <div class="flex items-center justify-between gap-3">
                                    <div>
                                        <div class="text-[11px] font-bold uppercase tracking-[0.16em] app-muted">Invoice</div>
                                        <div class="mt-2 text-lg font-black app-title">{{ parcel.value.invoice?.invoice_number || "Generated after acceptance" }}</div>
                                    </div>
                                    <StatusBadge :label="String(parcel.value.invoice?.payment_status || parcel.value.payment_status || 'pending').replaceAll('_', ' ')" :tone="parcel.value.invoice?.payment_status === 'paid' ? 'success' : parcel.value.invoice?.id ? 'warning' : 'neutral'" small />
                                </div>
                                <p class="mt-3 text-sm leading-6 app-muted">Issued from the confirmed booking and kept aligned with the same final totals.</p>
                                <a v-if="parcel.value.invoice?.id" :href="route('invoices.download', parcel.value.invoice.id)" class="mt-4 inline-flex items-center gap-2 text-sm font-bold uppercase tracking-[0.14em]" style="color:#2F2E7C;">Download invoice PDF</a>
                            </div>
                        </div>
                    </div>

                    <div class="app-panel rounded-[30px] p-6">
                        <div class="text-[11px] font-bold uppercase tracking-[0.18em]" style="color:#2F2E7C;">Next actions</div>
                        <div class="mt-4 grid gap-3 md:grid-cols-2">
                            <Link :href="route('user.parcels.index')" class="rounded-[22px] border px-5 py-4 transition hover:-translate-y-0.5" style="border-color: rgba(47,46,124,0.12); background:#2F2E7C; color:#FFFFFF; box-shadow:0 18px 30px rgba(47,46,124,0.16);">
                                <div class="text-base font-black">Track Parcel</div>
                                <div class="mt-1 text-sm text-white/72">Open your tracking hub and watch status updates.</div>
                            </Link>
                            <button v-if="parcel.value.quotation?.id && parcel.value.quotation?.status !== 'converted'" type="button" class="rounded-[22px] border px-5 py-4 text-left transition hover:-translate-y-0.5" style="border-color: var(--app-border); background: var(--app-surface-soft); color: var(--app-text);" @click="acceptQuote">
                                <div class="text-base font-black app-title">{{ quoteProcessing ? "Processing..." : "Accept Quote & Generate Invoice" }}</div>
                                <div class="mt-1 text-sm app-muted">Confirm the quotation and create the invoice document.</div>
                            </button>
                            <a v-if="parcel.value.quotation?.id" :href="route('quotations.download', parcel.value.quotation.id)" class="rounded-[22px] border px-5 py-4 transition hover:-translate-y-0.5" style="border-color: var(--app-border); background: var(--app-surface-soft); color: var(--app-text);">
                                <div class="text-base font-black app-title">Download Quote</div>
                                <div class="mt-1 text-sm app-muted">{{ parcel.value.quotation.quotation_number }}</div>
                            </a>
                            <a v-if="parcel.value.invoice?.id" :href="route('invoices.download', parcel.value.invoice.id)" class="rounded-[22px] border px-5 py-4 transition hover:-translate-y-0.5" style="border-color: var(--app-border); background: var(--app-surface-soft); color: var(--app-text);">
                                <div class="text-base font-black app-title">Download Invoice</div>
                                <div class="mt-1 text-sm app-muted">{{ parcel.value.invoice.invoice_number }}</div>
                            </a>

                            <Link :href="route('user.parcels.index')" class="rounded-[22px] border px-5 py-4 transition hover:-translate-y-0.5" style="border-color: var(--app-border); background: var(--app-surface-soft); color: var(--app-text);">
                                <div class="text-base font-black app-title">View My Requests</div>
                                <div class="mt-1 text-sm app-muted">See this booking together with your other deliveries.</div>
                            </Link>

                            <Link :href="route('welcome')" class="rounded-[22px] border px-5 py-4 transition hover:-translate-y-0.5" style="border-color: var(--app-border); background: var(--app-surface-soft); color: var(--app-text);">
                                <div class="text-base font-black app-title">Book Another Delivery</div>
                                <div class="mt-1 text-sm app-muted">Start a new parcel booking from the marketplace.</div>
                            </Link>

                            <div class="rounded-[22px] border px-5 py-4" style="border-color: var(--app-border); background: var(--app-surface-soft);">
                                <div class="inline-flex items-center gap-2 text-base font-black app-title">
                                    <LifeBuoy class="h-4 w-4" />
                                    Contact Support
                                </div>
                                <div class="mt-1 text-sm app-muted">Support can help if payment setup or driver contact needs attention.</div>
                            </div>
                        </div>
                    </div>
                </div>

                <aside class="space-y-6">
                    <StickyBookingSummary title="Booking details" :items="summaryItems" :badges="trustBadges">
                        <div class="mt-4">
                            <PaymentStatusCard
                                :booking-reference="parcel.value.booking_reference || parcel.value.tracking_number"
                                :tracking-number="parcel.value.tracking_number"
                                :payment-status="parcel.value.payment_status || 'ready'"
                                :booking-status="parcel.value.booking_status || 'confirmed'"
                                :total="totalLabel"
                                next-step="Tracking ready"
                            />
                        </div>
                    </StickyBookingSummary>

                    <PriceBreakdown
                        :rows="breakdownRows"
                        total-label="Confirmed total"
                        :total-value="totalLabel"
                        note="The same pricing breakdown is used in the quotation, booking summary, invoice, and future payment integrations."
                    />

                    <div class="app-panel rounded-[30px] p-6">
                        <div class="text-[11px] font-bold uppercase tracking-[0.18em]" style="color:#2F2E7C;">Payment options</div>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <span
                                v-for="method in paymentMethodLabels"
                                :key="method"
                                class="rounded-full border px-3 py-1.5 text-[11px] font-bold uppercase tracking-[0.14em]"
                                style="border-color: var(--app-border); background: var(--app-surface-soft); color: var(--app-text);"
                            >
                                {{ method }}
                            </span>
                        </div>
                    </div>
                </aside>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
