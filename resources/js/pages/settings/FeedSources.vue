<script setup lang="ts">
import HeadingSmall from '@/components/HeadingSmall.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { Check, Pencil, Plus, Rss, ToggleLeft, ToggleRight, Trash2, X } from 'lucide-vue-next';
import { onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Settings', href: '/settings/profile' },
    { title: 'Feed Sources', href: '/settings/feeds' },
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
    category_id: number | null;
    category: Category | null;
    hub_url: string | null;
    favicon_url: string | null;
}

interface Toast {
    type: 'success' | 'error';
    message: string;
}

const feeds = ref<Feed[]>([]);
const categories = ref<Category[]>([]);
const loading = ref(true);
const saving = ref(false);
const deleting = ref<number | null>(null);
const toggling = ref<number | null>(null);
const errors = ref<Record<string, string>>({});
const toast = ref<Toast | null>(null);

const showAddForm = ref(false);
const editingId = ref<number | null>(null);

const emptyForm = () => ({ name: '', url: '', description: '', active: true, category_id: '', hub_url: '' });
const addForm = ref(emptyForm());
const editForm = ref(emptyForm());

function showToast(type: Toast['type'], message: string) {
    toast.value = { type, message };
    setTimeout(() => {
        toast.value = null;
    }, 3500);
}

async function loadData() {
    loading.value = true;
    try {
        const [feedsRes, catsRes] = await Promise.all([axios.get('/api/feeds'), axios.get('/api/categories')]);
        // APIs return { feeds: [...] } and { categories: [...] }
        feeds.value = feedsRes.data.feeds ?? feedsRes.data.data ?? feedsRes.data;
        categories.value = catsRes.data.categories ?? catsRes.data.data ?? catsRes.data;
    } finally {
        loading.value = false;
    }
}

async function createFeed() {
    saving.value = true;
    errors.value = {};
    try {
        const res = await axios.post('/api/feeds', {
            name: addForm.value.name,
            url: addForm.value.url,
            description: addForm.value.description || undefined,
            active: addForm.value.active,
            category_id: addForm.value.category_id || undefined,
            hub_url: addForm.value.hub_url || undefined,
        });
        feeds.value.unshift(res.data.feed);
        addForm.value = emptyForm();
        showAddForm.value = false;
        showToast('success', 'Feed source added successfully.');
    } catch (e: any) {
        if (e.response?.status === 422) {
            const errs = e.response.data.errors ?? {};
            Object.keys(errs).forEach((k) => {
                errors.value[`add_${k}`] = errs[k][0];
            });
        } else {
            showToast('error', 'Failed to add feed source. Please try again.');
        }
    } finally {
        saving.value = false;
    }
}

function startEdit(feed: Feed) {
    editingId.value = feed.id;
    editForm.value = {
        name: feed.name,
        url: feed.url,
        description: feed.description ?? '',
        active: feed.active,
        category_id: feed.category_id ? String(feed.category_id) : '',
        hub_url: feed.hub_url ?? '',
    };
    errors.value = {};
}

function cancelEdit() {
    editingId.value = null;
    errors.value = {};
}

async function saveEdit(id: number) {
    saving.value = true;
    errors.value = {};
    try {
        const res = await axios.put(`/api/feeds/${id}`, {
            name: editForm.value.name,
            url: editForm.value.url,
            description: editForm.value.description || undefined,
            active: editForm.value.active,
            category_id: editForm.value.category_id || undefined,
            hub_url: editForm.value.hub_url || undefined,
        });
        const idx = feeds.value.findIndex((f) => f.id === id);
        if (idx !== -1) {
            feeds.value[idx] = res.data.feed;
        }
        editingId.value = null;
        showToast('success', 'Feed source updated successfully.');
    } catch (e: any) {
        if (e.response?.status === 422) {
            const errs = e.response.data.errors ?? {};
            Object.keys(errs).forEach((k) => {
                errors.value[`edit_${k}`] = errs[k][0];
            });
        } else {
            showToast('error', 'Failed to update feed source. Please try again.');
        }
    } finally {
        saving.value = false;
    }
}

async function toggleFeed(feed: Feed) {
    toggling.value = feed.id;
    try {
        await axios.patch(`/api/feeds/${feed.id}/toggle`);
        feed.active = !feed.active;
        showToast('success', feed.active ? 'Feed activated.' : 'Feed deactivated.');
    } catch {
        showToast('error', 'Failed to toggle feed status.');
    } finally {
        toggling.value = null;
    }
}

async function deleteFeed(id: number) {
    if (!confirm(t('general.confirm_delete'))) {
        return;
    }
    deleting.value = id;
    try {
        await axios.delete(`/api/feeds/${id}`);
        feeds.value = feeds.value.filter((f) => f.id !== id);
        showToast('success', 'Feed source deleted.');
    } catch {
        showToast('error', 'Failed to delete feed source.');
    } finally {
        deleting.value = null;
    }
}

onMounted(loadData);
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Feed Sources — Settings" />

        <SettingsLayout>
            <div class="space-y-6">
                <!-- Toast notification -->
                <Transition
                    enter-active-class="transition ease-out duration-200"
                    enter-from-class="opacity-0 -translate-y-1"
                    leave-active-class="transition ease-in duration-150"
                    leave-to-class="opacity-0 -translate-y-1"
                >
                    <div
                        v-if="toast"
                        :class="[
                            'flex items-center gap-2 rounded-lg px-4 py-3 text-sm font-medium',
                            toast.type === 'success'
                                ? 'bg-green-50 text-green-800 dark:bg-green-900/30 dark:text-green-200'
                                : 'bg-red-50 text-red-800 dark:bg-red-900/30 dark:text-red-200',
                        ]"
                    >
                        <Check v-if="toast.type === 'success'" class="h-4 w-4 shrink-0" />
                        <X v-else class="h-4 w-4 shrink-0" />
                        {{ toast.message }}
                    </div>
                </Transition>

                <div class="flex items-center justify-between">
                    <HeadingSmall title="Feed Sources" description="Add RSS/Atom feeds to follow." />
                    <Button size="sm" variant="outline" @click="showAddForm = !showAddForm">
                        <Plus class="mr-1 h-4 w-4" />
                        Add
                    </Button>
                </div>

                <!-- Add form -->
                <form v-if="showAddForm" @submit.prevent="createFeed" class="space-y-3 rounded-lg border border-border bg-muted/40 p-4">
                    <div class="grid gap-1.5">
                        <Label for="add-name">Name *</Label>
                        <Input id="add-name" v-model="addForm.name" placeholder="My Blog" required />
                        <p v-if="errors.add_name" class="text-xs text-destructive">{{ errors.add_name }}</p>
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="add-url">Feed URL *</Label>
                        <Input id="add-url" v-model="addForm.url" type="url" placeholder="https://example.com/rss" required />
                        <p v-if="errors.add_url" class="text-xs text-destructive">{{ errors.add_url }}</p>
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="add-category">Category</Label>
                        <select
                            id="add-category"
                            v-model="addForm.category_id"
                            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                        >
                            <option value="">— None —</option>
                            <option v-for="cat in categories" :key="cat.id" :value="String(cat.id)">{{ cat.name }}</option>
                        </select>
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="add-desc">Description</Label>
                        <Input id="add-desc" v-model="addForm.description" placeholder="Optional description" />
                    </div>
                    <div class="flex items-center gap-2">
                        <input id="add-active" type="checkbox" v-model="addForm.active" class="h-4 w-4" />
                        <Label for="add-active" class="font-normal">Active</Label>
                    </div>
                    <div class="flex gap-2">
                        <Button type="submit" size="sm" :disabled="saving">Save</Button>
                        <Button
                            type="button"
                            size="sm"
                            variant="ghost"
                            @click="
                                showAddForm = false;
                                errors = {};
                            "
                            >Cancel</Button
                        >
                    </div>
                </form>

                <!-- Loading -->
                <div v-if="loading" class="py-8 text-center text-sm text-muted-foreground">{{ t('general.loading') }}</div>

                <!-- Empty state -->
                <div v-else-if="feeds.length === 0" class="py-8 text-center text-sm text-muted-foreground">
                    <Rss class="mx-auto mb-2 h-8 w-8 opacity-40" />
                    <p>No feed sources yet. Add one to get started.</p>
                </div>

                <!-- List -->
                <ul v-else class="divide-y divide-border rounded-lg border border-border">
                    <li v-for="feed in feeds" :key="feed.id" class="px-4 py-3">
                        <!-- Edit mode -->
                        <form v-if="editingId === feed.id" @submit.prevent="saveEdit(feed.id)" class="space-y-2">
                            <div class="grid gap-1">
                                <Input v-model="editForm.name" placeholder="Name" required />
                                <p v-if="errors.edit_name" class="text-xs text-destructive">{{ errors.edit_name }}</p>
                            </div>
                            <div class="grid gap-1">
                                <Input v-model="editForm.url" type="url" placeholder="Feed URL" required />
                                <p v-if="errors.edit_url" class="text-xs text-destructive">{{ errors.edit_url }}</p>
                            </div>
                            <select v-model="editForm.category_id" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm">
                                <option value="">— None —</option>
                                <option v-for="cat in categories" :key="cat.id" :value="String(cat.id)">{{ cat.name }}</option>
                            </select>
                            <Input v-model="editForm.description" placeholder="Description (optional)" />
                            <div class="flex items-center gap-2">
                                <input id="edit-active" type="checkbox" v-model="editForm.active" class="h-4 w-4" />
                                <Label for="edit-active" class="text-sm font-normal">Active</Label>
                            </div>
                            <div class="flex gap-2">
                                <Button type="submit" size="sm" :disabled="saving"> <Check class="mr-1 h-4 w-4" /> Save </Button>
                                <Button type="button" size="sm" variant="ghost" @click="cancelEdit"> <X class="mr-1 h-4 w-4" /> Cancel </Button>
                            </div>
                        </form>

                        <!-- View mode -->
                        <div v-else class="flex items-center justify-between gap-4">
                            <div class="flex min-w-0 items-center gap-3">
                                <img
                                    v-if="feed.favicon_url"
                                    :src="feed.favicon_url"
                                    referrerpolicy="no-referrer"
                                    class="h-5 w-5 shrink-0 rounded-sm object-contain"
                                    @error="($event.target as HTMLImageElement).style.display = 'none'"
                                    alt=""
                                />
                                <Rss v-else class="h-4 w-4 shrink-0 text-muted-foreground" />
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium">{{ feed.name }}</p>
                                    <p class="truncate text-xs text-muted-foreground">
                                        {{ feed.category?.name ?? 'Uncategorized' }} · {{ feed.url }}
                                    </p>
                                </div>
                            </div>
                            <div class="flex shrink-0 items-center gap-1">
                                <button
                                    @click="toggleFeed(feed)"
                                    :disabled="toggling === feed.id"
                                    class="text-muted-foreground hover:text-foreground"
                                    :title="feed.active ? 'Deactivate' : 'Activate'"
                                >
                                    <ToggleRight v-if="feed.active" class="h-5 w-5 text-primary" />
                                    <ToggleLeft v-else class="h-5 w-5" />
                                </button>
                                <Button size="icon" variant="ghost" class="h-8 w-8" @click="startEdit(feed)">
                                    <Pencil class="h-4 w-4" />
                                </Button>
                                <Button
                                    size="icon"
                                    variant="ghost"
                                    class="h-8 w-8 text-destructive hover:text-destructive"
                                    :disabled="deleting === feed.id"
                                    @click="deleteFeed(feed.id)"
                                >
                                    <Trash2 class="h-4 w-4" />
                                </Button>
                            </div>
                        </div>
                    </li>
                </ul>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
