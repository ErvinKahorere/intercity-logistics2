<script setup>
import { Head, usePage } from "@inertiajs/vue3";
import PublicLayout from "@/Layouts/PublicLayout.vue";
import HeroMarketplace from "@/Components/Landing/HeroMarketplace.vue";
import TrustSteps from "@/Components/Landing/TrustSteps.vue";
import LiveRequests from "@/Components/Landing/LiveRequests.vue";
import DriversMarquee from "@/Components/Landing/DriversMarquee.vue";
import FeatureTiles from "@/Components/Landing/FeatureTiles.vue";
import SocialProof from "@/Components/Landing/SocialProof.vue";
import FinalCta from "@/Components/Landing/FinalCta.vue";

const page = usePage();
const locations = page.props.locations || [];
const packageTypes = page.props.packageTypes || [];
const cityRoutes = page.props.cityRoutes || [];
const liveRequests = page.props.liveRequests || [];
const featuredDrivers = page.props.featuredDrivers || [];
const driverReadyCount = Number(page.props.driverReadyCount || featuredDrivers.length || 0);

const flowSteps = [
    { number: "01", icon: "1", title: "Create request", description: "Route, parcel, estimate" },
    { number: "02", icon: "2", title: "See top drivers", description: "Best matches rank first" },
    { number: "03", icon: "3", title: "Track delivery", description: "Status stays visible" },
];

const featureTiles = [
    { icon: "FM", title: "Fast matching", copy: "Ranked drivers for the lane you need." },
    { icon: "NR", title: "City lanes", copy: "Built for Namibia intercity movement." },
    { icon: "FD", title: "Flexible loads", copy: "Documents, stock, furniture, heavy loads." },
    { icon: "TR", title: "Live tracking", copy: "Clear status from request to delivery." },
];

const proofStats = [
    { label: "Driver trust rating", value: "4.8/5" },
    { label: "Tracked deliveries", value: "12k+" },
    { label: "Routes active daily", value: "30+" },
];

const testimonials = [
    { name: "Selma Trading", role: "Retail customer", rating: "4.9 rated", quote: "The quote is quick and the right drivers show up fast." },
    { name: "Petrus Nghipandulwa", role: "Intercity driver", rating: "Trusted", quote: "I can spot the best jobs fast and move without guesswork." },
    { name: "Kavango Supplies", role: "Business sender", rating: "Reliable", quote: "We use it for both urgent parcels and larger intercity loads." },
];
</script>

<template>
    <Head title="InterCity Logistics" />

    <PublicLayout>
        <main class="pb-10">
            <HeroMarketplace
                :locations="locations"
                :package-types="packageTypes"
                :city-routes="cityRoutes"
                :driver-ready-count="driverReadyCount"
            />
            <TrustSteps :steps="flowSteps" />
            <LiveRequests :items="liveRequests" />
            <DriversMarquee :drivers="featuredDrivers" />
            <FeatureTiles :items="featureTiles" />
            <SocialProof :stats="proofStats" :testimonials="testimonials" />
            <FinalCta />
        </main>
    </PublicLayout>
</template>
