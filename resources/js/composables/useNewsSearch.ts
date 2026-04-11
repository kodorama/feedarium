import { useDebounceFn } from '@vueuse/core';
import axios from 'axios';
import { computed, ref } from 'vue';

export interface SearchArticleFeed {
    id: number;
    name: string;
    favicon_url: string | null;
}

export interface SearchArticle {
    id: number;
    title: string;
    link: string;
    description: string | null;
    thumbnail_url: string | null;
    published_at: string | null;
    author: string | null;
    is_read: boolean;
    feed: SearchArticleFeed;
}

export function useNewsSearch() {
    const searchQuery = ref('');
    const searchResults = ref<SearchArticle[]>([]);
    const isSearching = ref(false);
    const isLoadingMoreSearch = ref(false);
    const hasSearched = ref(false);
    const searchNextUrl = ref<string | null>(null);

    const searchHasMore = computed(() => !!searchNextUrl.value);

    // Internal state — not reactive, only needed for load-more replay
    let _lastQ = '';
    let _lastCategoryId: number | null = null;
    let _lastFeedId: number | null = null;
    let _currentPage = 1;

    async function doFetch(q: string, categoryId: number | null, feedId: number | null, page: number, append: boolean): Promise<void> {
        const params: Record<string, unknown> = { q, page };
        if (feedId !== null) {
            params.feed_id = feedId;
        } else if (categoryId !== null) {
            params.category_id = categoryId;
        }

        const res = await axios.get<{ data: SearchArticle[]; links: { next: string | null } }>('/api/news/search', {
            params,
        });

        if (append) {
            searchResults.value = [...searchResults.value, ...(res.data.data ?? [])];
        } else {
            searchResults.value = res.data.data ?? [];
        }

        searchNextUrl.value = res.data.links?.next ?? null;
        hasSearched.value = true;
    }

    const performSearch = useDebounceFn(async (q: string, categoryId: number | null = null, feedId: number | null = null): Promise<void> => {
        if (!q || q.length < 2) {
            searchResults.value = [];
            hasSearched.value = false;
            searchNextUrl.value = null;
            _currentPage = 1;
            return;
        }

        _lastQ = q;
        _lastCategoryId = categoryId;
        _lastFeedId = feedId;
        _currentPage = 1;

        isSearching.value = true;
        try {
            await doFetch(q, categoryId, feedId, 1, false);
        } finally {
            isSearching.value = false;
        }
    }, 300);

    async function loadMoreSearchResults(): Promise<void> {
        if (!searchHasMore.value || isLoadingMoreSearch.value || isSearching.value) return;

        _currentPage++;
        isLoadingMoreSearch.value = true;
        try {
            await doFetch(_lastQ, _lastCategoryId, _lastFeedId, _currentPage, true);
        } finally {
            isLoadingMoreSearch.value = false;
        }
    }

    function clearSearch(): void {
        searchQuery.value = '';
        searchResults.value = [];
        hasSearched.value = false;
        isSearching.value = false;
        isLoadingMoreSearch.value = false;
        searchNextUrl.value = null;
        _currentPage = 1;
    }

    return {
        searchQuery,
        searchResults,
        isSearching,
        isLoadingMoreSearch,
        hasSearched,
        searchHasMore,
        performSearch,
        loadMoreSearchResults,
        clearSearch,
    };
}
