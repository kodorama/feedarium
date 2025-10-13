<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
const categories = usePage().props.categories ?? [];
</script>
<template>
    <div class="p-6">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold">Categories</h1>
            <Link href="/categories/create" class="bg-primary text-white px-4 py-2 rounded-lg">Add Category</Link>
        </div>
        <div v-if="categories.length === 0" class="text-gray-500">No categories found.</div>
        <ul v-else class="divide-y divide-gray-200">
            <li v-for="cat in categories" :key="cat.id" class="py-2 flex justify-between items-center">
                <span>{{ cat.name }}</span>
                <Link :href="`/categories/${cat.id}/edit`" class="text-primary">Edit</Link>
            </li>
        </ul>
    </div>
</template>

