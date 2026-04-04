<script setup lang="ts">
import HeadingSmall from '@/components/HeadingSmall.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type AppPageProps, type BreadcrumbItem } from '@/types';
import { Head, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref } from 'vue';

interface Props {
    scoutDriver: string;
}

const props = defineProps<Props>();

const page = usePage<AppPageProps>();
const isAdmin = computed(() => page.props.auth.user.is_admin === true);
const isMeilisearch = computed(() => props.scoutDriver === 'meilisearch');

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Settings', href: '/settings/profile' },
    { title: 'Search', href: '/settings/search' },
];

interface Toast {
    type: 'success' | 'error';
    message: string;
}

const toast = ref<Toast | null>(null);
const importing = ref(false);
const flushing = ref(false);
const syncing = ref(false);

function showToast(type: Toast['type'], message: string) {
    toast.value = { type, message };
    setTimeout(() => {
        toast.value = null;
    }, 3500);
}

async function importIndex() {
    importing.value = true;
    try {
        await axios.post('/api/scout/import');
        showToast('success', 'Import job dispatched. Articles will be indexed in the background.');
    } catch {
        showToast('error', 'Failed to dispatch import job.');
    } finally {
        importing.value = false;
    }
}

async function flushIndex() {
    flushing.value = true;
    try {
        await axios.post('/api/scout/flush');
        showToast('success', 'Flush job dispatched. The search index will be cleared in the background.');
    } catch {
        showToast('error', 'Failed to dispatch flush job.');
    } finally {
        flushing.value = false;
    }
}

async function syncSettings() {
    syncing.value = true;
    try {
        await axios.post('/api/scout/sync-settings');
        showToast('success', 'Search index settings synced successfully.');
    } catch {
        showToast('error', 'Failed to sync index settings.');
    } finally {
        syncing.value = false;
    }
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Search settings" />

        <SettingsLayout>
            <div class="space-y-6">
                <HeadingSmall title="Search" description="Manage the full-text search index for articles." />

                <!-- Toast notification -->
                <div
                    v-if="toast"
                    :class="[
                        'rounded-lg px-4 py-3 text-sm',
                        toast.type === 'success'
                            ? 'bg-green-50 text-green-800 dark:bg-green-900/30 dark:text-green-200'
                            : 'bg-red-50 text-red-800 dark:bg-red-900/30 dark:text-red-200',
                    ]"
                >
                    {{ toast.message }}
                </div>

                <template v-if="isAdmin">
                    <!-- Driver info -->
                    <div class="rounded-lg border border-border bg-muted/40 px-4 py-3 text-sm text-muted-foreground">
                        Active driver:
                        <span class="font-medium text-foreground">{{ scoutDriver }}</span>
                    </div>

                    <!-- Index operations -->
                    <div class="space-y-3">
                        <p class="text-sm text-muted-foreground">All operations run in the background via the queue worker.</p>

                        <div class="flex flex-wrap gap-3">
                            <Button @click="importIndex" :disabled="importing">
                                {{ importing ? 'Dispatching…' : 'Import All Articles' }}
                            </Button>

                            <Button variant="outline" @click="flushIndex" :disabled="flushing">
                                {{ flushing ? 'Dispatching…' : 'Flush Index' }}
                            </Button>

                            <Button v-if="isMeilisearch" variant="secondary" @click="syncSettings" :disabled="syncing">
                                {{ syncing ? 'Syncing…' : 'Sync Index Settings' }}
                            </Button>
                        </div>
                    </div>
                </template>

                <template v-else>
                    <p class="text-sm text-muted-foreground">Search index management is available to administrators only.</p>
                </template>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
