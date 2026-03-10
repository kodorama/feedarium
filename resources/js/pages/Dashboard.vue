<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { useSavedArticles } from '@/composables/useSavedArticles';
import axios from 'axios';
import { Bookmark, BookmarkCheck, Rss, ExternalLink } from 'lucide-vue-next';

const { t } = useI18n();
const { save, unsave, isSaving } = useSavedArticles();

const breadcrumbs: BreadcrumbItem[] = [
    { title: t('nav.dashboard'), href: '/dashboard' },
];

interface Feed {
    id: number;
    name: string;
    url: string;
    active: boolean;
    last_fetched_at: string | null;
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

const feeds = ref<Feed[]>([]);
const articles = ref<Article[]>([]);
const savedIds = ref<Set<number>>(new Set());
const loading = ref(true);

onMounted(async () => {
    try {
        const [feedRes, newsRes] = await Promise.all([
            axios.get('/api/feeds'),
            axios.get('/api/news', { params: { per_page: 20 } }),
        ]);
        feeds.value = feedRes.data.data ?? feedRes.data;
        articles.value = newsRes.data.data ?? newsRes.data;
    } catch {
        // silently fail
    } finally {
        loading.value = false;
    }
});

async function toggleSave(article: Article) {
    if (savedIds.value.has(article.id)) {
        await unsave(article.id);
        savedIds.value.delete(article.id);
    } else {
        await save(article.id);
        savedIds.value.add(article.id);
    }
}
</script>

<template>
    <Head :title="t('nav.dashboard')" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4 md:p-6">
            <!-- Stats row -->
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
                <div class="rounded-xl border border-border bg-card p-4 shadow-sm">
                    <p class="text-xs text-muted-foreground uppercase tracking-wide">{{ t('feeds.title') }}</p>
                    <p class="mt-1 text-2xl font-bold">{{ feeds.length }}</p>
                </div>
                <div class="rounded-xl border border-border bg-card p-4 shadow-sm">
                    <p class="text-xs text-muted-foreground uppercase tracking-wide">{{ t('news.title') }}</p>
                    <p class="mt-1 text-2xl font-bold">{{ articles.length }}</p>
                </div>
                <div class="col-span-2 rounded-xl border border-border bg-card p-4 shadow-sm sm:col-span-1">
                    <p class="text-xs text-muted-foreground uppercase tracking-wide">Active Feeds</p>
                    <p class="mt-1 text-2xl font-bold">{{ feeds.filter(f => f.active).length }}</p>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <!-- Recent Articles -->
                <div class="lg:col-span-2">
                    <div class="mb-3 flex items-center justify-between">
                        <h2 class="text-lg font-semibold">{{ t('news.title') }}</h2>
                        <Link href="/saved" class="text-sm text-primary hover:underline">{{ t('nav.saved') }} →</Link>
                    </div>

                    <div v-if="loading" class="py-8 text-center text-muted-foreground">{{ t('general.loading') }}</div>

                    <ul v-else-if="articles.length" class="space-y-3">
                        <li
                            v-for="article in articles"
                            :key="article.id"
                            class="flex gap-3 rounded-xl border border-border bg-card p-3 shadow-sm transition hover:shadow-md"
                        >
                            <img
                                v-if="article.thumbnail_url"
                                :src="article.thumbnail_url"
                                :alt="article.title"
                                class="h-16 w-24 flex-shrink-0 rounded-lg object-cover"
                            />
                            <div class="flex flex-1 flex-col gap-1 min-w-0">
                                <a
                                    :href="article.link"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="truncate font-medium leading-snug hover:underline"
                                >
                                    {{ article.title }}
                                </a>
                                <p v-if="article.description" class="line-clamp-2 text-xs text-muted-foreground">
                                    {{ article.description }}
                                </p>
                                <div class="mt-auto flex items-center justify-between gap-2">
                                    <span class="flex items-center gap-1 text-xs text-muted-foreground">
                                        <Rss class="h-3 w-3" />
                                        {{ article.feed?.name }}
                                    </span>
                                    <button
                                        @click="toggleSave(article)"
                                        :disabled="isSaving(article.id)"
                                        class="flex items-center gap-1 text-xs text-muted-foreground hover:text-primary transition-colors"
                                        :title="savedIds.has(article.id) ? t('general.remove_saved') : t('general.read_later')"
                                    >
                                        <BookmarkCheck v-if="savedIds.has(article.id)" class="h-4 w-4 text-primary" />
                                        <Bookmark v-else class="h-4 w-4" />
                                    </button>
                                </div>
                            </div>
                        </li>
                    </ul>

                    <div v-else class="py-8 text-center text-muted-foreground">
                        <p>{{ t('general.no_results') }}</p>
                        <Link href="/feeds/create" class="mt-2 inline-block text-sm text-primary hover:underline">
                            {{ t('feeds.create') }}
                        </Link>
                    </div>
                </div>

                <!-- Feed list sidebar -->
                <div>
                    <div class="mb-3 flex items-center justify-between">
                        <h2 class="text-lg font-semibold">{{ t('feeds.title') }}</h2>
                        <Link href="/feeds" class="text-sm text-primary hover:underline">{{ t('general.edit') }} →</Link>
                    </div>

                    <ul class="space-y-2">
                        <li
                            v-for="feed in feeds.slice(0, 8)"
                            :key="feed.id"
                            class="flex items-center justify-between rounded-lg border border-border bg-card px-3 py-2"
                        >
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium">{{ feed.name }}</p>
                                <p class="truncate text-xs text-muted-foreground">{{ feed.url }}</p>
                            </div>
                            <a :href="feed.url" target="_blank" rel="noopener noreferrer" class="ml-2 flex-shrink-0">
                                <ExternalLink class="h-3 w-3 text-muted-foreground hover:text-foreground" />
                            </a>
                        </li>
                        <li v-if="feeds.length === 0" class="rounded-lg border border-border bg-card px-3 py-4 text-center text-sm text-muted-foreground">
                            <Link href="/feeds/create" class="text-primary hover:underline">{{ t('feeds.create') }}</Link>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
