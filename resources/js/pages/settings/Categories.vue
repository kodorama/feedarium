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
import { Check, Pencil, Plus, Tag, Trash2, X } from 'lucide-vue-next';
import { onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Settings', href: '/settings/profile' },
    { title: 'Categories', href: '/settings/categories' },
];

interface Category {
    id: number;
    name: string;
    description: string | null;
}

interface Toast {
    type: 'success' | 'error';
    message: string;
}

const categories = ref<Category[]>([]);
const loading = ref(true);
const saving = ref(false);
const deleting = ref<number | null>(null);
const errors = ref<Record<string, string>>({});
const toast = ref<Toast | null>(null);

const showAddForm = ref(false);
const editingId = ref<number | null>(null);

const addForm = ref({ name: '', description: '' });
const editForm = ref({ name: '', description: '' });

function showToast(type: Toast['type'], message: string) {
    toast.value = { type, message };
    setTimeout(() => {
        toast.value = null;
    }, 3500);
}

async function loadCategories() {
    loading.value = true;
    try {
        const res = await axios.get('/api/categories');
        // API returns { categories: [...] }
        categories.value = res.data.categories ?? res.data.data ?? res.data;
    } finally {
        loading.value = false;
    }
}

async function createCategory() {
    saving.value = true;
    errors.value = {};
    try {
        const res = await axios.post('/api/categories', {
            name: addForm.value.name,
            description: addForm.value.description || undefined,
        });
        categories.value.unshift(res.data.category);
        addForm.value = { name: '', description: '' };
        showAddForm.value = false;
        showToast('success', 'Category created successfully.');
    } catch (e: any) {
        if (e.response?.status === 422) {
            const errs = e.response.data.errors ?? {};
            Object.keys(errs).forEach((k) => {
                errors.value[`add_${k}`] = errs[k][0];
            });
        } else {
            showToast('error', 'Failed to create category. Please try again.');
        }
    } finally {
        saving.value = false;
    }
}

function startEdit(cat: Category) {
    editingId.value = cat.id;
    editForm.value = { name: cat.name, description: cat.description ?? '' };
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
        const res = await axios.put(`/api/categories/${id}`, {
            name: editForm.value.name,
            description: editForm.value.description || undefined,
        });
        const idx = categories.value.findIndex((c) => c.id === id);
        if (idx !== -1) {
            categories.value[idx] = res.data.category;
        }
        editingId.value = null;
        showToast('success', 'Category updated successfully.');
    } catch (e: any) {
        if (e.response?.status === 422) {
            const errs = e.response.data.errors ?? {};
            Object.keys(errs).forEach((k) => {
                errors.value[`edit_${k}`] = errs[k][0];
            });
        } else {
            showToast('error', 'Failed to update category. Please try again.');
        }
    } finally {
        saving.value = false;
    }
}

async function deleteCategory(id: number) {
    if (!confirm(t('general.confirm_delete'))) {
        return;
    }
    deleting.value = id;
    try {
        await axios.delete(`/api/categories/${id}`);
        categories.value = categories.value.filter((c) => c.id !== id);
        showToast('success', 'Category deleted.');
    } catch {
        showToast('error', 'Failed to delete category.');
    } finally {
        deleting.value = null;
    }
}

onMounted(loadCategories);
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Categories — Settings" />

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
                    <HeadingSmall title="Categories" description="Organise your feed sources into categories." />
                    <Button size="sm" variant="outline" @click="showAddForm = !showAddForm">
                        <Plus class="mr-1 h-4 w-4" />
                        Add
                    </Button>
                </div>

                <!-- Add form -->
                <form v-if="showAddForm" @submit.prevent="createCategory" class="space-y-3 rounded-lg border border-border bg-muted/40 p-4">
                    <div class="grid gap-1.5">
                        <Label for="add-name">Name *</Label>
                        <Input id="add-name" v-model="addForm.name" placeholder="Technology" required />
                        <p v-if="errors.add_name" class="text-xs text-destructive">{{ errors.add_name }}</p>
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="add-desc">Description</Label>
                        <Input id="add-desc" v-model="addForm.description" placeholder="Optional description" />
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
                <div v-else-if="categories.length === 0" class="py-8 text-center text-sm text-muted-foreground">
                    <Tag class="mx-auto mb-2 h-8 w-8 opacity-40" />
                    <p>No categories yet.</p>
                </div>

                <!-- List -->
                <ul v-else class="divide-y divide-border rounded-lg border border-border">
                    <li v-for="cat in categories" :key="cat.id" class="px-4 py-3">
                        <!-- Edit mode -->
                        <form v-if="editingId === cat.id" @submit.prevent="saveEdit(cat.id)" class="space-y-2">
                            <div class="grid gap-1">
                                <Input v-model="editForm.name" placeholder="Name" required />
                                <p v-if="errors.edit_name" class="text-xs text-destructive">{{ errors.edit_name }}</p>
                            </div>
                            <Input v-model="editForm.description" placeholder="Description (optional)" />
                            <div class="flex gap-2">
                                <Button type="submit" size="sm" :disabled="saving"> <Check class="mr-1 h-4 w-4" /> Save </Button>
                                <Button type="button" size="sm" variant="ghost" @click="cancelEdit"> <X class="mr-1 h-4 w-4" /> Cancel </Button>
                            </div>
                        </form>

                        <!-- View mode -->
                        <div v-else class="flex items-center justify-between gap-4">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium">{{ cat.name }}</p>
                                <p v-if="cat.description" class="truncate text-xs text-muted-foreground">{{ cat.description }}</p>
                            </div>
                            <div class="flex shrink-0 items-center gap-1">
                                <Button size="icon" variant="ghost" class="h-8 w-8" @click="startEdit(cat)">
                                    <Pencil class="h-4 w-4" />
                                </Button>
                                <Button
                                    size="icon"
                                    variant="ghost"
                                    class="h-8 w-8 text-destructive hover:text-destructive"
                                    :disabled="deleting === cat.id"
                                    @click="deleteCategory(cat.id)"
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
