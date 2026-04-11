<script setup lang="ts">
import { stripHtml, useArticleContent } from '@/composables/useArticleContent';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { AlignJustify, Bookmark, BookmarkCheck, Code2, ExternalLink, FileText, LayoutGrid, LayoutList, Rss, X } from 'lucide-vue-next';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const { rawMode, getArticleContent, hasContent } = useArticleContent();

interface FeedMeta {
    id: number;
    name: string;
    favicon_url: string | null;
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
    is_read: boolean;
    feed: FeedMeta;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: t('nav.feeds'), href: '/feeds' },
    { title: t('nav.saved'), href: '/saved' },
];

type ViewMode = 'list' | 'grid' | 'compact';
const viewMode = ref<ViewMode>('grid');

const articles = ref<Article[]>([]);
const loading = ref(true);
const loadingMore = ref(false);
const nextCursor = ref<string | null>(null);
const hasMore = computed(() => nextCursor.value !== null);

const readerArticle = ref<Article | null>(null);
const readerIndex = computed(() => (readerArticle.value ? articles.value.findIndex((a) => a.id === readerArticle.value!.id) : -1));

function formatDate(dateStr: string | null): string {
    if (!dateStr) return '';
    return new Date(dateStr).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
}

async function fetchSaved(reset = true): Promise<void> {
    if (!reset && (loadingMore.value || loading.value || !hasMore.value)) return;

    if (reset) {
        articles.value = [];
        nextCursor.value = null;
        loading.value = true;
    } else {
        loadingMore.value = true;
    }

    try {
        const params: Record<string, unknown> = { per_page: 20 };
        if (!reset && nextCursor.value) params.cursor = nextCursor.value;
        const res = await axios.get('/api/news/saved', { params });
        const newArticles: Article[] = res.data.data;
        if (reset) {
            articles.value = newArticles;
        } else {
            articles.value.push(...newArticles);
        }
        nextCursor.value = res.data.meta?.next_cursor ?? null;
    } finally {
        loading.value = false;
        loadingMore.value = false;
    }
}

async function loadMore(): Promise<void> {
    if (loadingMore.value || loading.value || !hasMore.value) return;
    scrollObserver?.disconnect();
    await fetchSaved(false);
    await nextTick();
    setupScrollObserver();
}

async function unsave(id: number): Promise<void> {
    await axios.delete(`/api/news/${id}/save`);
    articles.value = articles.value.filter((a) => a.id !== id);
    if (readerArticle.value?.id === id) readerArticle.value = null;
}

function openReader(article: Article): void {
    readerArticle.value = article;
}

function goToPrev(): void {
    if (readerIndex.value > 0) openReader(articles.value[readerIndex.value - 1]);
}

function goToNext(): void {
    if (readerIndex.value < articles.value.length - 1) openReader(articles.value[readerIndex.value + 1]);
}

function onKeyDown(e: KeyboardEvent): void {
    if (!readerArticle.value) return;
    if (e.key === 'ArrowLeft') goToPrev();
    else if (e.key === 'ArrowRight') goToNext();
    else if (e.key === 'Escape') readerArticle.value = null;
}

// Infinite scroll
const sentinel = ref<HTMLElement | null>(null);
let scrollObserver: IntersectionObserver | null = null;

function setupScrollObserver(): void {
    scrollObserver?.disconnect();
    if (!sentinel.value) return;
    scrollObserver = new IntersectionObserver(
        (entries) => {
            if (entries[0].isIntersecting) loadMore();
        },
        { rootMargin: '200px' },
    );
    scrollObserver.observe(sentinel.value);
}

watch(sentinel, () => setupScrollObserver());

onMounted(() => {
    fetchSaved(true);
    nextTick(() => setupScrollObserver());
    window.addEventListener('keydown', onKeyDown);
});

onUnmounted(() => {
    scrollObserver?.disconnect();
    window.removeEventListener('keydown', onKeyDown);
});
</script>

<template>
    <Head :title="t('news.saved_title')" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col">
            <!-- Sticky header -->
            <div
                class="sticky top-0 z-10 flex shrink-0 items-center justify-between gap-4 border-b border-border bg-background/95 px-4 py-3 backdrop-blur-sm md:px-6"
            >
                <h1 class="truncate text-xl font-bold">{{ t('news.saved_title') }}</h1>
                <div class="flex items-center gap-0.5 rounded-lg border border-border p-1">
                    <button
                        @click="viewMode = 'list'"
                        :class="[
                            'cursor-pointer rounded p-1.5 transition-colors',
                            viewMode === 'list' ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:bg-muted',
                        ]"
                        :title="t('general.list_view')"
                    >
                        <LayoutList class="h-4 w-4" />
                    </button>
                    <button
                        @click="viewMode = 'compact'"
                        :class="[
                            'cursor-pointer rounded p-1.5 transition-colors',
                            viewMode === 'compact' ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:bg-muted',
                        ]"
                        :title="t('general.compact_view')"
                    >
                        <AlignJustify class="h-4 w-4" />
                    </button>
                    <button
                        @click="viewMode = 'grid'"
                        :class="[
                            'cursor-pointer rounded p-1.5 transition-colors',
                            viewMode === 'grid' ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:bg-muted',
                        ]"
                        :title="t('general.grid_view')"
                    >
                        <LayoutGrid class="h-4 w-4" />
                    </button>
                </div>
            </div>

            <div class="flex-1 px-4 py-4 md:px-6">
                <div v-if="loading" class="py-12 text-center text-muted-foreground">{{ t('general.loading') }}</div>

                <p v-else-if="articles.length === 0" class="py-12 text-center text-muted-foreground">
                    {{ t('news.no_saved') }}
                </p>

                <!-- Article list / grid -->
                <ul v-else :class="viewMode === 'grid' ? 'grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-4' : 'space-y-3'">
                    <li
                        v-for="article in articles"
                        :key="article.id"
                        @click="openReader(article)"
                        :class="[
                            'cursor-pointer rounded-xl border border-border bg-card shadow-sm transition hover:shadow-md',
                            viewMode === 'grid' ? 'flex flex-col overflow-hidden' : 'flex gap-4 p-4',
                        ]"
                    >
                        <!-- Thumbnail or placeholder — hidden in compact mode -->
                        <template v-if="viewMode !== 'compact'">
                            <img
                                v-if="article.thumbnail_url"
                                :src="article.thumbnail_url"
                                :alt="article.title"
                                loading="lazy"
                                referrerpolicy="no-referrer"
                                :class="
                                    viewMode === 'grid'
                                        ? 'h-44 w-full shrink-0 object-cover'
                                        : 'h-20 w-32 shrink-0 rounded-lg object-cover sm:h-24 sm:w-36'
                                "
                                @error="($event.target as HTMLImageElement).style.display = 'none'"
                            />
                            <div
                                v-else
                                :class="[
                                    'flex shrink-0 items-center justify-center bg-linear-to-br from-muted/60 to-muted',
                                    viewMode === 'grid' ? 'h-44 w-full' : 'h-20 w-32 rounded-lg sm:h-24 sm:w-36',
                                ]"
                            >
                                <img
                                    v-if="article.feed.favicon_url"
                                    :src="article.feed.favicon_url"
                                    loading="lazy"
                                    referrerpolicy="no-referrer"
                                    class="h-8 w-8 rounded-sm object-contain opacity-40"
                                    alt=""
                                    @error="($event.target as HTMLImageElement).style.display = 'none'"
                                />
                                <Rss v-else class="h-8 w-8 text-muted-foreground/30" />
                            </div>
                        </template>
                        <div :class="['flex min-w-0 flex-1 flex-col gap-1', viewMode === 'grid' ? 'p-4' : '']">
                            <span class="line-clamp-2 leading-snug font-semibold">{{ article.title }}</span>
                            <p v-if="article.description" class="line-clamp-2 text-sm text-muted-foreground">
                                {{ stripHtml(article.description) }}
                            </p>
                            <div class="mt-auto flex items-center justify-between gap-2 pt-2 text-xs text-muted-foreground">
                                <span class="flex min-w-0 items-center gap-1.5">
                                    <img
                                        v-if="article.feed.favicon_url"
                                        :src="article.feed.favicon_url"
                                        referrerpolicy="no-referrer"
                                        class="h-3.5 w-3.5 shrink-0 rounded-sm object-contain"
                                        @error="($event.target as HTMLImageElement).style.display = 'none'"
                                        alt=""
                                    />
                                    <Rss v-else class="h-3 w-3 shrink-0" />
                                    <span :class="['truncate', viewMode === 'grid' ? 'max-w-32' : 'max-w-28 sm:max-w-none']">{{
                                        article.feed.name
                                    }}</span>
                                </span>
                                <div class="flex items-center gap-2">
                                    <span v-if="article.published_at">{{ formatDate(article.published_at) }}</span>
                                    <a
                                        :href="article.link"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="hover:text-foreground"
                                        :title="t('general.read_more')"
                                        @click.stop
                                    >
                                        <ExternalLink class="h-4 w-4" />
                                    </a>
                                    <button
                                        @click.stop="unsave(article.id)"
                                        class="cursor-pointer text-destructive hover:text-destructive/80"
                                        :title="t('general.remove_saved')"
                                    >
                                        <BookmarkCheck class="h-4 w-4" />
                                    </button>
                                </div>
                            </div>
                        </div>
                    </li>
                </ul>

                <!-- Infinite-scroll sentinel -->
                <div ref="sentinel" class="h-4" />

                <!-- Skeleton cards while loading more -->
                <div
                    v-if="loadingMore"
                    :class="viewMode === 'grid' ? 'grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-4' : 'space-y-3'"
                >
                    <div
                        v-for="n in 4"
                        :key="n"
                        :class="[
                            'animate-pulse overflow-hidden rounded-xl border border-border bg-card',
                            viewMode !== 'grid' ? 'flex gap-4 p-4' : '',
                        ]"
                    >
                        <div
                            v-if="viewMode !== 'compact'"
                            :class="viewMode === 'grid' ? 'h-40 w-full bg-muted' : 'h-20 w-32 shrink-0 rounded-lg bg-muted'"
                        />
                        <div :class="['space-y-2', viewMode === 'grid' ? 'p-4' : 'flex-1']">
                            <div class="h-4 w-3/4 rounded bg-muted" />
                            <div class="h-3 w-full rounded bg-muted" />
                            <div class="h-3 w-1/2 rounded bg-muted" />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Article reader modal (gallery style) -->
        <Teleport to="body">
            <div
                v-if="readerArticle"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-3 backdrop-blur-sm md:p-6"
                @click.self="readerArticle = null"
            >
                <div class="flex w-full max-w-3xl items-center gap-2 md:gap-3">
                    <button
                        @click.stop="goToPrev"
                        :disabled="readerIndex <= 0"
                        class="shrink-0 cursor-pointer rounded-full bg-background/90 p-2.5 shadow-lg backdrop-blur-sm transition hover:bg-background disabled:cursor-not-allowed disabled:opacity-30"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            class="h-5 w-5"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                        >
                            <path d="m15 18-6-6 6-6" />
                        </svg>
                    </button>

                    <div class="max-h-[90vh] min-w-0 flex-1 overflow-y-auto rounded-xl border border-border bg-background shadow-2xl" @click.stop>
                        <!-- Header -->
                        <div
                            class="sticky top-0 flex items-start justify-between gap-4 rounded-t-xl border-b border-border bg-background/95 px-5 py-4 backdrop-blur-sm"
                        >
                            <div class="min-w-0">
                                <h2 class="text-base leading-snug font-bold">{{ readerArticle.title }}</h2>
                                <div class="mt-1 flex flex-wrap gap-3 text-xs text-muted-foreground">
                                    <span class="flex items-center gap-1.5">
                                        <img
                                            v-if="readerArticle.feed.favicon_url"
                                            :src="readerArticle.feed.favicon_url"
                                            referrerpolicy="no-referrer"
                                            class="h-3.5 w-3.5 rounded-sm object-contain"
                                            @error="($event.target as HTMLImageElement).style.display = 'none'"
                                            alt=""
                                        />
                                        <Rss v-else class="h-3 w-3" />
                                        {{ readerArticle.feed.name }}
                                    </span>
                                    <span v-if="readerArticle.published_at">{{ formatDate(readerArticle.published_at) }}</span>
                                    <span v-if="readerArticle.author">{{ readerArticle.author }}</span>
                                </div>
                            </div>
                            <div class="flex shrink-0 items-center gap-1">
                                <button
                                    @click="rawMode = !rawMode"
                                    :class="[
                                        'cursor-pointer rounded-lg p-1.5 transition-colors',
                                        rawMode
                                            ? 'bg-primary/10 text-primary hover:bg-primary/20'
                                            : 'text-muted-foreground hover:bg-muted hover:text-foreground',
                                    ]"
                                    :title="rawMode ? t('news.switch_to_formatted') : t('news.switch_to_raw')"
                                >
                                    <Code2 v-if="rawMode" class="h-4 w-4" />
                                    <FileText v-else class="h-4 w-4" />
                                </button>
                                <button @click="readerArticle = null" class="shrink-0 cursor-pointer rounded-lg p-1.5 hover:bg-muted">
                                    <X class="h-5 w-5" />
                                </button>
                            </div>
                        </div>
                        <!-- Body -->
                        <div class="px-5 py-5">
                            <img
                                v-if="readerArticle.thumbnail_url"
                                :src="readerArticle.thumbnail_url"
                                :alt="readerArticle.title"
                                loading="lazy"
                                referrerpolicy="no-referrer"
                                class="mb-4 max-h-56 w-full rounded-lg object-cover"
                                @error="($event.target as HTMLImageElement).style.display = 'none'"
                            />
                            <template v-if="hasContent(readerArticle)">
                                <!-- eslint-disable vue/no-v-html -->
                                <div v-if="!rawMode" class="prose prose-sm max-w-none dark:prose-invert" v-html="getArticleContent(readerArticle)" />
                                <div
                                    v-else
                                    class="text-sm leading-relaxed whitespace-pre-wrap text-foreground [&_a]:text-primary [&_a]:underline [&_a]:underline-offset-2"
                                    v-html="getArticleContent(readerArticle)"
                                />
                                <!-- eslint-enable vue/no-v-html -->
                            </template>
                            <p v-else class="text-sm text-muted-foreground">{{ t('news.no_preview') }}</p>
                        </div>
                        <!-- Footer -->
                        <div class="flex items-center justify-between gap-3 rounded-b-xl border-t border-border px-5 py-3">
                            <button
                                @click="unsave(readerArticle.id)"
                                class="flex cursor-pointer items-center gap-1.5 text-sm text-destructive hover:text-destructive/80"
                            >
                                <Bookmark class="h-4 w-4" />
                                {{ t('general.remove_saved') }}
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

                    <button
                        @click.stop="goToNext"
                        :disabled="readerIndex >= articles.length - 1"
                        class="shrink-0 cursor-pointer rounded-full bg-background/90 p-2.5 shadow-lg backdrop-blur-sm transition hover:bg-background disabled:cursor-not-allowed disabled:opacity-30"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            class="h-5 w-5"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                        >
                            <path d="m9 18 6-6-6-6" />
                        </svg>
                    </button>
                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>
