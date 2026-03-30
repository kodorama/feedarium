<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import axios from 'axios';
import { Pencil, Plus, Trash2, Users } from 'lucide-vue-next';
import { onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const breadcrumbs: BreadcrumbItem[] = [
    { title: t('nav.dashboard'), href: '/dashboard' },
    { title: 'Users', href: '/users' },
];

interface User {
    id: number;
    name: string;
    email: string;
    is_admin: boolean;
}

const users = ref<User[]>([]);
const loading = ref(true);
const deleting = ref<number | null>(null);

async function loadUsers() {
    loading.value = true;
    try {
        const res = await axios.get('/api/users');
        users.value = res.data.data ?? res.data;
    } finally {
        loading.value = false;
    }
}

async function deleteUser(id: number) {
    if (!confirm(t('general.confirm_delete'))) return;
    deleting.value = id;
    try {
        await axios.delete(`/api/users/${id}`);
        users.value = users.value.filter((u) => u.id !== id);
    } finally {
        deleting.value = null;
    }
}

onMounted(loadUsers);
</script>

<template>
    <Head title="Users" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-4 md:p-6">
            <div class="mb-6 flex items-center justify-between">
                <h1 class="text-2xl font-bold">Users</h1>
                <Link
                    href="/users/create"
                    class="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90"
                >
                    <Plus class="h-4 w-4" /> Add User
                </Link>
            </div>

            <div v-if="loading" class="py-12 text-center text-muted-foreground">{{ t('general.loading') }}</div>

            <div v-else-if="users.length === 0" class="py-12 text-center text-muted-foreground">
                <Users class="mx-auto mb-3 h-10 w-10 opacity-40" />
                <p>{{ t('general.no_results') }}</p>
            </div>

            <!-- Desktop table -->
            <div v-else class="hidden overflow-x-auto rounded-xl border border-border sm:block">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-xs tracking-wide text-muted-foreground uppercase">
                        <tr>
                            <th class="px-4 py-3 text-left">{{ t('auth.name') }}</th>
                            <th class="px-4 py-3 text-left">{{ t('auth.email') }}</th>
                            <th class="px-4 py-3 text-left">Admin</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border bg-card">
                        <tr v-for="user in users" :key="user.id" class="hover:bg-muted/30">
                            <td class="px-4 py-3 font-medium">{{ user.name }}</td>
                            <td class="px-4 py-3 text-muted-foreground">{{ user.email }}</td>
                            <td class="px-4 py-3">
                                <span v-if="user.is_admin" class="rounded-full bg-primary/10 px-2 py-0.5 text-xs font-medium text-primary"
                                    >Admin</span
                                >
                                <span v-else class="text-muted-foreground">—</span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-2">
                                    <Link :href="`/users/${user.id}/edit`" class="rounded p-1 hover:bg-accent">
                                        <Pencil class="h-4 w-4" />
                                    </Link>
                                    <button
                                        @click="deleteUser(user.id)"
                                        :disabled="deleting === user.id"
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
            <ul v-if="users.length > 0" class="space-y-3 sm:hidden">
                <li v-for="user in users" :key="user.id" class="rounded-xl border border-border bg-card p-4 shadow-sm">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="font-semibold">{{ user.name }}</p>
                            <p class="text-sm text-muted-foreground">{{ user.email }}</p>
                            <span v-if="user.is_admin" class="mt-1 inline-block rounded-full bg-primary/10 px-2 py-0.5 text-xs text-primary"
                                >Admin</span
                            >
                        </div>
                    </div>
                    <div class="mt-3 flex gap-3 border-t border-border pt-3">
                        <Link :href="`/users/${user.id}/edit`" class="flex items-center gap-1 text-sm text-primary hover:underline">
                            <Pencil class="h-4 w-4" /> {{ t('general.edit') }}
                        </Link>
                        <button
                            @click="deleteUser(user.id)"
                            :disabled="deleting === user.id"
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
