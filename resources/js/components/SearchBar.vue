<script setup lang="ts">
import { ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import axios from 'axios';

const { t } = useI18n();

interface SearchResult {
    id: number;
    title: string;
    link: string;
    feed?: { name: string };
    name?: string; // for feeds
    url?: string;
}

const query = ref('');
const results = ref<SearchResult[]>([]);
const loading = ref(false);
const showDropdown = ref(false);
let debounceTimer: ReturnType<typeof setTimeout> | null = null;

watch(query, (val) => {
    if (debounceTimer) clearTimeout(debounceTimer);
    if (val.length < 2) {
        results.value = [];
        showDropdown.value = false;
        return;
    }
    debounceTimer = setTimeout(() => search(val), 300);
});

async function search(q: string) {
    loading.value = true;
    try {
        const res = await axios.get('/api/news/search', { params: { q } });
        results.value = res.data.data ?? [];
        showDropdown.value = true;
    } catch {
        results.value = [];
    } finally {
        loading.value = false;
    }
}

function close() {
    showDropdown.value = false;
    query.value = '';
    results.value = [];
}

function onBlur() {
    setTimeout(close, 150);
}
</script>

<template>
    <div class="relative">
        <input
            v-model="query"
            type="search"
            :placeholder="t('news.search_placeholder')"
            class="h-9 w-48 rounded-md border border-input bg-background px-3 text-sm focus:outline-none focus:ring-2 focus:ring-primary sm:w-64"
            @blur="onBlur"
            @focus="query.length >= 2 && (showDropdown = true)"
        />
        <div
            v-if="showDropdown && results.length > 0"
            class="absolute right-0 top-10 z-50 w-80 rounded-xl border border-border bg-popover shadow-lg"
        >
            <ul class="max-h-72 overflow-y-auto py-1">
                <li v-if="loading" class="px-4 py-2 text-sm text-muted-foreground">{{ t('general.loading') }}</li>
                <li
                    v-for="item in results"
                    :key="item.id"
                    class="px-4 py-2 hover:bg-accent"
                >
                    <a :href="item.link" target="_blank" rel="noopener noreferrer" class="block text-sm font-medium">
                        {{ item.title }}
                    </a>
                    <span v-if="item.feed" class="text-xs text-muted-foreground">{{ item.feed.name }}</span>
                </li>
            </ul>
            <div v-if="results.length === 0 && !loading" class="px-4 py-2 text-sm text-muted-foreground">
                {{ t('general.no_results') }}
            </div>
        </div>
    </div>
</template>

