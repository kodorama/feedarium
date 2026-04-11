<script setup lang="ts">
import HeadingSmall from '@/components/HeadingSmall.vue';
import { Label } from '@/components/ui/label';
import { useArticleContent } from '@/composables/useArticleContent';
import { useAutoAdvance } from '@/composables/useAutoAdvance';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
const { autoAdvanceEnabled, setAutoAdvance } = useAutoAdvance();
const { rawMode, setRawModeDefault } = useArticleContent();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Settings', href: '/settings/profile' },
    { title: 'Reading', href: '/settings/reading' },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Reading — Settings" />

        <SettingsLayout>
            <div class="space-y-6">
                <!-- Auto-advance section -->
                <div class="space-y-4">
                    <HeadingSmall :title="t('general.auto_advance')" :description="t('general.auto_advance_desc')" />

                    <div class="flex items-center gap-3">
                        <input
                            id="auto-advance"
                            type="checkbox"
                            class="h-4 w-4 cursor-pointer rounded border-border accent-primary"
                            :checked="autoAdvanceEnabled"
                            @change="setAutoAdvance(($event.target as HTMLInputElement).checked)"
                        />
                        <Label for="auto-advance" class="cursor-pointer font-normal">
                            {{ t('general.auto_advance') }}
                        </Label>
                    </div>
                </div>

                <!-- Plain text mode section -->
                <div class="space-y-4 border-t border-border pt-6">
                    <HeadingSmall :title="t('general.plain_text_mode')" :description="t('general.plain_text_mode_desc')" />

                    <div class="flex items-center gap-3">
                        <input
                            id="plain-text-mode"
                            type="checkbox"
                            class="h-4 w-4 cursor-pointer rounded border-border accent-primary"
                            :checked="rawMode"
                            @change="setRawModeDefault(($event.target as HTMLInputElement).checked)"
                        />
                        <Label for="plain-text-mode" class="cursor-pointer font-normal">
                            {{ t('general.plain_text_mode_label') }}
                        </Label>
                    </div>
                </div>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
