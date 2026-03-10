<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { Link } from '@inertiajs/vue3';
import axios from 'axios';

const { t } = useI18n();

interface Feed {
    id: number;
    name: string;
}

interface Article {
    id: number;
    title: string;
    link: string;
    description: string | null;
    thumbnail_url: string | null;
    published_at: string | null;
    author: string | null;
    feed: Feed;
}

interface PaginatedArticles {
    data: Article[];
    current_page: number;
    last_page: number;
    next_page_url: string | null;
    prev_page_url: string | null;
}

const articles = ref<Article[]>([]);
const pagination = ref<PaginatedArticles | null>(null);
const loading = ref(false);

async function fetchSaved(page = 1) {
    loading.value = true;
    try {
        const res = await axios.get('/api/news/saved', { params: { page } });
        pagination.value = res.data;
        articles.value = res.data.data;
    } finally {
        loading.value = false;
    }
}

async function unsave(id: number) {
    await axios.delete(`/api/news/${id}/save`);
    articles.value = articles.value.filter((a) => a.id !== id);
}

onMounted(() => fetchSaved());
</script>

<template>
    <div class="mx-auto max-w-4xl px-4 py-8">
        <h1 class="mb-6 text-2xl font-bold">{{ t('news.saved_title') }}</h1>

        <div v-if="loading" class="text-muted-foreground text-center py-12">{{ t('general.loading') }}</div>

        <p v-else-if="articles.length === 0" class="text-muted-foreground text-center py-12">
            {{ t('news.no_saved') }}
        </p>

        <ul v-else class="space-y-4">
            <li
                v-for="article in articles"
                :key="article.id"
                class="flex gap-4 rounded-xl border border-border bg-card p-4 shadow-sm"
            >
                <img
                    v-if="article.thumbnail_url"
                    :src="article.thumbnail_url"
                    :alt="article.title"
                    class="h-20 w-32 flex-shrink-0 rounded-lg object-cover"
                />
                <div class="flex flex-1 flex-col gap-1">
                    <a
                        :href="article.link"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="font-semibold leading-snug hover:underline"
                    >
                        {{ article.title }}
                    </a>
                    <p v-if="article.description" class="line-clamp-2 text-sm text-muted-foreground">
                        {{ article.description }}
                    </p>
                    <div class="mt-auto flex items-center justify-between text-xs text-muted-foreground">
                        <span>{{ article.feed.name }}</span>
                        <button
                            @click="unsave(article.id)"
                            class="text-destructive hover:underline"
                        >
                            {{ t('general.remove_saved') }}
                        </button>
                    </div>
                </div>
            </li>
        </ul>

        <div v-if="pagination && pagination.last_page > 1" class="mt-8 flex justify-center gap-2">
            <button
                :disabled="!pagination.prev_page_url"
                @click="fetchSaved(pagination!.current_page - 1)"
                class="rounded border px-3 py-1 disabled:opacity-50"
            >
                {{ t('general.back') }}
            </button>
            <span class="px-2 py-1 text-sm">{{ pagination.current_page }} / {{ pagination.last_page }}</span>
            <button
                :disabled="!pagination.next_page_url"
                @click="fetchSaved(pagination!.current_page + 1)"
                class="rounded border px-3 py-1 disabled:opacity-50"
            >
                &rsaquo;
            </button>
        </div>
    </div>
</template>

