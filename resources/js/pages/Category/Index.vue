<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import axios from 'axios';
import { Pencil, Trash2, Plus, Tag } from 'lucide-vue-next';

const { t } = useI18n();

const breadcrumbs: BreadcrumbItem[] = [
    { title: t('nav.dashboard'), href: '/dashboard' },
    { title: t('categories.title'), href: '/categories' },
];

interface Category {
    id: number;
    name: string;
    description: string | null;
}

const categories = ref<Category[]>([]);
const loading = ref(true);
const deleting = ref<number | null>(null);

async function loadCategories() {
    loading.value = true;
    try {
        const res = await axios.get('/api/categories');
        categories.value = res.data.data ?? res.data;
    } finally {
        loading.value = false;
    }
}

async function deleteCategory(id: number) {
    if (!confirm(t('general.confirm_delete'))) return;
    deleting.value = id;
    try {
        await axios.delete(`/api/categories/${id}`);
        categories.value = categories.value.filter((c) => c.id !== id);
    } finally {
        deleting.value = null;
    }
}

onMounted(loadCategories);
</script>

<template>
    <Head :title="t('categories.title')" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-4 md:p-6">
            <div class="mb-6 flex items-center justify-between">
                <h1 class="text-2xl font-bold">{{ t('categories.title') }}</h1>
                <Link
                    href="/categories/create"
                    class="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90"
                >
                    <Plus class="h-4 w-4" />
                    {{ t('categories.create') }}
                </Link>
            </div>

            <div v-if="loading" class="py-12 text-center text-muted-foreground">{{ t('general.loading') }}</div>

            <div v-else-if="categories.length === 0" class="py-12 text-center text-muted-foreground">
                <Tag class="mx-auto mb-3 h-10 w-10 opacity-40" />
                <p>{{ t('general.no_results') }}</p>
            </div>

            <!-- Desktop table -->
            <div v-else class="hidden sm:block overflow-x-auto rounded-xl border border-border">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-xs uppercase tracking-wide text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3 text-left">{{ t('categories.name') }}</th>
                            <th class="px-4 py-3 text-left">{{ t('categories.description') }}</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border bg-card">
                        <tr v-for="cat in categories" :key="cat.id" class="hover:bg-muted/30">
                            <td class="px-4 py-3 font-medium">{{ cat.name }}</td>
                            <td class="px-4 py-3 text-muted-foreground">{{ cat.description ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-2">
                                    <Link :href="`/categories/${cat.id}/edit`" class="rounded p-1 hover:bg-accent">
                                        <Pencil class="h-4 w-4" />
                                    </Link>
                                    <button
                                        @click="deleteCategory(cat.id)"
                                        :disabled="deleting === cat.id"
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
            <ul v-if="categories.length > 0" class="space-y-3 sm:hidden">
                <li v-for="cat in categories" :key="cat.id" class="rounded-xl border border-border bg-card p-4 shadow-sm">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <p class="font-semibold">{{ cat.name }}</p>
                            <p v-if="cat.description" class="mt-1 text-sm text-muted-foreground">{{ cat.description }}</p>
                        </div>
                    </div>
                    <div class="mt-3 flex gap-3 border-t border-border pt-3">
                        <Link :href="`/categories/${cat.id}/edit`" class="flex items-center gap-1 text-sm text-primary hover:underline">
                            <Pencil class="h-4 w-4" /> {{ t('general.edit') }}
                        </Link>
                        <button
                            @click="deleteCategory(cat.id)"
                            :disabled="deleting === cat.id"
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

