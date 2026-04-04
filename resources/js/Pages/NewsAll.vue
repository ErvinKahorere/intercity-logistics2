<script setup>
import { ref, onMounted } from "vue";
import axios from "axios";
import { Head } from "@inertiajs/vue3";
import PublicLayout from "@/Layouts/PublicLayout.vue";
import EmptyState from "@/Components/AppShell/EmptyState.vue";

const newsItems = ref([]);
const loading = ref(true);

function imageUrl(img) {
    if (!img) return null;
    if (typeof img === "string" && (img.startsWith("http://") || img.startsWith("https://") || img.startsWith("/"))) return img;
    return "/storage/" + img;
}

async function fetchNews() {
    try {
        const response = await axios.get("/api/news");
        newsItems.value = response.data;
    } finally {
        loading.value = false;
    }
}

onMounted(fetchNews);
</script>

<template>
    <Head title="All News & Media" />

    <PublicLayout>
        <div class="mx-auto max-w-[1680px] px-4 py-8 sm:px-6 lg:px-8">
            <section class="app-panel p-6 text-center md:p-8">
                <h1 class="text-3xl font-black app-title sm:text-4xl lg:text-5xl">All News & Media</h1>
                <p class="mx-auto mt-4 max-w-3xl text-lg leading-8 app-muted">
                    Stay updated with the latest announcements, route updates, and platform news from InterCity Logistics.
                </p>
            </section>

            <div v-if="loading" class="mt-8 app-panel p-10 text-center">
                <div class="mx-auto h-12 w-12 animate-spin rounded-full border-4 border-solid border-transparent" style="border-top-color: #2F2E7C;"></div>
                <p class="mt-4 app-muted">Loading news...</p>
            </div>

            <div v-else-if="newsItems.length" class="mt-8 grid gap-6 md:grid-cols-2 xl:grid-cols-3">
                <article v-for="news in newsItems" :key="news.id || news.title" class="app-panel overflow-hidden p-0 transition hover:-translate-y-1" @click="$inertia.visit(`/news/${news.id}`)">
                    <img v-if="imageUrl(news.image)" :src="imageUrl(news.image)" :alt="news.title" class="h-52 w-full object-cover" />
                    <div class="p-5">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <span class="rounded-full px-3 py-1 text-sm font-semibold" style="background: #F2C900; color: #1F1F1F;">{{ news.category }}</span>
                            <span class="text-sm app-muted">{{ new Date(news.date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) }}</span>
                        </div>
                        <h2 class="mt-4 text-xl font-black app-title">{{ news.title }}</h2>
                        <p class="mt-3 text-sm leading-7 app-muted">{{ news.excerpt }}</p>
                        <div class="mt-4 text-sm font-bold uppercase tracking-[0.16em]" style="color: #2F2E7C;">Read more</div>
                    </div>
                </article>
            </div>

            <div v-else class="mt-8">
                <EmptyState title="No news available" description="There are no news articles to show right now." icon="N" />
            </div>
        </div>
    </PublicLayout>
</template>


