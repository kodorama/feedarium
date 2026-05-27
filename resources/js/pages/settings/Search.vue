<script setup lang="ts">
import HeadingSmall from '@/components/HeadingSmall.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type AppPageProps, type BreadcrumbItem } from '@/types';
import { Head, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

interface Props {
    scoutDriver: string;
}

const props = defineProps<Props>();

const page = usePage<AppPageProps>();
const isAdmin = computed(() => page.props.auth.user.is_admin === true);
const isMeilisearch = computed(() => props.scoutDriver === 'meilisearch');

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: t('settings.breadcrumb.settings'), href: '/settings/profile' },
    { title: t('settings.breadcrumb.search'), href: '/settings/search' },
]);

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
        showToast('success', t('settings.search.import_success'));
    } catch {
        showToast('error', t('settings.search.import_failed'));
    } finally {
        importing.value = false;
    }
}

async function flushIndex() {
    flushing.value = true;
    try {
        await axios.post('/api/scout/flush');
        showToast('success', t('settings.search.flush_success'));
    } catch {
        showToast('error', t('settings.search.flush_failed'));
    } finally {
        flushing.value = false;
    }
}

async function syncSettings() {
    syncing.value = true;
    try {
        await axios.post('/api/scout/sync-settings');
        showToast('success', t('settings.search.sync_success'));
    } catch {
        showToast('error', t('settings.search.sync_failed'));
    } finally {
        syncing.value = false;
    }
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="t('settings.breadcrumb.search')" />

        <SettingsLayout>
            <div class="space-y-6">
                <HeadingSmall :title="t('settings.search.title')" :description="t('settings.search.description')" />

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
                        {{ t('settings.search.active_driver') }}
                        <span class="font-medium text-foreground">{{ scoutDriver }}</span>
                    </div>

                    <!-- Index operations -->
                    <div class="space-y-3">
                        <p class="text-sm text-muted-foreground">{{ t('settings.search.operations_note') }}</p>

                        <div class="flex flex-wrap gap-3">
                            <Button @click="importIndex" :disabled="importing">
                                {{ importing ? t('settings.search.importing') : t('settings.search.import') }}
                            </Button>

                            <Button variant="outline" @click="flushIndex" :disabled="flushing">
                                {{ flushing ? t('settings.search.flushing') : t('settings.search.flush') }}
                            </Button>

                            <Button v-if="isMeilisearch" variant="secondary" @click="syncSettings" :disabled="syncing">
                                {{ syncing ? t('settings.search.syncing') : t('settings.search.sync_settings') }}
                            </Button>
                        </div>
                    </div>
                </template>

                <template v-else>
                    <p class="text-sm text-muted-foreground">{{ t('settings.search.admin_only') }}</p>
                </template>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
