<script setup>
import { ref, onMounted } from "vue";
import axios from "axios";
import { Head, usePage, Link } from "@inertiajs/vue3";
import PublicLayout from "@/Layouts/PublicLayout.vue";
import EmptyState from "@/Components/AppShell/EmptyState.vue";

const page = usePage();
const news = ref(null);
const loading = ref(true);

async function fetchNewsDetail() {
    try {
        const response = await axios.get(`/api/news/${page.props.id}`);
        news.value = response.data;
    } finally {
        loading.value = false;
    }
}

onMounted(fetchNewsDetail);
</script>

<template>
    <Head :title="news ? news.title : 'News Detail'" />

    <PublicLayout>
        <div class="mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:px-8">
            <div class="mb-6 flex flex-wrap gap-3">
                <Link href="/news-all" class="app-outline-btn">Back to All News</Link>
                <Link href="/" class="app-outline-btn">Back to Home</Link>
            </div>

            <div v-if="loading" class="app-panel p-10 text-center">
                <div class="mx-auto h-12 w-12 animate-spin rounded-full border-4 border-solid border-transparent" style="border-top-color: #2F2E7C;"></div>
                <p class="mt-4 app-muted">Loading news...</p>
            </div>

            <article v-else-if="news" class="app-panel overflow-hidden p-0">
                <img :src="news.image" :alt="news.title" class="h-56 w-full object-cover sm:h-72" />
                <div class="p-6 sm:p-8">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <span class="rounded-full px-3 py-1 text-sm font-semibold" style="background: #F2C900; color: #1F1F1F;">{{ news.category }}</span>
                        <span class="text-sm app-muted">{{ new Date(news.date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) }}</span>
                    </div>
                    <h1 class="mt-6 text-3xl font-black app-title sm:text-4xl">{{ news.title }}</h1>
                    <p class="mt-4 text-base leading-8 app-muted">{{ news.excerpt }}</p>
                    <div class="news-prose mt-8" v-html="news.content"></div>
                </div>
            </article>

            <div v-else>
                <EmptyState title="News not found" description="The article you are looking for does not exist." icon="N" />
            </div>
        </div>
    </PublicLayout>
</template>

<style scoped>
.news-prose :deep(*) {
    color: var(--app-text);
}
.news-prose :deep(p) {
    margin-bottom: 1rem;
    line-height: 1.9;
    color: var(--app-text-muted);
}
.news-prose :deep(h2) {
    margin-top: 1.8rem;
    margin-bottom: 0.9rem;
    font-size: 1.5rem;
    font-weight: 800;
}
.news-prose :deep(h3) {
    margin-top: 1.4rem;
    margin-bottom: 0.75rem;
    font-size: 1.2rem;
    font-weight: 800;
}
.news-prose :deep(ul),
.news-prose :deep(ol) {
    margin-bottom: 1rem;
    padding-left: 1.5rem;
}
</style>
