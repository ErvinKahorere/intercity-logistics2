<script setup>
import { computed, ref } from "vue";
import { Head } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import PageHeader from "@/Components/AppShell/PageHeader.vue";
import StatusBadge from "@/Components/AppShell/StatusBadge.vue";
import api from "@/lib/api";
import { errorToast, successToast } from "@/composables/useAppToast";

const props = defineProps({
    rules: { type: Object, default: () => ({}) },
    alerts: { type: Object, default: () => ({ inactive_rules: 0, missing_parcel_type_rules: [] }) },
    packageTypes: { type: Array, default: () => [] },
    routes: { type: Array, default: () => [] },
});

const rules = ref({ ...(props.rules || {}) });
const alerts = ref({ ...(props.alerts || {}) });
const activeTab = ref("global");
const saving = ref(false);
const simulationBusy = ref(false);
const validationMessage = ref("");
const ruleForm = ref({
    id: null,
    rule_type: "global",
    rule_key: "",
    name: "",
    description: "",
    target_type: "",
    target_id: "",
    config: "{}",
    is_active: true,
    sort_order: 10,
});
const simulator = ref({ city_route_id: "", package_type_id: "", weight_kg: "", load_size: "small", urgency_level: "standard", notes: "" });
const simulation = ref(null);

const ruleTabs = [
    { id: "global", label: "Global Pricing Rules", hint: "Platform-wide defaults" },
    { id: "weight_tiers", label: "Weight Tier Rules", hint: "Weight-based surcharges" },
    { id: "parcel_types", label: "Parcel Type Rules", hint: "Cargo-specific behavior" },
    { id: "urgency", label: "Urgency Rules", hint: "Express and same-day modifiers" },
    { id: "route_overrides", label: "Route-Specific Overrides", hint: "Lane-level adjustments" },
];
const ruleGroups = computed(() => ({
    global: rules.value.global || [],
    weight_tiers: rules.value.weight_tiers || [],
    urgency: rules.value.urgency || [],
    parcel_types: rules.value.parcel_types || [],
    route_overrides: rules.value.route_overrides || [],
}));
const activeItems = computed(() => ruleGroups.value[activeTab.value] || []);
const activeTabMeta = computed(() => ruleTabs.find((item) => item.id === activeTab.value));
const selectedRouteOption = computed(() => props.routes.find((item) => Number(item.id) === Number(simulator.value.city_route_id)) || null);

function editRule(type, item) {
    ruleForm.value = {
        id: item?.id || null,
        rule_type: item?.rule_type || type,
        rule_key: item?.rule_key || "",
        name: item?.name || "",
        description: item?.description || "",
        target_type: item?.target_type || "",
        target_id: item?.target_id || "",
        config: JSON.stringify(item?.config || {}, null, 2),
        is_active: item?.is_active ?? true,
        sort_order: item?.sort_order ?? 10,
    };
    validationMessage.value = "";
}

function ruleTypeForTab(tab) {
    return {
        global: "global",
        weight_tiers: "weight_tier",
        urgency: "urgency",
        parcel_types: "parcel_type",
        route_overrides: "route_override",
    }[tab] || "weight_tier";
}

function newRule() {
    editRule(ruleTypeForTab(activeTab.value), null);
}

async function saveRule() {
    saving.value = true;
    validationMessage.value = "";
    try {
        const payload = {
            id: ruleForm.value.id,
            rule_type: ruleForm.value.rule_type,
            rule_key: ruleForm.value.rule_key || null,
            name: ruleForm.value.name,
            description: ruleForm.value.description || null,
            target_type: ruleForm.value.target_type || null,
            target_id: ruleForm.value.target_id || null,
            config: JSON.parse(ruleForm.value.config || "{}"),
            is_active: !!ruleForm.value.is_active,
            sort_order: Number(ruleForm.value.sort_order || 0),
        };
        const { data } = ruleForm.value.id
            ? await api.put(route("admin.pricing.rules.update", ruleForm.value.id), payload)
            : await api.post(route("admin.pricing.rules.store"), payload);
        rules.value = data.rules || rules.value;
        alerts.value = data.alerts || alerts.value;
        newRule();
        successToast(data.message || "Pricing rule saved.", "Pricing updated");
    } catch (error) {
        validationMessage.value = Object.values(error.response?.data?.errors || {}).flat()[0] || "";
        errorToast(validationMessage.value || error.response?.data?.message || "Could not save pricing rule.", "Save failed");
    } finally {
        saving.value = false;
    }
}

async function deleteRule() {
    if (!ruleForm.value.id || saving.value) return;

    saving.value = true;
    validationMessage.value = "";
    try {
        const { data } = await api.delete(route("admin.pricing.rules.destroy", ruleForm.value.id));
        rules.value = data.rules || rules.value;
        alerts.value = data.alerts || alerts.value;
        newRule();
        successToast(data.message || "Pricing rule deleted.", "Pricing updated");
    } catch (error) {
        validationMessage.value = Object.values(error.response?.data?.errors || {}).flat()[0] || "";
        errorToast(validationMessage.value || error.response?.data?.message || "Could not delete pricing rule.", "Delete failed");
    } finally {
        saving.value = false;
    }
}

async function runSimulation() {
    if (!selectedRouteOption.value) {
        errorToast("Choose an operational route before running the simulator.", "Simulator needs a route");
        return;
    }

    simulationBusy.value = true;
    try {
        const { data } = await api.post(route("admin.pricing.simulate"), {
            city_route_id: selectedRouteOption.value.id,
            package_type_id: simulator.value.package_type_id,
            weight_kg: simulator.value.weight_kg,
            load_size: simulator.value.load_size,
            urgency_level: simulator.value.urgency_level,
            notes: simulator.value.notes,
        });
        simulation.value = data.simulation || null;
    } catch (error) {
        errorToast(error.response?.data?.message || "Could not run pricing simulation.", "Simulation failed");
    } finally {
        simulationBusy.value = false;
    }
}
</script>

<template>
    <Head title="Pricing" />

    <AuthenticatedLayout>
        <PageHeader eyebrow="Admin pricing" title="Pricing Rules Operations" description="Tune base fees, weight tiers, urgency, parcel-type behavior, and route-specific overrides without editing code." />

        <section class="grid gap-4 md:grid-cols-3">
            <article class="app-panel rounded-[28px] p-5"><div class="flex items-center justify-between gap-3"><div><div class="text-[11px] font-bold uppercase tracking-[0.18em] app-muted">Inactive rules</div><div class="mt-3 text-3xl font-black app-title">{{ alerts.inactive_rules || 0 }}</div></div><StatusBadge :label="alerts.inactive_rules ? 'review' : 'healthy'" :tone="alerts.inactive_rules ? 'warning' : 'success'" small /></div></article>
            <article class="app-panel rounded-[28px] p-5"><div class="flex items-center justify-between gap-3"><div><div class="text-[11px] font-bold uppercase tracking-[0.18em] app-muted">Parcel rule gaps</div><div class="mt-3 text-3xl font-black app-title">{{ (alerts.missing_parcel_type_rules || []).length }}</div></div><StatusBadge :label="(alerts.missing_parcel_type_rules || []).length ? 'action' : 'covered'" :tone="(alerts.missing_parcel_type_rules || []).length ? 'danger' : 'success'" small /></div></article>
            <article class="app-panel rounded-[28px] p-5"><div class="flex items-center justify-between gap-3"><div><div class="text-[11px] font-bold uppercase tracking-[0.18em] app-muted">Weight tiers</div><div class="mt-3 text-3xl font-black app-title">{{ (ruleGroups.weight_tiers || []).length }}</div></div><StatusBadge label="tiers" tone="brand" small /></div></article>
        </section>

        <section class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_420px]">
            <article class="space-y-6">
                <div class="app-panel rounded-[30px] p-5 sm:p-6">
                    <div class="grid gap-3 lg:grid-cols-2 xl:grid-cols-3">
                        <button v-for="item in ruleTabs" :key="item.id" type="button" class="rounded-[22px] border p-4 text-left transition" :style="activeTab === item.id ? 'border-color:#2F2E7C;background:rgba(47,46,124,0.06);' : 'border-color:var(--app-border);background:var(--app-surface-soft);'" @click="activeTab = item.id">
                            <div class="text-sm font-black app-title">{{ item.label }}</div>
                            <div class="mt-1 text-xs leading-5 app-muted">{{ item.hint }}</div>
                        </button>
                    </div>

                    <div class="mt-5 rounded-[24px] border p-5" style="border-color: rgba(47,46,124,0.12); background: rgba(47,46,124,0.05);">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <div class="text-[11px] font-bold uppercase tracking-[0.16em] app-muted">{{ activeTabMeta?.label }}</div>
                                <div class="mt-2 text-lg font-black app-title">{{ activeItems.length }} rule{{ activeItems.length === 1 ? '' : 's' }} in this section</div>
                            </div>
                            <StatusBadge :label="activeItems.length ? 'configured' : 'empty'" :tone="activeItems.length ? 'brand' : 'warning'" small />
                        </div>
                        <button type="button" class="app-outline-btn mt-4" @click="newRule">Create new rule</button>
                    </div>

                    <div class="mt-5 grid gap-3">
                        <button v-for="item in activeItems" :key="item.id" type="button" class="rounded-[24px] border p-4 text-left transition hover:-translate-y-0.5" style="border-color: var(--app-border); background: var(--app-surface-soft);" @click="editRule(activeTab, item)">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <div class="text-base font-black app-title">{{ item.name }}</div>
                                    <div class="mt-1 text-sm app-muted">{{ item.description || item.rule_key || 'Open to review the exact rule config.' }}</div>
                                </div>
                                <StatusBadge :label="item.is_active ? 'active' : 'inactive'" :tone="item.is_active ? 'success' : 'neutral'" small />
                            </div>
                        </button>
                    </div>
                </div>

                <div class="app-panel rounded-[30px] p-6">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <div class="text-[11px] font-bold uppercase tracking-[0.18em]" style="color:#2F2E7C;">Pricing simulator</div>
                            <h2 class="mt-2 text-2xl font-black app-title">Preview rule impact instantly</h2>
                        </div>
                        <StatusBadge :label="simulation ? 'simulation ready' : 'awaiting input'" :tone="simulation ? 'success' : 'neutral'" small />
                    </div>
                    <div class="mt-5 grid gap-4 md:grid-cols-2">
                        <select v-model="simulator.city_route_id" class="app-field md:col-span-2"><option value="">Choose operational route</option><option v-for="item in routes" :key="item.id" :value="item.id">{{ item.name }}</option></select>
                        <select v-model="simulator.package_type_id" class="app-field"><option value="">Parcel type</option><option v-for="item in packageTypes" :key="item.id" :value="item.id">{{ item.name }}</option></select>
                        <input v-model="simulator.weight_kg" type="number" step="0.1" class="app-field" placeholder="Weight kg" />
                        <select v-model="simulator.load_size" class="app-field"><option value="small">Small</option><option value="medium">Medium</option><option value="large">Large</option><option value="heavy">Heavy</option><option value="oversized">Oversized</option></select>
                        <select v-model="simulator.urgency_level" class="app-field"><option value="standard">Standard</option><option value="express">Express</option><option value="same_day">Same Day</option></select>
                    </div>
                    <div v-if="selectedRouteOption" class="mt-4 rounded-[20px] border px-4 py-4 text-sm app-muted" style="border-color: var(--app-border); background: var(--app-surface-soft);">
                        Simulating on <strong class="app-title">{{ selectedRouteOption.name }}</strong> using the active route distance and pricing rules.
                    </div>
                    <textarea v-model="simulator.notes" rows="3" class="mt-4 w-full rounded-[24px] border" style="border-color: var(--app-border); background: var(--app-surface-soft);" placeholder="Fragile, forklift, mining site, refrigerated..."></textarea>
                    <button type="button" class="app-primary-btn mt-4" :disabled="simulationBusy" @click="runSimulation">{{ simulationBusy ? 'Running...' : 'Run Simulator' }}</button>

                    <div v-if="simulation" class="mt-5 rounded-[24px] border p-5" style="border-color: rgba(47,46,124,0.12); background: rgba(47,46,124,0.05);">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <div class="text-[11px] font-bold uppercase tracking-[0.16em] app-muted">Simulation result</div>
                                <div class="mt-2 text-2xl font-black app-title">N$ {{ Number(simulation.total_price || 0).toFixed(2) }}</div>
                            </div>
                            <div class="text-sm app-muted">{{ Number(simulation.distance_km || 0).toFixed(0) }} km · {{ Number(simulation.estimated_hours || 0).toFixed(1) }} hrs</div>
                        </div>
                        <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                            <div class="rounded-[18px] p-3" style="background: rgba(255,255,255,0.75);"><div class="text-xs app-muted">Base dispatch</div><div class="mt-1 text-sm font-black app-title">N$ {{ Number(simulation.base_price || 0).toFixed(2) }}</div></div>
                            <div class="rounded-[18px] p-3" style="background: rgba(255,255,255,0.75);"><div class="text-xs app-muted">Distance charge</div><div class="mt-1 text-sm font-black app-title">N$ {{ Number(simulation.distance_fee || 0).toFixed(2) }}</div></div>
                            <div class="rounded-[18px] p-3" style="background: rgba(255,255,255,0.75);"><div class="text-xs app-muted">Weight adjustment</div><div class="mt-1 text-sm font-black app-title">N$ {{ Number(simulation.weight_surcharge || 0).toFixed(2) }}</div></div>
                            <div class="rounded-[18px] p-3" style="background: rgba(255,255,255,0.75);"><div class="text-xs app-muted">Urgency surcharge</div><div class="mt-1 text-sm font-black app-title">N$ {{ Number(simulation.urgency_surcharge || 0).toFixed(2) }}</div></div>
                            <div class="rounded-[18px] p-3" style="background: rgba(255,255,255,0.75);"><div class="text-xs app-muted">Handling</div><div class="mt-1 text-sm font-black app-title">N$ {{ Number(simulation.special_handling_fee || 0).toFixed(2) }}</div></div>
                            <div class="rounded-[18px] p-3" style="background: rgba(255,255,255,0.75);"><div class="text-xs app-muted">Minimum charge</div><div class="mt-1 text-sm font-black app-title">N$ {{ Number(simulation.minimum_charge || 0).toFixed(2) }}</div></div>
                        </div>
                    </div>
                </div>
            </article>

            <article class="app-panel rounded-[30px] p-6">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <div class="text-[11px] font-bold uppercase tracking-[0.18em]" style="color:#2F2E7C;">Rule editor</div>
                        <h2 class="mt-2 text-2xl font-black app-title">Create or update rule</h2>
                    </div>
                    <StatusBadge :label="ruleForm.id ? 'editing' : 'new rule'" :tone="ruleForm.id ? 'brand' : 'neutral'" />
                </div>

                <div class="mt-5 grid gap-4">
                    <select v-model="ruleForm.rule_type" class="app-field"><option value="global">Global</option><option value="weight_tier">Weight tier</option><option value="parcel_type">Parcel type</option><option value="urgency">Urgency</option><option value="route_override">Route override</option></select>
                    <input v-model="ruleForm.name" type="text" class="app-field" placeholder="Rule name" />
                    <input v-model="ruleForm.rule_key" type="text" class="app-field" placeholder="Rule key" />
                    <input v-model="ruleForm.description" type="text" class="app-field" placeholder="Description" />
                    <select v-model="ruleForm.target_type" class="app-field"><option value="">No target</option><option value="package_type">Package type</option><option value="city_route">City route</option></select>
                    <select v-if="ruleForm.target_type === 'package_type'" v-model="ruleForm.target_id" class="app-field"><option value="">Select package type</option><option v-for="item in packageTypes" :key="item.id" :value="item.id">{{ item.name }}</option></select>
                    <select v-if="ruleForm.target_type === 'city_route'" v-model="ruleForm.target_id" class="app-field"><option value="">Select route</option><option v-for="item in routes" :key="item.id" :value="item.id">{{ item.name }}</option></select>
                    <input v-model="ruleForm.sort_order" type="number" class="app-field" placeholder="Sort order" />
                    <label class="inline-flex items-center gap-2 text-sm app-muted"><input v-model="ruleForm.is_active" type="checkbox" /> Rule active</label>
                    <textarea v-model="ruleForm.config" rows="14" class="w-full rounded-[24px] border font-mono text-sm" style="border-color: var(--app-border); background: var(--app-surface-soft);"></textarea>
                    <div class="text-xs leading-5 app-muted">Use valid JSON. Example keys depend on the rule type, such as <code>multiplier</code>, <code>fee</code>, <code>min_weight</code>, or route override amounts.</div>
                    <div v-if="validationMessage" class="rounded-[18px] border px-4 py-3 text-sm" style="border-color: rgba(220,38,38,0.16); background: rgba(220,38,38,0.06); color:#991b1b;">{{ validationMessage }}</div>
                    <div class="flex flex-wrap gap-3">
                        <button type="button" class="app-primary-btn" :disabled="saving" @click="saveRule">{{ saving ? 'Saving...' : 'Save Rule' }}</button>
                        <button v-if="ruleForm.id" type="button" class="app-outline-btn" :disabled="saving" @click="deleteRule">Delete rule</button>
                    </div>
                </div>

                <div class="mt-5 rounded-[24px] border p-4 text-sm app-muted" style="border-color: var(--app-border); background: var(--app-surface-soft);">
                    Missing parcel type rules:
                    {{ (alerts.missing_parcel_type_rules || []).map((item) => item.name).join(', ') || 'none' }}
                </div>
            </article>
        </section>
    </AuthenticatedLayout>
</template>
