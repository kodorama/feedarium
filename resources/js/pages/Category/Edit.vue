<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import axios from 'axios';

const { t } = useI18n();

interface CategoryProp {
    id: number;
    name: string;
    description: string | null;
}

const categoryProp = usePage().props.category as CategoryProp;

const breadcrumbs: BreadcrumbItem[] = [
    { title: t('nav.dashboard'), href: '/dashboard' },
    { title: t('categories.title'), href: '/categories' },
    { title: t('categories.edit'), href: `/categories/${categoryProp?.id}/edit` },
];

const submitting = ref(false);
const errors = ref<Record<string, string>>({});
const successMsg = ref('');

const form = ref({
    name: categoryProp?.name ?? '',
    description: categoryProp?.description ?? '',
});

async function submit() {
    submitting.value = true;
    errors.value = {};
    successMsg.value = '';
    try {
        await axios.put(`/api/categories/${categoryProp.id}`, {
            name: form.value.name,
            description: form.value.description || undefined,
        });
        successMsg.value = t('categories.updated');
        setTimeout(() => router.visit('/categories'), 800);
    } catch (e: any) {
        if (e.response?.status === 422) {
            const errs = e.response.data.errors ?? {};
            Object.keys(errs).forEach((k) => { errors.value[k] = errs[k][0]; });
        }
    } finally {
        submitting.value = false;
    }
}
</script>

<template>
    <Head :title="t('categories.edit')" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-2xl p-4 md:p-6">
            <h1 class="mb-6 text-2xl font-bold">{{ t('categories.edit') }}</h1>

            <div v-if="successMsg" class="mb-4 rounded-lg bg-green-100 px-4 py-3 text-sm text-green-800 dark:bg-green-900/30 dark:text-green-200">
                {{ successMsg }}
            </div>

            <form @submit.prevent="submit" class="space-y-5 rounded-xl border border-border bg-card p-6 shadow-sm">
                <div>
                    <label class="mb-1 block text-sm font-medium">{{ t('categories.name') }} *</label>
                    <input
                        v-model="form.name"
                        type="text"
                        required
                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"
                    />
                    <p v-if="errors.name" class="mt-1 text-xs text-destructive">{{ errors.name }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">{{ t('categories.description') }}</label>
                    <textarea
                        v-model="form.description"
                        rows="3"
                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"
                    />
                </div>
                <div class="flex items-center gap-3 pt-2">
                    <button
                        type="submit"
                        :disabled="submitting"
                        class="rounded-lg bg-primary px-5 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 disabled:opacity-60"
                    >
                        {{ submitting ? t('general.loading') : t('general.update') }}
                    </button>
                    <Link href="/categories" class="text-sm text-muted-foreground hover:underline">{{ t('general.cancel') }}</Link>
                </div>
            </form>
        </div>
    </AppLayout>
</template>

