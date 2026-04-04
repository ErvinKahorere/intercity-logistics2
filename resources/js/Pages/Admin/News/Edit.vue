<script setup>
import AppLayout from "@/Layouts/AuthenticatedLayout.vue";
import { useForm, router, Link } from "@inertiajs/vue3";
import { ref, onMounted } from "vue";
import axios from "axios";

const props = defineProps({
    news: { type: Object, required: true },
});

const editForm = useForm({
    title: props.news.title || "",
    excerpt: props.news.excerpt || "",
    content: props.news.content || "",
    image: null,
    category: props.news.category || "",
    date: props.news.date ? props.news.date.slice(0, 10) : new Date().toISOString().slice(0, 10),
});

const imagePreviewEdit = ref(props.news.image ? `/storage/${props.news.image}` : null);
const contentField = ref(null);
const isUploadingInlineImage = ref(false);

const contentTools = [
    { label: "H2", start: "<h2>", end: "</h2>" },
    { label: "Bold", start: "<strong>", end: "</strong>" },
    { label: "List", start: `<ul>
  <li>`, end: `</li>
</ul>` },
    { label: "Quote", start: "<blockquote>", end: "</blockquote>" },
];

function onPickEdit(e) {
    const file = e?.target?.files?.[0];
    editForm.image = file || null;
    imagePreviewEdit.value = file ? URL.createObjectURL(file) : imagePreviewEdit.value;
}

function insertSnippet(start, end = "") {
    const field = contentField.value;
    if (!field) {
        editForm.content += `${start}${end}`;
        return;
    }

    const selectionStart = field.selectionStart ?? editForm.content.length;
    const selectionEnd = field.selectionEnd ?? editForm.content.length;
    const selectedText = editForm.content.slice(selectionStart, selectionEnd);
    const nextValue = `${editForm.content.slice(0, selectionStart)}${start}${selectedText}${end}${editForm.content.slice(selectionEnd)}`;

    editForm.content = nextValue;

    requestAnimationFrame(() => {
        field.focus();
        const cursor = selectionStart + start.length + selectedText.length + end.length;
        field.setSelectionRange(cursor, cursor);
    });
}

async function insertInlineImage() {
    const input = document.createElement("input");
    input.type = "file";
    input.accept = "image/*";
    input.click();

    input.onchange = async () => {
        const file = input.files?.[0];
        if (!file) return;

        const formData = new FormData();
        formData.append("image", file);
        isUploadingInlineImage.value = true;

        try {
            const response = await axios.post(route("admin.news.upload-image"), formData, {
                headers: { "Content-Type": "multipart/form-data" },
            });

            insertSnippet(`<figure>
  <img src="${response.data.url}" alt="News image" />
  <figcaption>`, `</figcaption>
</figure>`);
        } catch (error) {
            console.error("Image upload failed:", error);
            alert("Image upload failed. Please try again.");
        } finally {
            isUploadingInlineImage.value = false;
        }
    };
}

function submitEdit() {
    if (!editForm.content || editForm.content.replace(/<[^>]*>/g, "").trim() === "") {
        editForm.errors.content = "Content is required";
        return;
    }

    editForm.transform((data) => ({ ...data, _method: "put" }));
    editForm.post(route("admin.news.update", props.news.id), {
        forceFormData: true,
        onSuccess: () => {
            router.visit(route("admin.news.index"));
        },
    });
}

onMounted(() => {
    document.getElementById("edit-title")?.focus();
});
</script>

<template>
    <AppLayout title="Edit News">
        <div class="flex items-center justify-between px-4 py-4">
            <h2 class="text-xl font-semibold text-gray-800">Edit News</h2>
            <Link
                :href="route('admin.news.index')"
                class="inline-flex items-center gap-2 rounded-2xl bg-gray-600 px-4 py-2 text-white shadow hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-300"
            >
                Back to News
            </Link>
        </div>

        <div class="py-8">
            <div class="mx-auto max-w-4xl space-y-6 sm:px-6 lg:px-8">
                <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-100">
                    <form @submit.prevent="submitEdit" class="space-y-4">
                        <div>
                            <label for="edit-title" class="mb-1 block text-sm font-medium text-gray-700">Title</label>
                            <input
                                id="edit-title"
                                v-model="editForm.title"
                                type="text"
                                class="w-full rounded-xl border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                required
                            />
                            <p v-if="editForm.errors.title" class="mt-1 text-sm text-red-600">{{ editForm.errors.title }}</p>
                        </div>

                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Excerpt</label>
                            <textarea
                                v-model="editForm.excerpt"
                                rows="2"
                                class="w-full rounded-xl border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                required
                            />
                            <p v-if="editForm.errors.excerpt" class="mt-1 text-sm text-red-600">{{ editForm.errors.excerpt }}</p>
                        </div>

                        <div>
                            <div class="mb-2 flex items-center justify-between gap-3">
                                <label class="block text-sm font-medium text-gray-700">Content</label>
                                <div class="flex flex-wrap gap-2">
                                    <button
                                        v-for="tool in contentTools"
                                        :key="tool.label"
                                        type="button"
                                        class="rounded-xl border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 transition hover:bg-gray-50"
                                        @click="insertSnippet(tool.start, tool.end)"
                                    >
                                        {{ tool.label }}
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded-xl border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 transition hover:bg-gray-50 disabled:opacity-60"
                                        :disabled="isUploadingInlineImage"
                                        @click="insertInlineImage"
                                    >
                                        {{ isUploadingInlineImage ? 'Uploading image...' : 'Insert image' }}
                                    </button>
                                </div>
                            </div>
                            <textarea
                                ref="contentField"
                                v-model="editForm.content"
                                rows="14"
                                class="w-full rounded-2xl border border-gray-300 font-mono text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                placeholder="Write the news body using simple HTML, for example <p>, <h2>, <strong>, <ul>, and <img>."
                                required
                            />
                            <p class="mt-2 text-xs text-gray-500">Use simple HTML for headings, lists, links, and images. The toolbar adds common snippets for you.</p>
                            <p v-if="editForm.errors.content" class="mt-1 text-sm text-red-600">{{ editForm.errors.content }}</p>
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700">Category</label>
                                <input
                                    v-model="editForm.category"
                                    type="text"
                                    class="w-full rounded-xl border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                    required
                                />
                                <p v-if="editForm.errors.category" class="mt-1 text-sm text-red-600">{{ editForm.errors.category }}</p>
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700">Date</label>
                                <input
                                    v-model="editForm.date"
                                    type="date"
                                    class="w-full rounded-xl border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                    required
                                />
                                <p v-if="editForm.errors.date" class="mt-1 text-sm text-red-600">{{ editForm.errors.date }}</p>
                            </div>
                        </div>

                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Image (optional)</label>
                            <div class="flex items-center gap-4">
                                <label class="inline-flex cursor-pointer items-center justify-center rounded-xl border border-dashed border-gray-300 px-4 py-2 text-sm hover:bg-gray-50">
                                    <input
                                        type="file"
                                        accept="image/*"
                                        class="hidden"
                                        @change="onPickEdit"
                                    />
                                    Choose file
                                </label>
                                <img
                                    v-if="imagePreviewEdit"
                                    :src="imagePreviewEdit"
                                    class="h-12 w-16 rounded-lg object-cover ring-1 ring-gray-200"
                                />
                            </div>
                            <p v-if="editForm.errors.image" class="mt-1 text-sm text-red-600">{{ editForm.errors.image }}</p>
                        </div>

                        <div class="flex justify-end gap-2 pt-2">
                            <Link
                                :href="route('admin.news.index')"
                                class="rounded-xl border border-gray-300 px-4 py-2 text-sm shadow-sm hover:bg-gray-50"
                            >
                                Cancel
                            </Link>
                            <button
                                type="submit"
                                :disabled="editForm.processing"
                                class="inline-flex items-center gap-2 rounded-2xl bg-blue-600 px-4 py-2 text-white shadow hover:bg-blue-700 disabled:opacity-60"
                            >
                                <svg v-if="editForm.processing" class="h-4 w-4 animate-spin" viewBox="0 0 24 24">
                                    <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none" opacity=".25" />
                                    <path d="M22 12a10 10 0 00-10-10" stroke="currentColor" stroke-width="4" fill="none" stroke-linecap="round" />
                                </svg>
                                Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
