<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import axios from 'axios';

const { t } = useI18n();

const breadcrumbs: BreadcrumbItem[] = [
    { title: t('nav.dashboard'), href: '/dashboard' },
    { title: t('feeds.title'), href: '/feeds' },
    { title: t('feeds.create'), href: '/feeds/create' },
];

interface Category {
    id: number;
    name: string;
}

const categories = ref<Category[]>([]);
const submitting = ref(false);
const errors = ref<Record<string, string>>({});
const successMsg = ref('');

const form = ref({
    name: '',
    url: '',
    description: '',
    active: true,
    category_id: '',
    hub_url: '',
});

onMounted(async () => {
    try {
        const res = await axios.get('/api/categories');
        categories.value = res.data.data ?? res.data;
    } catch {
        // ignore
    }
});

async function submit() {
    submitting.value = true;
    errors.value = {};
    successMsg.value = '';
    try {
        await axios.post('/api/feeds', {
            name: form.value.name,
            url: form.value.url,
            description: form.value.description || undefined,
            active: form.value.active,
            category_id: form.value.category_id || undefined,
            hub_url: form.value.hub_url || undefined,
        });
        successMsg.value = t('feeds.created');
        setTimeout(() => router.visit('/feeds'), 800);
    } catch (e: any) {
        if (e.response?.status === 422) {
            const errs = e.response.data.errors ?? {};
            Object.keys(errs).forEach((k) => {
                errors.value[k] = errs[k][0];
            });
        }
    } finally {
        submitting.value = false;
    }
}
</script>

<template>
    <Head :title="t('feeds.create')" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-2xl p-4 md:p-6">
            <h1 class="mb-6 text-2xl font-bold">{{ t('feeds.create') }}</h1>

            <div v-if="successMsg" class="mb-4 rounded-lg bg-green-100 px-4 py-3 text-sm text-green-800 dark:bg-green-900/30 dark:text-green-200">
                {{ successMsg }}
            </div>

            <form @submit.prevent="submit" class="space-y-5 rounded-xl border border-border bg-card p-6 shadow-sm">
                <!-- Name -->
                <div>
                    <label class="mb-1 block text-sm font-medium">{{ t('feeds.name') }} *</label>
                    <input
                        v-model="form.name"
                        type="text"
                        required
                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"
                    />
                    <p v-if="errors.name" class="mt-1 text-xs text-destructive">{{ errors.name }}</p>
                </div>

                <!-- URL -->
                <div>
                    <label class="mb-1 block text-sm font-medium">{{ t('feeds.url') }} *</label>
                    <input
                        v-model="form.url"
                        type="url"
                        required
                        placeholder="https://example.com/rss"
                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"
                    />
                    <p v-if="errors.url" class="mt-1 text-xs text-destructive">{{ errors.url }}</p>
                </div>

                <!-- Description -->
                <div>
                    <label class="mb-1 block text-sm font-medium">{{ t('feeds.description') }}</label>
                    <textarea
                        v-model="form.description"
                        rows="3"
                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"
                    />
                    <p v-if="errors.description" class="mt-1 text-xs text-destructive">{{ errors.description }}</p>
                </div>

                <!-- Category -->
                <div>
                    <label class="mb-1 block text-sm font-medium">{{ t('feeds.category') }}</label>
                    <select
                        v-model="form.category_id"
                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"
                    >
                        <option value="">— None —</option>
                        <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                    </select>
                </div>

                <!-- Hub URL (WebSub) -->
                <div>
                    <label class="mb-1 block text-sm font-medium">{{ t('feeds.hub_url') }}</label>
                    <input
                        v-model="form.hub_url"
                        type="url"
                        placeholder="https://pubsubhubbub.appspot.com/"
                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"
                    />
                    <p v-if="errors.hub_url" class="mt-1 text-xs text-destructive">{{ errors.hub_url }}</p>
                </div>

                <!-- Active toggle -->
                <div class="flex items-center gap-3">
                    <input id="active" v-model="form.active" type="checkbox" class="h-4 w-4 rounded border-input" />
                    <label for="active" class="text-sm font-medium">{{ t('feeds.active') }}</label>
                </div>

                <!-- Actions -->
                <div class="flex items-center gap-3 pt-2">
                    <button
                        type="submit"
                        :disabled="submitting"
                        class="rounded-lg bg-primary px-5 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 disabled:opacity-60"
                    >
                        {{ submitting ? t('general.loading') : t('general.save') }}
                    </button>
                    <Link href="/feeds" class="text-sm text-muted-foreground hover:underline">{{ t('general.cancel') }}</Link>
                </div>
            </form>
        </div>
    </AppLayout>
</template>

