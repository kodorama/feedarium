<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { ref, onMounted, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useSavedArticles } from '@/composables/useSavedArticles';
import { useFeedNotifications } from '@/composables/useFeedNotifications';
import axios from 'axios';
import { Bookmark, BookmarkCheck, Rss, ExternalLink, RefreshCw, X, LayoutList, LayoutGrid } from 'lucide-vue-next';

const { t } = useI18n();
const { save, unsave, isSaving } = useSavedArticles();
const { feedUpdated, resetNotification } = useFeedNotifications();

const props = defineProps<{
    selectedFeedId?: number | null;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: t('nav.feeds'), href: '/feeds' },
];

interface Feed {
    id: number;
    name: string;
    favicon_url: string | null;
}

type ViewMode = 'list' | 'grid';
const viewMode = ref<ViewMode>('grid');

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

function stripHtml(html: string | null): string {
    if (!html) return '';
    const doc = new DOMParser().parseFromString(html, 'text/html');
    return doc.body.textContent ?? '';
}

const articles = ref<Article[]>([]);
const pagination = ref<Pagination | null>(null);
const loading = ref(true);
const savedIds = ref<Set<number>>(new Set());
const readerArticle = ref<Article | null>(null);
const selectedFeedId = ref<number | null>(props.selectedFeedId ?? null);

watch(() => props.selectedFeedId, (val) => {
    selectedFeedId.value = val ?? null;
    loadArticles(1);
});

async function loadArticles(page = 1) {
    loading.value = true;
    try {
        const params: Record<string, unknown> = { page };
        if (selectedFeedId.value !== null) params.feed_id = selectedFeedId.value;
        const res = await axios.get('/api/news', { params });
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

/** Always open the reader modal regardless of whether full_body is available. */
function openReader(article: Article) {
    readerArticle.value = article;
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
        <div class="flex-1 p-4 md:p-6">
            <!-- Feed-updated notification banner -->
            <div v-if="feedUpdated" class="mb-4 flex items-center justify-between rounded-lg bg-blue-50 px-4 py-3 text-sm text-blue-800 dark:bg-blue-900/30 dark:text-blue-200">
                <span>New articles are available. Refresh to see them.</span>
                <button @click="refresh" class="ml-4 flex items-center gap-1 rounded bg-blue-600 px-3 py-1 text-white hover:bg-blue-700">
                    <RefreshCw class="h-4 w-4" /> Refresh
                </button>
            </div>

            <!-- Header: title + view-mode toggle -->
            <div class="mb-4 flex items-center justify-between gap-4">
                <h1 class="text-2xl font-bold">{{ t('news.title') }}</h1>
                <div class="flex items-center gap-0.5 rounded-lg border border-border p-1">
                    <button
                        @click="viewMode = 'list'"
                        :class="['rounded p-1.5 transition-colors', viewMode === 'list' ? 'bg-primary text-primary-foreground' : 'hover:bg-muted text-muted-foreground']"
                        title="List view"
                    >
                        <LayoutList class="h-4 w-4" />
                    </button>
                    <button
                        @click="viewMode = 'grid'"
                        :class="['rounded p-1.5 transition-colors', viewMode === 'grid' ? 'bg-primary text-primary-foreground' : 'hover:bg-muted text-muted-foreground']"
                        title="Grid view"
                    >
                        <LayoutGrid class="h-4 w-4" />
                    </button>
                </div>
            </div>

            <div v-if="loading" class="py-12 text-center text-muted-foreground">{{ t('general.loading') }}</div>

            <div v-else-if="articles.length === 0" class="py-12 text-center text-muted-foreground">
                <Rss class="mx-auto mb-3 h-10 w-10 opacity-40" />
                <p>{{ t('general.no_results') }}</p>
            </div>

            <!-- Article list / grid -->
            <ul
                v-else
                :class="viewMode === 'grid'
                    ? 'grid grid-cols-2 gap-4 sm:grid-cols-3 xl:grid-cols-4'
                    : 'space-y-4'"
            >
                <li
                    v-for="article in articles"
                    :key="article.id"
                    @click="openReader(article)"
                    :class="[
                        'cursor-pointer rounded-xl border border-border bg-card shadow-sm transition hover:shadow-md',
                        viewMode === 'grid' ? 'flex flex-col overflow-hidden' : 'flex gap-4 p-4',
                    ]"
                >
                    <!-- Thumbnail -->
                    <img
                        v-if="article.thumbnail_url"
                        :src="article.thumbnail_url"
                        :alt="article.title"
                        referrerpolicy="no-referrer"
                        :class="viewMode === 'grid'
                            ? 'h-44 w-full flex-shrink-0 object-cover'
                            : 'h-20 w-32 flex-shrink-0 rounded-lg object-cover sm:h-24 sm:w-36'"
                        @error="($event.target as HTMLImageElement).style.display = 'none'"
                    />

                    <!-- Content -->
                    <div
                        :class="[
                            'flex flex-1 flex-col gap-1 min-w-0',
                            viewMode === 'grid' ? 'p-4' : '',
                        ]"
                    >
                        <span
                            :class="['font-semibold leading-snug', viewMode === 'grid' ? 'line-clamp-2' : 'line-clamp-2']"
                        >
                            {{ article.title }}
                        </span>

                        <p v-if="article.description" class="line-clamp-2 text-sm text-muted-foreground">
                            {{ stripHtml(article.description) }}
                        </p>

                        <div class="mt-auto flex flex-wrap items-center justify-between gap-2 pt-2 text-xs text-muted-foreground">
                            <div class="flex items-center gap-3">
                                <span class="flex items-center gap-1.5">
                                    <img
                                        v-if="article.feed?.favicon_url"
                                        :src="article.feed.favicon_url"
                                        referrerpolicy="no-referrer"
                                        class="h-3.5 w-3.5 rounded-sm object-contain"
                                        @error="($event.target as HTMLImageElement).style.display = 'none'"
                                        alt=""
                                    />
                                    <Rss v-else class="h-3 w-3" />
                                    {{ article.feed?.name }}
                                </span>
                                <span v-if="article.published_at">{{ formatDate(article.published_at) }}</span>
                            </div>
                            <!-- Action buttons — stop propagation so they don't open the modal -->
                            <div class="flex items-center gap-2">
                                <a
                                    :href="article.link"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="flex items-center gap-1 hover:text-foreground"
                                    :title="t('general.read_more')"
                                    @click.stop
                                >
                                    <ExternalLink class="h-4 w-4" />
                                </a>
                                <button
                                    @click.stop="toggleSave(article)"
                                    :disabled="isSaving(article.id)"
                                    class="flex items-center gap-1 transition-colors hover:text-primary"
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

        <!-- Article reader modal — always shown on card click -->
        <Teleport to="body">
            <div
                v-if="readerArticle"
                class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/60 backdrop-blur-sm p-4 md:p-8"
                @click.self="readerArticle = null"
            >
                <div class="relative w-full max-w-2xl rounded-xl border border-border bg-background shadow-2xl">
                    <!-- Modal header -->
                    <div class="sticky top-0 flex items-start justify-between gap-4 rounded-t-xl border-b border-border bg-background/95 px-5 py-4 backdrop-blur-sm">
                        <div class="min-w-0">
                            <h2 class="font-bold text-base leading-snug">{{ readerArticle.title }}</h2>
                            <div class="mt-1 flex flex-wrap gap-3 text-xs text-muted-foreground">
                                <span class="flex items-center gap-1.5">
                                    <img
                                        v-if="readerArticle.feed?.favicon_url"
                                        :src="readerArticle.feed.favicon_url"
                                        referrerpolicy="no-referrer"
                                        class="h-3.5 w-3.5 rounded-sm object-contain"
                                        @error="($event.target as HTMLImageElement).style.display = 'none'"
                                        alt=""
                                    />
                                    <Rss v-else class="h-3 w-3" />
                                    {{ readerArticle.feed?.name }}
                                </span>
                                <span v-if="readerArticle.published_at">{{ formatDate(readerArticle.published_at) }}</span>
                                <span v-if="readerArticle.author">{{ t('news.by') }} {{ readerArticle.author }}</span>
                            </div>
                        </div>
                        <button
                            @click="readerArticle = null"
                            class="flex-shrink-0 rounded-lg p-1.5 hover:bg-muted"
                            :title="t('general.close')"
                        >
                            <X class="h-5 w-5" />
                        </button>
                    </div>

                    <!-- Modal body -->
                    <div class="px-5 py-5">
                        <img
                            v-if="readerArticle.thumbnail_url"
                            :src="readerArticle.thumbnail_url"
                            :alt="readerArticle.title"
                            referrerpolicy="no-referrer"
                            class="mb-4 max-h-56 w-full rounded-lg object-cover"
                            @error="($event.target as HTMLImageElement).style.display = 'none'"
                        />
                        <!-- eslint-disable vue/no-v-html -->
                        <div
                            v-if="readerArticle.full_body"
                            class="prose prose-sm max-w-none dark:prose-invert"
                            v-html="readerArticle.full_body"
                        />
                        <p
                            v-else-if="readerArticle.description"
                            class="text-sm leading-relaxed text-foreground"
                        >
                            {{ stripHtml(readerArticle.description) }}
                        </p>
                        <p v-else class="text-sm text-muted-foreground">No preview available.</p>
                    </div>

                    <!-- Modal footer -->
                    <div class="flex items-center justify-between gap-3 rounded-b-xl border-t border-border px-5 py-3">
                        <button
                            @click="toggleSave(readerArticle)"
                            :disabled="isSaving(readerArticle.id)"
                            class="flex items-center gap-1.5 text-sm transition-colors hover:text-primary"
                        >
                            <BookmarkCheck v-if="savedIds.has(readerArticle.id)" class="h-4 w-4 text-primary" />
                            <Bookmark v-else class="h-4 w-4" />
                            {{ savedIds.has(readerArticle.id) ? t('general.remove_saved') : t('general.read_later') }}
                        </button>
                        <a
                            :href="readerArticle.link"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="flex items-center gap-1.5 text-sm text-primary hover:underline"
                        >
                            <ExternalLink class="h-4 w-4" />
                            {{ t('general.read_more') }}
                        </a>
                    </div>
                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>
