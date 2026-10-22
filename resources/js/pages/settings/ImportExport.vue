<script setup lang="ts">
import HeadingSmall from '@/components/HeadingSmall.vue';
import { Button } from '@/components/ui/button';
import { createPreviewGeneration } from '@/composables/opmlPreviewGeneration';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
const file = ref<File | null>(null);
const previewing = ref(false);
const importing = ref(false);
const error = ref('');
const notice = ref('');
const importSummary = ref<ImportSummary | null>(null);
const previewedFile = ref<File | null>(null);
const previewGeneration = createPreviewGeneration();

interface PreviewFeed {
    name: string;
    url: string;
    category: string | null;
    already_subscribed: boolean;
}

interface PreviewSummary {
    feed_count: number;
    already_subscribed: number;
    new_feeds: number;
    duplicates: number;
    invalid: number;
    unsupported: number;
    categories: number;
}

interface ImportSummary {
    created: number;
    existing: number;
    categories_created: number;
    duplicates: number;
    invalid: number;
    unsupported: number;
    failures: number;
}

const preview = ref<PreviewFeed[] | null>(null);
const summary = ref<PreviewSummary | null>(null);
const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: t('settings.breadcrumb.settings'), href: '/settings/profile' },
    { title: t('settings.breadcrumb.import_export'), href: '/settings/import-export' },
]);

function selectFile(event: Event) {
    const input = event.target as HTMLInputElement;
    file.value = input.files?.[0] ?? null;
    previewGeneration.select(file.value);
    previewing.value = false;
    preview.value = null;
    summary.value = null;
    previewedFile.value = null;
    importSummary.value = null;
    error.value = '';
    notice.value = '';
}

function uploadData(): FormData | null {
    if (!file.value) return null;
    const data = new FormData();
    data.append('file', file.value);

    return data;
}

async function previewFile() {
    const data = uploadData();
    if (!data) return;

    const requestedFile = file.value;
    const generation = previewGeneration.begin(requestedFile);
    previewing.value = true;
    preview.value = null;
    summary.value = null;
    previewedFile.value = null;
    error.value = '';
    notice.value = '';
    try {
        const response = await axios.post('/api/feeds/opml/preview', data);
        if (!previewGeneration.isCurrent(requestedFile, generation)) return;
        preview.value = response.data.feeds;
        summary.value = response.data.summary;
        previewedFile.value = requestedFile;
    } catch (exception: any) {
        if (!previewGeneration.isCurrent(requestedFile, generation)) return;
        error.value = exception.response?.data?.errors?.file?.[0] ?? t('settings.opml.preview_failed');
        preview.value = null;
        summary.value = null;
    } finally {
        if (previewGeneration.isCurrent(requestedFile, generation)) previewing.value = false;
    }
}

async function importFeeds() {
    const data = uploadData();
    if (!data || !summary.value || !file.value || previewedFile.value !== file.value || summary.value.new_feeds === 0) return;
    if (!window.confirm(t('settings.opml.confirm_import', { count: summary.value.new_feeds }))) return;

    importing.value = true;
    error.value = '';
    notice.value = '';
    try {
        const response = await axios.post('/api/feeds/opml/import', data);
        importSummary.value = response.data.summary;
        notice.value = t('settings.opml.import_complete');
        preview.value = null;
        summary.value = null;
        file.value = null;
        previewGeneration.select(null);
        const input = document.querySelector<HTMLInputElement>('#opml-file');
        if (input) input.value = '';
    } catch (exception: any) {
        error.value = exception.response?.data?.errors?.file?.[0] ?? t('settings.opml.import_failed');
    } finally {
        importing.value = false;
    }
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="t('settings.breadcrumb.import_export')" />

        <SettingsLayout>
            <div class="space-y-8">
                <section class="space-y-4">
                    <HeadingSmall :title="t('settings.opml.title')" :description="t('settings.opml.description')" />
                    <a
                        href="/api/feeds/opml/export"
                        class="inline-flex h-9 items-center justify-center rounded-md border border-input bg-background px-4 py-2 text-sm font-medium hover:bg-accent"
                    >
                        {{ t('settings.opml.export') }}
                    </a>
                </section>

                <section class="space-y-4 border-t border-border pt-6">
                    <HeadingSmall :title="t('settings.opml.import_title')" :description="t('settings.opml.import_description')" />
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                        <label for="opml-file" class="sr-only">{{ t('settings.opml.file_label') }}</label>
                        <input
                            id="opml-file"
                            type="file"
                            accept=".opml,.xml,application/xml,text/xml"
                            :disabled="importing"
                            class="block w-full text-sm text-muted-foreground file:mr-4 file:rounded-md file:border-0 file:bg-secondary file:px-3 file:py-2 file:text-sm file:font-medium file:text-secondary-foreground"
                            @change="selectFile"
                        />
                        <Button :disabled="!file || previewing || importing" @click="previewFile">
                            {{ previewing ? t('settings.opml.previewing') : t('settings.opml.preview') }}
                        </Button>
                    </div>

                    <p v-if="error" role="alert" class="text-sm text-destructive">{{ error }}</p>
                    <p v-if="notice" role="status" class="text-sm text-green-700 dark:text-green-300">{{ notice }}</p>

                    <div v-if="summary && previewedFile === file" class="space-y-4 rounded-md border border-border p-4" aria-live="polite">
                        <dl class="grid grid-cols-2 gap-3 text-sm sm:grid-cols-3 xl:grid-cols-6">
                            <div>
                                <dt class="text-muted-foreground">{{ t('settings.opml.feed_count') }}</dt>
                                <dd class="font-medium">{{ summary.feed_count }}</dd>
                            </div>
                            <div>
                                <dt class="text-muted-foreground">{{ t('settings.opml.new_feeds') }}</dt>
                                <dd class="font-medium">{{ summary.new_feeds }}</dd>
                            </div>
                            <div>
                                <dt class="text-muted-foreground">{{ t('settings.opml.existing_feeds') }}</dt>
                                <dd class="font-medium">{{ summary.already_subscribed }}</dd>
                            </div>
                            <div>
                                <dt class="text-muted-foreground">{{ t('settings.opml.duplicates') }}</dt>
                                <dd class="font-medium">{{ summary.duplicates }}</dd>
                            </div>
                            <div>
                                <dt class="text-muted-foreground">{{ t('settings.opml.unsupported') }}</dt>
                                <dd class="font-medium">{{ summary.unsupported }}</dd>
                            </div>
                            <div>
                                <dt class="text-muted-foreground">{{ t('settings.opml.invalid') }}</dt>
                                <dd class="font-medium">{{ summary.invalid }}</dd>
                            </div>
                            <div>
                                <dt class="text-muted-foreground">{{ t('settings.opml.categories') }}</dt>
                                <dd class="font-medium">{{ summary.categories }}</dd>
                            </div>
                        </dl>

                        <ul class="max-h-72 space-y-2 overflow-y-auto text-sm">
                            <li
                                v-for="feed in preview"
                                :key="feed.url"
                                class="flex flex-col gap-1 border-t border-border pt-2 sm:flex-row sm:justify-between"
                            >
                                <span class="font-medium"
                                    >{{ feed.name
                                    }}<span v-if="feed.category" class="font-normal text-muted-foreground"> · {{ feed.category }}</span></span
                                >
                                <span class="break-all text-muted-foreground">{{
                                    feed.already_subscribed ? t('settings.opml.already_subscribed') : feed.url
                                }}</span>
                            </li>
                        </ul>

                        <Button :disabled="summary.new_feeds === 0 || importing" @click="importFeeds">
                            {{ importing ? t('settings.opml.importing') : t('settings.opml.import', { count: summary.new_feeds }) }}
                        </Button>
                    </div>

                    <dl
                        v-if="importSummary"
                        class="grid grid-cols-2 gap-3 rounded-md border border-border p-4 text-sm sm:grid-cols-3 xl:grid-cols-4"
                        aria-live="polite"
                    >
                        <div>
                            <dt class="text-muted-foreground">{{ t('settings.opml.created') }}</dt>
                            <dd class="font-medium">{{ importSummary.created }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground">{{ t('settings.opml.existing_feeds') }}</dt>
                            <dd class="font-medium">{{ importSummary.existing }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground">{{ t('settings.opml.categories_created') }}</dt>
                            <dd class="font-medium">{{ importSummary.categories_created }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground">{{ t('settings.opml.duplicates') }}</dt>
                            <dd class="font-medium">{{ importSummary.duplicates }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground">{{ t('settings.opml.unsupported') }}</dt>
                            <dd class="font-medium">{{ importSummary.unsupported }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground">{{ t('settings.opml.invalid') }}</dt>
                            <dd class="font-medium">{{ importSummary.invalid }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground">{{ t('settings.opml.failures') }}</dt>
                            <dd class="font-medium">{{ importSummary.failures }}</dd>
                        </div>
                    </dl>
                </section>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
