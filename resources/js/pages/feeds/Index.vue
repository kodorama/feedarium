<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { useSavedArticles } from '@/composables/useSavedArticles';
import { useFeedNotifications } from '@/composables/useFeedNotifications';
import axios from 'axios';
import { Bookmark, BookmarkCheck, Rss, ExternalLink, RefreshCw, X } from 'lucide-vue-next';

const { t } = useI18n();
const { save, unsave, isSaving } = useSavedArticles();
const { feedUpdated, resetNotification } = useFeedNotifications();

const breadcrumbs: BreadcrumbItem[] = [
    { title: t('nav.dashboard'), href: '/dashboard' },
    { title: t('news.title'), href: '/feeds' },
];

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
    full_body: string | null;
    published_at: string | null;
    author: string | null;
    feed: Feed;
}

interface Pagination {
    data: Article[];
    current_page: number;
    last_page: number;
    next_page_url: string | null;
    prev_page_url: string | null;
}

const articles = ref<Article[]>([]);
const pagination = ref<Pagination | null>(null);
const loading = ref(true);
const savedIds = ref<Set<number>>(new Set());
const readerArticle = ref<Article | null>(null);

async function loadArticles(page = 1) {
    loading.value = true;
    try {
        const res = await axios.get('/api/news', { params: { page } });
        pagination.value = res.data;
        articles.value = res.data.data;
    } finally {
        loading.value = false;
    }
}

async function toggleSave(article: Article) {
    if (savedIds.value.has(article.id)) {
        await unsave(article.id);
        savedIds.value.delete(article.id);
    } else {
        await save(article.id);
        savedIds.value.add(article.id);
    }
}

async function refresh() {
    resetNotification();
    await loadArticles();
}

function openReader(article: Article) {
    if (article.full_body) {
        readerArticle.value = article;
    } else {
        window.open(article.link, '_blank', 'noopener,noreferrer');
    }
}

function formatDate(dateStr: string | null): string {
    if (!dateStr) return '';
    return new Date(dateStr).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
}

onMounted(() => loadArticles());
</script>

<template>
    <Head :title="t('news.title')" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-4 md:p-6">
            <!-- Feed-updated notification banner -->
            <div v-if="feedUpdated" class="mb-4 flex items-center justify-between rounded-lg bg-blue-50 px-4 py-3 text-sm text-blue-800 dark:bg-blue-900/30 dark:text-blue-200">
                <span>New articles are available. Refresh to see them.</span>
                <button @click="refresh" class="ml-4 flex items-center gap-1 rounded bg-blue-600 px-3 py-1 text-white hover:bg-blue-700">
                    <RefreshCw class="h-4 w-4" /> Refresh
                </button>
            </div>

            <div class="mb-4 flex items-center justify-between">
                <h1 class="text-2xl font-bold">{{ t('news.title') }}</h1>
            </div>

            <div v-if="loading" class="py-12 text-center text-muted-foreground">{{ t('general.loading') }}</div>

            <div v-else-if="articles.length === 0" class="py-12 text-center text-muted-foreground">
                <Rss class="mx-auto mb-3 h-10 w-10 opacity-40" />
                <p>{{ t('general.no_results') }}</p>
            </div>

            <ul v-else class="space-y-4">
                <li
                    v-for="article in articles"
                    :key="article.id"
                    class="flex gap-4 rounded-xl border border-border bg-card p-4 shadow-sm transition hover:shadow-md"
                >
                    <img
                        v-if="article.thumbnail_url"
                        :src="article.thumbnail_url"
                        :alt="article.title"
                        class="h-20 w-32 flex-shrink-0 rounded-lg object-cover sm:h-24 sm:w-36"
                    />
                    <div class="flex flex-1 flex-col gap-1 min-w-0">
                        <button
                            @click="openReader(article)"
                            class="truncate text-left font-semibold leading-snug hover:underline"
                        >
                            {{ article.title }}
                        </button>
                        <p v-if="article.description" class="line-clamp-2 text-sm text-muted-foreground">
                            {{ article.description }}
                        </p>
                        <div class="mt-auto flex flex-wrap items-center justify-between gap-2 text-xs text-muted-foreground">
                            <div class="flex items-center gap-3">
                                <span class="flex items-center gap-1">
                                    <Rss class="h-3 w-3" />
                                    {{ article.feed?.name }}
                                </span>
                                <span v-if="article.published_at">{{ formatDate(article.published_at) }}</span>
                                <span v-if="article.author">{{ t('news.by') }} {{ article.author }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <a
                                    :href="article.link"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="flex items-center gap-1 hover:text-foreground"
                                    :title="t('general.read_more')"
                                >
                                    <ExternalLink class="h-4 w-4" />
                                </a>
                                <button
                                    @click="toggleSave(article)"
                                    :disabled="isSaving(article.id)"
                                    class="flex items-center gap-1 hover:text-primary transition-colors"
                                    :title="savedIds.has(article.id) ? t('general.remove_saved') : t('general.read_later')"
                                >
                                    <BookmarkCheck v-if="savedIds.has(article.id)" class="h-4 w-4 text-primary" />
                                    <Bookmark v-else class="h-4 w-4" />
                                </button>
                            </div>
                        </div>
                    </div>
                </li>
            </ul>

            <!-- Pagination -->
            <div v-if="pagination && pagination.last_page > 1" class="mt-8 flex justify-center gap-2">
                <button
                    :disabled="!pagination.prev_page_url"
                    @click="loadArticles(pagination!.current_page - 1)"
                    class="min-h-[44px] min-w-[44px] rounded border px-3 py-1 disabled:opacity-50"
                >
                    ‹
                </button>
                <span class="flex items-center px-2 py-1 text-sm">{{ pagination.current_page }} / {{ pagination.last_page }}</span>
                <button
                    :disabled="!pagination.next_page_url"
                    @click="loadArticles(pagination!.current_page + 1)"
                    class="min-h-[44px] min-w-[44px] rounded border px-3 py-1 disabled:opacity-50"
                >
                    ›
                </button>
            </div>
        </div>

        <!-- Full-body reader overlay (Phase 10) -->
        <Teleport to="body">
            <div
                v-if="readerArticle"
                class="fixed inset-0 z-50 overflow-y-auto bg-background/95 backdrop-blur-sm"
            >
                <div class="mx-auto max-w-3xl px-4 py-8">
                    <div class="mb-4 flex items-start justify-between gap-4">
                        <h2 class="text-xl font-bold leading-snug">{{ readerArticle.title }}</h2>
                        <button
                            @click="readerArticle = null"
                            class="flex-shrink-0 rounded p-1 hover:bg-muted"
                        >
                            <X class="h-5 w-5" />
                        </button>
                    </div>
                    <div class="mb-4 flex flex-wrap gap-3 text-xs text-muted-foreground">
                        <span>{{ readerArticle.feed?.name }}</span>
                        <span v-if="readerArticle.published_at">{{ formatDate(readerArticle.published_at) }}</span>
                        <a :href="readerArticle.link" target="_blank" rel="noopener noreferrer" class="flex items-center gap-1 text-primary hover:underline">
                            <ExternalLink class="h-3 w-3" /> {{ t('general.read_more') }}
                        </a>
                    </div>
                    <!-- eslint-disable vue/no-v-html -->
                    <div class="prose prose-sm max-w-none dark:prose-invert" v-html="readerArticle.full_body" />
                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>
