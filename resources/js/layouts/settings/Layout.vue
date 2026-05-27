<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
const page = usePage();

const isAdmin = computed(() => (page.props.auth?.user as any)?.is_admin === true);

const sidebarNavItems = computed<NavItem[]>(() => [
    { title: t('settings.nav.profile'), href: '/settings/profile' },
    { title: t('settings.nav.password'), href: '/settings/password' },
    { title: t('settings.nav.appearance'), href: '/settings/appearance' },
    { title: t('settings.nav.reading'), href: '/settings/reading' },
    { title: t('settings.nav.categories'), href: '/settings/categories' },
    { title: t('settings.nav.feeds'), href: '/settings/feeds' },
    ...(isAdmin.value
        ? [
              { title: t('settings.nav.search'), href: '/settings/search' },
              { title: t('settings.nav.advanced'), href: '/settings/advanced' },
          ]
        : []),
]);

const currentPath = page.props.ziggy?.location ? new URL(page.props.ziggy.location).pathname : '';
</script>

<template>
    <div class="px-4 py-6">
        <Heading :title="t('settings.title')" :description="t('settings.description')" />

        <div class="flex flex-col space-y-8 md:space-y-0 lg:flex-row lg:space-y-0 lg:space-x-12">
            <aside class="w-full max-w-xl lg:w-48">
                <nav class="flex flex-col space-y-1 space-x-0">
                    <Button
                        v-for="item in sidebarNavItems"
                        :key="item.href"
                        variant="ghost"
                        :class="['w-full justify-start', { 'bg-muted': currentPath === item.href }]"
                        as-child
                    >
                        <Link :href="item.href">
                            {{ item.title }}
                        </Link>
                    </Button>
                </nav>
            </aside>

            <Separator class="my-6 md:hidden" />

            <div class="flex-1 md:max-w-2xl">
                <section class="max-w-xl space-y-12">
                    <slot />
                </section>
            </div>
        </div>
    </div>
</template>
