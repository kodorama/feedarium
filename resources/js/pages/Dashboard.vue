<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import PlaceholderPattern from '../components/PlaceholderPattern.vue';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
];
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <!-- Button group for adding entities -->
            <div class="flex gap-4 mb-4">
                <Link href="/categories/create" class="inline-flex items-center rounded-lg bg-primary px-4 py-2 text-white font-semibold shadow hover:bg-primary/90 focus:outline-none focus:ring-2 focus:ring-primary/50">
                    Add Category
                </Link>
                <Link href="/feeds/create" class="inline-flex items-center rounded-lg bg-primary px-4 py-2 text-white font-semibold shadow hover:bg-primary/90 focus:outline-none focus:ring-2 focus:ring-primary/50">
                    Add Feed
                </Link>
                <Link href="/users/create" class="inline-flex items-center rounded-lg bg-primary px-4 py-2 text-white font-semibold shadow hover:bg-primary/90 focus:outline-none focus:ring-2 focus:ring-primary/50">
                    Add User
                </Link>
            </div>
            <div class="grid auto-rows-min gap-4 md:grid-cols-3">
                <div class="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <PlaceholderPattern />
                </div>
                <div class="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <PlaceholderPattern />
                </div>
                <div class="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <PlaceholderPattern />
                </div>
            </div>
            <div class="relative min-h-[100vh] flex-1 rounded-xl border border-sidebar-border/70 md:min-h-min dark:border-sidebar-border">
                <PlaceholderPattern />
            </div>
        </div>
    </AppLayout>
</template>
