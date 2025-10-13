<template>
    <div class="p-6 max-w-lg mx-auto">
        <h1 class="text-2xl font-bold mb-4">Edit Feed</h1>
        <form @submit.prevent="submit" class="space-y-4">
            <div>
                <label class="block mb-1 font-semibold">Title</label>
                <input v-model="form.title" type="text" class="w-full border rounded px-3 py-2" />
                <div v-if="form.errors.title" class="text-red-500 text-sm">{{ form.errors.title }}</div>
            </div>
            <div>
                <label class="block mb-1 font-semibold">URL</label>
                <input v-model="form.url" type="url" class="w-full border rounded px-3 py-2" />
                <div v-if="form.errors.url" class="text-red-500 text-sm">{{ form.errors.url }}</div>
            </div>
            <button type="submit" :disabled="form.processing" class="bg-primary text-white px-4 py-2 rounded-lg">Update</button>
            <Link href="/feeds" class="ml-4 text-primary">Cancel</Link>
        </form>
    </div>
</template>

<script setup lang="ts">
import { useForm, Link, router, usePage } from '@inertiajs/vue3';
const feed = usePage().props.feed;
const form = useForm({ title: feed?.title ?? '', url: feed?.url ?? '' });
function submit() {
    form.put(`/api/feeds/${feed.id}`, {
        onSuccess: () => router.visit('/feeds'),
    });
}
</script>
