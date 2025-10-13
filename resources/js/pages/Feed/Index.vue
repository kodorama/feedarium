<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
const feeds = usePage().props.feeds ?? [];
</script>
<template>
    <div class="p-6">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold">Feeds</h1>
            <Link href="/feeds/create" class="bg-primary text-white px-4 py-2 rounded-lg">Add Feed</Link>
        </div>
        <div v-if="feeds.length === 0" class="text-gray-500">No feeds found.</div>
        <ul v-else class="divide-y divide-gray-200">
            <li v-for="feed in feeds" :key="feed.id" class="py-2 flex justify-between items-center">
                <span>{{ feed.title }}</span>
                <Link :href="`/feeds/${feed.id}/edit`" class="text-primary">Edit</Link>
            </li>
        </ul>
    </div>
</template>

