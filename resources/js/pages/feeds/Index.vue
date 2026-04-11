<script setup lang="ts">
import { stripHtml, useArticleContent } from '@/composables/useArticleContent';
import { useFeedNotifications } from '@/composables/useFeedNotifications';
import { useFullBody } from '@/composables/useFullBody';
import { useNewsSearch } from '@/composables/useNewsSearch';
import { useReadStatus } from '@/composables/useReadStatus';
import { useSavedArticles } from '@/composables/useSavedArticles';
import { useViewPreferences } from '@/composables/useViewPreferences';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import {
    AlignJustify,
    BookOpen,
    Bookmark,
    BookmarkCheck,
    CheckCheck,
    ChevronLeft,
    ChevronRight,
    Code2,
    ExternalLink,
    FileText,
    LayoutGrid,
    LayoutList,
    Loader2,
    Mail,
    MailOpen,
    RefreshCw,
    Rss,
    Search,
    X,
} from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
const page = usePage();
const { save, unsave, isSaving } = useSavedArticles();
const { feedUpdated, resetNotification } = useFeedNotifications();
const { readIds, markAllSignal, markAsRead, markAllAsRead, addReads, populateFromArticles } = useReadStatus();
const {
    searchQuery,
    searchResults,
    isSearching,
    isLoadingMoreSearch,
    hasSearched,
    searchHasMore,
    performSearch,
    loadMoreSearchResults,
    clearSearch,
} = useNewsSearch();
const { viewMode, gridColumns, gridClass } = useViewPreferences();
const { rawMode, getArticleContent, hasContent } = useArticleContent();
const { fetchFullBody, getCachedBody } = useFullBody();

const isSearchMode = computed(() => searchQuery.value.length >= 2);

const props = defineProps<{
    selectedFeedId?: number | null;
    selectedCategoryId?: number | null;
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: t('nav.feeds'), href: '/feeds' }];

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------
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
    published_at: string | null;
    author: string | null;
    is_read: boolean;
    feed: FeedMeta;
}

interface SidebarEntry {
    id: number;
    name: string;
}

// ---------------------------------------------------------------------------
// State
// ---------------------------------------------------------------------------
const showUnreadOnly = ref(false);

const articles = ref<Article[]>([]);
const loading = ref(true);
const loadingMore = ref(false);
const nextCursor = ref<string | null>(null);
const hasMore = computed(() => nextCursor.value !== null);

const savedIds = ref<Set<number>>(new Set());
const readerArticle = ref<Article | null>(null);
const selectedFeedId = ref<number | null>(props.selectedFeedId ?? null);
const selectedCategoryId = ref<number | null>(props.selectedCategoryId ?? null);

// Sidebar data for title resolution
const sidebarFeeds = computed(() => ((page.props as any).sidebarFeeds as SidebarEntry[]) ?? []);
const sidebarCategories = computed(() => ((page.props as any).sidebarCategories as SidebarEntry[]) ?? []);

const pageTitle = computed<string>(() => {
    if (selectedFeedId.value) {
        return sidebarFeeds.value.find((f) => f.id === selectedFeedId.value)?.name ?? t('news.all_articles');
    }
    if (selectedCategoryId.value) {
        return sidebarCategories.value.find((c) => c.id === selectedCategoryId.value)?.name ?? t('news.all_articles');
    }
    return t('news.all_articles');
});

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function formatDate(dateStr: string | null): string {
    if (!dateStr) return '';
    return new Date(dateStr).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
}

function groupLabel(dateStr: string | null): string {
    if (!dateStr) return t('news.unknown_date');
    const d = new Date(dateStr);
    const today = new Date();
    const yesterday = new Date(today);
    yesterday.setDate(today.getDate() - 1);
    if (d.toDateString() === today.toDateString()) return t('news.today');
    if (d.toDateString() === yesterday.toDateString()) return t('news.yesterday');
    return d.toLocaleDateString(undefined, { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
}

// ---------------------------------------------------------------------------
// Grouped articles for display
// ---------------------------------------------------------------------------
const groupedArticles = computed(() => {
    const groups = new Map<string, Article[]>();
    for (const article of articles.value) {
        const key = article.published_at ? new Date(article.published_at).toDateString() : '__unknown__';
        if (!groups.has(key)) groups.set(key, []);
        groups.get(key)!.push(article);
    }
    return Array.from(groups.entries()).map(([key, items]) => ({
        key,
        label: groupLabel(items[0].published_at),
        items,
    }));
});

// ---------------------------------------------------------------------------
// Data fetching
// ---------------------------------------------------------------------------
async function loadArticles(reset = true): Promise<void> {
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
        if (selectedFeedId.value !== null) params.feed_id = selectedFeedId.value;
        if (selectedCategoryId.value !== null) params.category_id = selectedCategoryId.value;
        if (showUnreadOnly.value) params.unread_only = 1;

        const res = await axios.get('/api/news', { params });
        const newArticles: Article[] = res.data.data;

        if (reset) {
            articles.value = newArticles;
        } else {
            articles.value.push(...newArticles);
        }

        nextCursor.value = res.data.meta?.next_cursor ?? null;
        populateFromArticles(newArticles);
    } finally {
        loading.value = false;
        loadingMore.value = false;
    }
}

// ---------------------------------------------------------------------------
// Prop watchers — reset on filter change
// ---------------------------------------------------------------------------
watch(
    () => props.selectedFeedId,
    (val) => {
        selectedFeedId.value = val ?? null;
        selectedCategoryId.value = null;
        loadArticles(true);
    },
);

watch(
    () => props.selectedCategoryId,
    (val) => {
        selectedCategoryId.value = val ?? null;
        selectedFeedId.value = null;
        loadArticles(true);
    },
);

watch(showUnreadOnly, () => loadArticles(true));

// Trigger debounced search whenever the query, active feed, or active category changes
watch([searchQuery, selectedCategoryId, selectedFeedId], ([q, catId, feedId]) => {
    performSearch(q, catId, feedId);
});

// ---------------------------------------------------------------------------
// Mark-all signal from sidebar
// ---------------------------------------------------------------------------
watch(markAllSignal, (sig) => {
    if (!sig) return;
    addReads(articles.value.map((a) => a.id));
    if (showUnreadOnly.value) {
        loadArticles(true);
    }
});

// ---------------------------------------------------------------------------
// Read / Save actions
// ---------------------------------------------------------------------------
async function toggleSave(article: Article): Promise<void> {
    if (savedIds.value.has(article.id)) {
        await unsave(article.id);
        savedIds.value.delete(article.id);
    } else {
        await save(article.id);
        savedIds.value.add(article.id);
    }
}

async function markAllVisible(): Promise<void> {
    await markAllAsRead({
        feedId: selectedFeedId.value ?? undefined,
        categoryId: selectedCategoryId.value ?? undefined,
    });
    addReads(articles.value.map((a) => a.id));
    if (showUnreadOnly.value) {
        await loadArticles(true);
    }
}

async function refresh(): Promise<void> {
    resetNotification();
    await loadArticles(true);
}

// ---------------------------------------------------------------------------
// Article reader modal
// ---------------------------------------------------------------------------
const readerIndex = computed(() => (readerArticle.value ? articles.value.findIndex((a) => a.id === readerArticle.value!.id) : -1));

// Full body toggle state — resets on each new article
const showFullBody = ref(false);
const fullBodyContent = ref<string | null | undefined>(undefined);
const loadingFullBody = ref(false);

watch(readerArticle, (article) => {
    showFullBody.value = false;
    fullBodyContent.value = article ? getCachedBody(article.id) : undefined;
});

async function toggleFullBody(): Promise<void> {
    if (!readerArticle.value) return;
    showFullBody.value = !showFullBody.value;
    if (showFullBody.value && fullBodyContent.value === undefined) {
        loadingFullBody.value = true;
        try {
            fullBodyContent.value = await fetchFullBody(readerArticle.value.id);
        } finally {
            loadingFullBody.value = false;
        }
    }
}

function openReader(article: Article): void {
    readerArticle.value = article;
    markAsRead(article.id, article.feed.id);
}

function goToPrev(): void {
    if (readerIndex.value > 0) openReader(articles.value[readerIndex.value - 1]);
}

function goToNext(): void {
    if (readerIndex.value >= 0 && readerIndex.value < articles.value.length - 1) openReader(articles.value[readerIndex.value + 1]);
}

// Keyboard navigation
function onKeyDown(e: KeyboardEvent): void {
    if (!readerArticle.value) return;
    if (e.key === 'ArrowLeft') goToPrev();
    else if (e.key === 'ArrowRight') goToNext();
    else if (e.key === 'Escape') readerArticle.value = null;
    else if (e.key === 'f' || e.key === 'F') toggleFullBody();
}

onMounted(() => {
    loadArticles(true);
    window.addEventListener('keydown', onKeyDown);
});

onUnmounted(() => {
    loadMoreObserver?.disconnect();
    window.removeEventListener('keydown', onKeyDown);
});

// ---------------------------------------------------------------------------
// Load-more auto-trigger — observe the button element itself
// ---------------------------------------------------------------------------
const loadMoreBtn = ref<HTMLElement | null>(null);
let loadMoreObserver: IntersectionObserver | null = null;

function setupLoadMoreObserver(): void {
    loadMoreObserver?.disconnect();
    if (!loadMoreBtn.value) return;
    loadMoreObserver = new IntersectionObserver(
        (entries) => {
            if (entries[0].isIntersecting && hasMore.value && !loadingMore.value && !loading.value) {
                loadArticles(false);
            }
        },
        { rootMargin: '100px' },
    );
    loadMoreObserver.observe(loadMoreBtn.value);
}

// Re-attach whenever the button mounts / unmounts (driven by v-if)
watch(loadMoreBtn, (btn) => {
    if (btn) {
        setupLoadMoreObserver();
    } else {
        loadMoreObserver?.disconnect();
    }
});
</script>

<template>
    <Head :title="pageTitle" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <!-- ── Sticky header ──────────────────────────────────────────────── -->
        <div
            class="sticky top-0 z-10 flex shrink-0 flex-wrap items-center gap-x-3 gap-y-2 border-b border-border bg-background/95 px-4 py-3 backdrop-blur-sm md:flex-nowrap md:px-6"
        >
            <!-- Title: always the first item -->
            <h1 class="order-1 shrink-0 truncate text-xl font-bold">{{ pageTitle }}</h1>

            <!-- Actions: sit right of title on mobile (order-2 + ml-auto), end of row on md+ (order-3) -->
            <div class="order-2 ml-auto flex shrink-0 items-center gap-1.5 md:order-3 md:ml-0">
                <!-- Show unread only / Show all toggle -->
                <button
                    @click="showUnreadOnly = !showUnreadOnly"
                    :class="[
                        'cursor-pointer rounded-lg p-1.5 transition-colors',
                        showUnreadOnly
                            ? 'bg-primary/10 text-primary hover:bg-primary/20'
                            : 'text-muted-foreground hover:bg-muted hover:text-foreground',
                    ]"
                    :title="showUnreadOnly ? t('general.show_all') : t('general.show_unread_only')"
                >
                    <MailOpen v-if="showUnreadOnly" class="h-4 w-4" />
                    <Mail v-else class="h-4 w-4" />
                </button>

                <!-- Mark all as read -->
                <button
                    @click="markAllVisible"
                    class="cursor-pointer rounded-lg p-1.5 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                    :title="t('general.mark_all_read')"
                >
                    <CheckCheck class="h-4 w-4" />
                </button>

                <!-- View-mode toggle -->
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

                    <!-- Column count — only visible in grid mode, numbers hidden on mobile portrait -->
                    <template v-if="viewMode === 'grid'">
                        <div class="mx-1 hidden h-4 w-px shrink-0 bg-border sm:block" />
                        <button
                            v-for="col in [2, 3, 4]"
                            :key="col"
                            @click="gridColumns = col"
                            :class="[
                                'hidden min-w-6 cursor-pointer rounded p-1.5 text-xs font-semibold transition-colors sm:inline-flex',
                                gridColumns === col ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:bg-muted',
                            ]"
                            :title="`${col} ${t('general.columns')}`"
                        >
                            {{ col }}
                        </button>
                    </template>
                </div>
            </div>

            <!-- Search: full-width below title on mobile/tablet portrait, inline on md+ -->
            <div class="relative order-3 flex w-full min-w-0 items-center md:order-2 md:max-w-xs md:flex-1">
                <Search class="pointer-events-none absolute left-2.5 h-4 w-4 text-muted-foreground" />
                <input
                    v-model="searchQuery"
                    type="text"
                    :placeholder="t('news.search_placeholder')"
                    class="h-8 w-full rounded-lg border border-border bg-muted/40 pr-8 pl-8 text-sm text-foreground placeholder:text-muted-foreground focus:border-primary focus:bg-background focus:ring-1 focus:ring-primary focus:outline-none"
                />
                <button
                    v-if="searchQuery"
                    @click="clearSearch"
                    class="absolute right-2 cursor-pointer text-muted-foreground hover:text-foreground"
                    :title="t('general.clear_search')"
                >
                    <X class="h-3.5 w-3.5" />
                </button>
            </div>
        </div>

        <!-- ── Scrollable content area ──────────────────────────────────── -->
        <div class="min-h-0 flex-1 overflow-y-auto">
            <!-- Feed-updated banner -->
            <div
                v-if="feedUpdated"
                class="mx-4 mt-4 flex items-center justify-between rounded-lg bg-blue-50 px-4 py-3 text-sm text-blue-800 md:mx-6 dark:bg-blue-900/30 dark:text-blue-200"
            >
                <span>{{ t('news.new_available') }}</span>
                <button
                    @click="refresh"
                    class="ml-4 flex cursor-pointer items-center gap-1 rounded bg-blue-600 px-3 py-1 text-white hover:bg-blue-700"
                >
                    <RefreshCw class="h-4 w-4" /> {{ t('general.refresh') }}
                </button>
            </div>

            <!-- Main content -->
            <div class="px-4 py-4 md:px-6">
                <!-- ── Search results ──────────────────────────────────────── -->
                <template v-if="isSearchMode">
                    <div v-if="isSearching" class="py-12 text-center text-muted-foreground">{{ t('news.searching') }}</div>

                    <div v-else-if="hasSearched && searchResults.length === 0" class="py-12 text-center text-muted-foreground">
                        <Search class="mx-auto mb-3 h-10 w-10 opacity-40" />
                        <p>{{ t('news.no_search_results') }}</p>
                    </div>

                    <ul v-else-if="searchResults.length > 0" :class="viewMode === 'grid' ? gridClass : 'space-y-3'">
                        <li
                            v-for="article in searchResults"
                            :key="article.id"
                            @click="openReader(article)"
                            :class="[
                                'cursor-pointer rounded-xl border border-border bg-card shadow-sm transition hover:shadow-md',
                                viewMode === 'grid' ? 'flex flex-col overflow-hidden' : 'flex gap-4 p-4',
                            ]"
                        >
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
                                        v-if="article.feed?.favicon_url"
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
                                <span
                                    :class="[
                                        'line-clamp-2 leading-snug font-semibold transition-colors',
                                        readIds.has(article.id) ? 'text-muted-foreground' : '',
                                    ]"
                                >
                                    {{ article.title }}
                                </span>
                                <p v-if="article.description" class="line-clamp-2 text-sm text-muted-foreground">
                                    {{ stripHtml(article.description) }}
                                </p>
                                <div class="mt-auto flex flex-wrap items-center justify-between gap-2 pt-2 text-xs text-muted-foreground">
                                    <div class="flex min-w-0 items-center gap-3">
                                        <span class="flex min-w-0 items-center gap-1.5">
                                            <img
                                                v-if="article.feed?.favicon_url"
                                                :src="article.feed.favicon_url"
                                                referrerpolicy="no-referrer"
                                                class="h-3.5 w-3.5 shrink-0 rounded-sm object-contain"
                                                @error="($event.target as HTMLImageElement).style.display = 'none'"
                                                alt=""
                                            />
                                            <Rss v-else class="h-3 w-3 shrink-0" />
                                            <span :class="['truncate', viewMode === 'grid' ? 'max-w-32' : 'max-w-28 sm:max-w-none']">{{
                                                article.feed?.name
                                            }}</span>
                                        </span>
                                        <span v-if="article.published_at" class="shrink-0">{{ formatDate(article.published_at) }}</span>
                                    </div>
                                    <div class="flex shrink-0 items-center gap-2">
                                        <a
                                            :href="article.link"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="flex cursor-pointer items-center gap-1 hover:text-foreground"
                                            :title="t('general.read_more')"
                                            @click.stop
                                        >
                                            <ExternalLink class="h-4 w-4" />
                                        </a>
                                        <button
                                            @click.stop="toggleSave(article)"
                                            :disabled="isSaving(article.id)"
                                            class="flex cursor-pointer items-center gap-1 transition-colors hover:text-primary disabled:cursor-not-allowed"
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

                    <!-- Search skeleton while loading more -->
                    <div v-if="isLoadingMoreSearch" :class="['mt-4', viewMode === 'grid' ? gridClass : 'space-y-3']">
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

                    <!-- Search Load More -->
                    <div v-if="searchHasMore && !isSearching && !isLoadingMoreSearch" class="mt-6 flex justify-center">
                        <button
                            @click="loadMoreSearchResults()"
                            class="cursor-pointer rounded-lg border border-border bg-card px-6 py-2.5 text-sm font-medium text-muted-foreground shadow-sm transition hover:bg-muted hover:text-foreground"
                        >
                            {{ t('general.load_more') }}
                        </button>
                    </div>
                </template>
                <!-- end search results -->

                <!-- ── Regular feed ────────────────────────────────────────── -->
                <template v-else>
                    <div v-if="loading" class="py-12 text-center text-muted-foreground">{{ t('general.loading') }}</div>

                    <div v-else-if="articles.length === 0" class="py-12 text-center text-muted-foreground">
                        <Rss class="mx-auto mb-3 h-10 w-10 opacity-40" />
                        <p>{{ t('general.no_results') }}</p>
                    </div>

                    <!-- Article groups by date -->
                    <template v-else v-for="group in groupedArticles" :key="group.key">
                        <h2 class="mt-6 mb-3 text-xs font-semibold tracking-wider text-muted-foreground uppercase first:mt-0">
                            {{ group.label }}
                        </h2>

                        <ul :class="viewMode === 'grid' ? gridClass : 'space-y-3'">
                            <li
                                v-for="article in group.items"
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
                                            v-if="article.feed?.favicon_url"
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

                                <!-- Content -->
                                <div :class="['flex min-w-0 flex-1 flex-col gap-1', viewMode === 'grid' ? 'p-4' : '']">
                                    <span
                                        :class="[
                                            'line-clamp-2 leading-snug font-semibold transition-colors',
                                            readIds.has(article.id) ? 'text-muted-foreground' : '',
                                        ]"
                                    >
                                        {{ article.title }}
                                    </span>
                                    <p v-if="article.description" class="line-clamp-2 text-sm text-muted-foreground">
                                        {{ stripHtml(article.description) }}
                                    </p>
                                    <div class="mt-auto flex flex-wrap items-center justify-between gap-2 pt-2 text-xs text-muted-foreground">
                                        <div class="flex min-w-0 items-center gap-3">
                                            <span class="flex min-w-0 items-center gap-1.5">
                                                <img
                                                    v-if="article.feed?.favicon_url"
                                                    :src="article.feed.favicon_url"
                                                    referrerpolicy="no-referrer"
                                                    class="h-3.5 w-3.5 shrink-0 rounded-sm object-contain"
                                                    @error="($event.target as HTMLImageElement).style.display = 'none'"
                                                    alt=""
                                                />
                                                <Rss v-else class="h-3 w-3 shrink-0" />
                                                <span :class="['truncate', viewMode === 'grid' ? 'max-w-32' : 'max-w-28 sm:max-w-none']">{{
                                                    article.feed?.name
                                                }}</span>
                                            </span>
                                            <span v-if="article.published_at" class="shrink-0">{{ formatDate(article.published_at) }}</span>
                                        </div>
                                        <div class="flex shrink-0 items-center gap-2">
                                            <a
                                                :href="article.link"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                class="flex cursor-pointer items-center gap-1 hover:text-foreground"
                                                :title="t('general.read_more')"
                                                @click.stop
                                            >
                                                <ExternalLink class="h-4 w-4" />
                                            </a>
                                            <button
                                                @click.stop="toggleSave(article)"
                                                :disabled="isSaving(article.id)"
                                                class="flex cursor-pointer items-center gap-1 transition-colors hover:text-primary disabled:cursor-not-allowed"
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
                    </template>

                    <!-- Skeleton cards while loading more -->
                    <div v-if="loadingMore" :class="['mt-4', viewMode === 'grid' ? gridClass : 'space-y-3']">
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

                    <!-- Load More button -->
                    <div ref="loadMoreBtn" v-if="hasMore && !loadingMore && !loading" class="mt-6 flex justify-center">
                        <button
                            @click="loadArticles(false)"
                            class="cursor-pointer rounded-lg border border-border bg-card px-6 py-2.5 text-sm font-medium text-muted-foreground shadow-sm transition hover:bg-muted hover:text-foreground"
                        >
                            {{ t('general.load_more') }}
                        </button>
                    </div>
                </template>
                <!-- end regular feed -->
            </div>
        </div>

        <!-- ── Article reader modal (gallery style) ──────────────────────── -->
        <Teleport to="body">
            <div
                v-if="readerArticle"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-3 backdrop-blur-sm md:p-6"
                @click.self="readerArticle = null"
            >
                <div class="flex w-full max-w-3xl items-center gap-2 md:gap-3">
                    <!-- Prev arrow -->
                    <button
                        @click.stop="goToPrev"
                        :disabled="readerIndex <= 0"
                        class="shrink-0 cursor-pointer rounded-full bg-background/90 p-2.5 shadow-lg backdrop-blur-sm transition hover:bg-background disabled:cursor-not-allowed disabled:opacity-30"
                        :title="t('general.previous')"
                    >
                        <ChevronLeft class="h-5 w-5" />
                    </button>

                    <!-- Modal panel -->
                    <div class="max-h-[90vh] min-w-0 flex-1 overflow-y-auto rounded-xl border border-border bg-background shadow-2xl" @click.stop>
                        <!-- Modal header -->
                        <div
                            class="sticky top-0 flex items-start justify-between gap-4 rounded-t-xl border-b border-border bg-background/95 px-5 py-4 backdrop-blur-sm"
                        >
                            <div class="min-w-0">
                                <h2 class="text-base leading-snug font-bold">
                                    <a :href="readerArticle.link" target="_blank" rel="noopener noreferrer" class="hover:underline">{{
                                        readerArticle.title
                                    }}</a>
                                </h2>
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
                            <div class="flex shrink-0 items-center gap-1">
                                <!-- Full body toggle -->
                                <button
                                    @click="toggleFullBody"
                                    :class="[
                                        'cursor-pointer rounded-lg p-1.5 transition-colors',
                                        showFullBody
                                            ? 'bg-primary/10 text-primary hover:bg-primary/20'
                                            : 'text-muted-foreground hover:bg-muted hover:text-foreground',
                                    ]"
                                    :title="showFullBody ? t('news.switch_to_description') : t('news.switch_to_full_body')"
                                >
                                    <BookOpen class="h-4 w-4" />
                                </button>
                                <!-- Raw mode toggle -->
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
                                <button
                                    @click="readerArticle = null"
                                    class="shrink-0 cursor-pointer rounded-lg p-1.5 hover:bg-muted"
                                    :title="t('general.close')"
                                >
                                    <X class="h-5 w-5" />
                                </button>
                            </div>
                        </div>

                        <!-- Modal body -->
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

                            <!-- Full body view -->
                            <template v-if="showFullBody">
                                <div v-if="loadingFullBody" class="flex items-center gap-2 text-sm text-muted-foreground">
                                    <Loader2 class="h-4 w-4 animate-spin" />
                                    {{ t('news.loading_full_body') }}
                                </div>
                                <template v-else-if="hasContent(fullBodyContent ?? null)">
                                    <!-- eslint-disable vue/no-v-html -->
                                    <div v-if="!rawMode" class="prose prose-sm max-w-none dark:prose-invert" v-html="getArticleContent(fullBodyContent ?? null)" />
                                    <div
                                        v-else
                                        class="text-sm leading-relaxed whitespace-pre-wrap text-foreground [&_a]:text-primary [&_a]:underline [&_a]:underline-offset-2"
                                        v-html="getArticleContent(fullBodyContent ?? null)"
                                    />
                                    <!-- eslint-enable vue/no-v-html -->
                                </template>
                                <p v-else class="text-sm text-muted-foreground">{{ t('news.no_full_body') }}</p>
                            </template>

                            <!-- Description view (default) -->
                            <template v-else>
                                <template v-if="hasContent(readerArticle.description)">
                                    <!-- eslint-disable vue/no-v-html -->
                                    <div v-if="!rawMode" class="prose prose-sm max-w-none dark:prose-invert" v-html="getArticleContent(readerArticle.description)" />
                                    <div
                                        v-else
                                        class="text-sm leading-relaxed whitespace-pre-wrap text-foreground [&_a]:text-primary [&_a]:underline [&_a]:underline-offset-2"
                                        v-html="getArticleContent(readerArticle.description)"
                                    />
                                    <!-- eslint-enable vue/no-v-html -->
                                </template>
                                <p v-else class="text-sm text-muted-foreground">{{ t('news.no_preview') }}</p>
                            </template>
                        </div>

                        <!-- Modal footer -->
                        <div class="flex items-center justify-between gap-3 rounded-b-xl border-t border-border px-5 py-3">
                            <button
                                @click="toggleSave(readerArticle)"
                                :disabled="isSaving(readerArticle.id)"
                                class="flex cursor-pointer items-center gap-1.5 text-sm transition-colors hover:text-primary disabled:cursor-not-allowed"
                            >
                                <BookmarkCheck v-if="savedIds.has(readerArticle.id)" class="h-4 w-4 text-primary" />
                                <Bookmark v-else class="h-4 w-4" />
                                {{ savedIds.has(readerArticle.id) ? t('general.remove_saved') : t('general.read_later') }}
                            </button>
                            <a
                                :href="readerArticle.link"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="flex cursor-pointer items-center gap-1.5 text-sm text-primary hover:underline"
                            >
                                <ExternalLink class="h-4 w-4" />
                                {{ t('general.read_more') }}
                            </a>
                        </div>
                    </div>
                    <!-- end modal panel -->

                    <!-- Next arrow -->
                    <button
                        @click.stop="goToNext"
                        :disabled="readerIndex >= articles.length - 1"
                        class="shrink-0 cursor-pointer rounded-full bg-background/90 p-2.5 shadow-lg backdrop-blur-sm transition hover:bg-background disabled:cursor-not-allowed disabled:opacity-30"
                        :title="t('general.next')"
                    >
                        <ChevronRight class="h-5 w-5" />
                    </button>
                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>
