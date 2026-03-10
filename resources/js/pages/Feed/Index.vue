<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import axios from 'axios';
import { Pencil, Trash2, ToggleLeft, ToggleRight, Plus, Rss } from 'lucide-vue-next';

const { t } = useI18n();

const breadcrumbs: BreadcrumbItem[] = [
    { title: t('nav.dashboard'), href: '/dashboard' },
    { title: t('feeds.title'), href: '/feeds' },
];

interface Category {
    id: number;
    name: string;
}

interface Feed {
    id: number;
    name: string;
    url: string;
    description: string | null;
    active: boolean;
    category: Category | null;
    last_fetched_at: string | null;
}

const feeds = ref<Feed[]>([]);
const loading = ref(true);
const deleting = ref<number | null>(null);
const toggling = ref<number | null>(null);

async function loadFeeds() {
    loading.value = true;
    try {
        const res = await axios.get('/api/feeds');
        feeds.value = res.data.data ?? res.data;
    } finally {
        loading.value = false;
    }
}

async function deleteFeed(id: number) {
    if (!confirm(t('general.confirm_delete'))) return;
    deleting.value = id;
    try {
        await axios.delete(`/api/feeds/${id}`);
        feeds.value = feeds.value.filter((f) => f.id !== id);
    } finally {
        deleting.value = null;
    }
}

async function toggleFeed(feed: Feed) {
    toggling.value = feed.id;
    try {
        await axios.patch(`/api/feeds/${feed.id}/toggle`);
        feed.active = !feed.active;
    } finally {
        toggling.value = null;
    }
}

onMounted(loadFeeds);
</script>

<template>
    <Head :title="t('feeds.title')" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-4 md:p-6">
            <div class="mb-6 flex items-center justify-between">
                <h1 class="text-2xl font-bold">{{ t('feeds.title') }}</h1>
                <Link
                    href="/feeds/create"
                    class="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90"
                >
                    <Plus class="h-4 w-4" />
                    {{ t('feeds.create') }}
                </Link>
            </div>

            <div v-if="loading" class="py-12 text-center text-muted-foreground">{{ t('general.loading') }}</div>

            <div v-else-if="feeds.length === 0" class="py-12 text-center text-muted-foreground">
                <Rss class="mx-auto mb-3 h-10 w-10 opacity-40" />
                <p>{{ t('general.no_results') }}</p>
                <Link href="/feeds/create" class="mt-2 inline-block text-sm text-primary hover:underline">
                    {{ t('feeds.create') }}
                </Link>
            </div>

            <!-- Desktop table -->
            <div v-else class="hidden sm:block overflow-x-auto rounded-xl border border-border">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-xs uppercase tracking-wide text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3 text-left">{{ t('feeds.name') }}</th>
                            <th class="px-4 py-3 text-left">{{ t('feeds.url') }}</th>
                            <th class="px-4 py-3 text-left">{{ t('feeds.category') }}</th>
                            <th class="px-4 py-3 text-left">{{ t('feeds.active') }}</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border bg-card">
                        <tr v-for="feed in feeds" :key="feed.id" class="hover:bg-muted/30">
                            <td class="px-4 py-3 font-medium">{{ feed.name }}</td>
                            <td class="max-w-xs px-4 py-3">
                                <a :href="feed.url" target="_blank" rel="noopener noreferrer" class="truncate text-primary hover:underline block">
                                    {{ feed.url }}
                                </a>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">{{ feed.category?.name ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <button
                                    @click="toggleFeed(feed)"
                                    :disabled="toggling === feed.id"
                                    class="transition-opacity hover:opacity-70"
                                    :title="feed.active ? 'Deactivate' : 'Activate'"
                                >
                                    <ToggleRight v-if="feed.active" class="h-5 w-5 text-green-500" />
                                    <ToggleLeft v-else class="h-5 w-5 text-muted-foreground" />
                                </button>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-2">
                                    <Link :href="`/feeds/${feed.id}/edit`" class="rounded p-1 hover:bg-accent">
                                        <Pencil class="h-4 w-4" />
                                    </Link>
                                    <button
                                        @click="deleteFeed(feed.id)"
                                        :disabled="deleting === feed.id"
                                        class="rounded p-1 text-destructive hover:bg-destructive/10"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Mobile cards -->
            <ul v-if="feeds.length > 0" class="space-y-3 sm:hidden">
                <li
                    v-for="feed in feeds"
                    :key="feed.id"
                    class="rounded-xl border border-border bg-card p-4 shadow-sm"
                >
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <p class="font-semibold">{{ feed.name }}</p>
                            <a :href="feed.url" target="_blank" rel="noopener noreferrer" class="block truncate text-xs text-primary hover:underline">
                                {{ feed.url }}
                            </a>
                            <p v-if="feed.category" class="mt-1 text-xs text-muted-foreground">{{ feed.category.name }}</p>
                        </div>
                        <button @click="toggleFeed(feed)" :disabled="toggling === feed.id">
                            <ToggleRight v-if="feed.active" class="h-6 w-6 text-green-500" />
                            <ToggleLeft v-else class="h-6 w-6 text-muted-foreground" />
                        </button>
                    </div>
                    <div class="mt-3 flex gap-3 border-t border-border pt-3">
                        <Link :href="`/feeds/${feed.id}/edit`" class="flex items-center gap-1 text-sm text-primary hover:underline">
                            <Pencil class="h-4 w-4" /> {{ t('general.edit') }}
                        </Link>
                        <button
                            @click="deleteFeed(feed.id)"
                            :disabled="deleting === feed.id"
                            class="flex items-center gap-1 text-sm text-destructive hover:underline"
                        >
                            <Trash2 class="h-4 w-4" /> {{ t('general.delete') }}
                        </button>
                    </div>
                </li>
            </ul>
        </div>
    </AppLayout>
</template>
