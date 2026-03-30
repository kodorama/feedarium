<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const breadcrumbs: BreadcrumbItem[] = [
    { title: t('nav.dashboard'), href: '/dashboard' },
    { title: 'Users', href: '/users' },
    { title: 'Add User', href: '/users/create' },
];

const submitting = ref(false);
const errors = ref<Record<string, string>>({});
const successMsg = ref('');

const form = ref({ name: '', email: '', password: '', is_admin: false });

async function submit() {
    submitting.value = true;
    errors.value = {};
    successMsg.value = '';
    try {
        await axios.post('/api/users', form.value);
        successMsg.value = 'User created successfully.';
        setTimeout(() => router.visit('/users'), 800);
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
    <Head title="Add User" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-2xl p-4 md:p-6">
            <h1 class="mb-6 text-2xl font-bold">Add User</h1>

            <div v-if="successMsg" class="mb-4 rounded-lg bg-green-100 px-4 py-3 text-sm text-green-800 dark:bg-green-900/30 dark:text-green-200">
                {{ successMsg }}
            </div>

            <form @submit.prevent="submit" class="space-y-5 rounded-xl border border-border bg-card p-6 shadow-sm">
                <div>
                    <label class="mb-1 block text-sm font-medium">{{ t('auth.name') }} *</label>
                    <input
                        v-model="form.name"
                        type="text"
                        required
                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus:ring-2 focus:ring-primary focus:outline-none"
                    />
                    <p v-if="errors.name" class="mt-1 text-xs text-destructive">{{ errors.name }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">{{ t('auth.email') }} *</label>
                    <input
                        v-model="form.email"
                        type="email"
                        required
                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus:ring-2 focus:ring-primary focus:outline-none"
                    />
                    <p v-if="errors.email" class="mt-1 text-xs text-destructive">{{ errors.email }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">{{ t('auth.password') }} *</label>
                    <input
                        v-model="form.password"
                        type="password"
                        required
                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus:ring-2 focus:ring-primary focus:outline-none"
                    />
                    <p v-if="errors.password" class="mt-1 text-xs text-destructive">{{ errors.password }}</p>
                </div>
                <div class="flex items-center gap-3">
                    <input id="is_admin" v-model="form.is_admin" type="checkbox" class="h-4 w-4 rounded border-input" />
                    <label for="is_admin" class="text-sm font-medium">Admin</label>
                </div>
                <div class="flex items-center gap-3 pt-2">
                    <button
                        type="submit"
                        :disabled="submitting"
                        class="rounded-lg bg-primary px-5 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 disabled:opacity-60"
                    >
                        {{ submitting ? t('general.loading') : t('general.save') }}
                    </button>
                    <Link href="/users" class="text-sm text-muted-foreground hover:underline">{{ t('general.cancel') }}</Link>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
