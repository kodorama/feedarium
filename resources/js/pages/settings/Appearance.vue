<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

import AppearanceTabs from '@/components/AppearanceTabs.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import { type BreadcrumbItem } from '@/types';

import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';

const { t } = useI18n();

const breadcrumbItems = computed<BreadcrumbItem[]>(() => [
    { title: t('settings.breadcrumb.appearance'), href: '/settings/appearance' },
]);
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="t('settings.breadcrumb.appearance')" />

        <SettingsLayout>
            <div class="space-y-6">
                <HeadingSmall :title="t('settings.appearance.title')" :description="t('settings.appearance.description')" />
                <AppearanceTabs />
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
