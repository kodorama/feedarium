<script setup lang="ts">
import { useForm, Link, router, usePage } from '@inertiajs/vue3';
const category = usePage().props.category;
const form = useForm({ name: category?.name ?? '' });
function submit() {
    form.put(`/api/categories/${category.id}`, {
        onSuccess: () => router.visit('/categories'),
    });
}
</script>
<template>
    <div class="p-6 max-w-lg mx-auto">
        <h1 class="text-2xl font-bold mb-4">Edit Category</h1>
        <form @submit.prevent="submit" class="space-y-4">
            <div>
                <label class="block mb-1 font-semibold">Name</label>
                <input v-model="form.name" type="text" class="w-full border rounded px-3 py-2" />
                <div v-if="form.errors.name" class="text-red-500 text-sm">{{ form.errors.name }}</div>
            </div>
            <button type="submit" :disabled="form.processing" class="bg-primary text-white px-4 py-2 rounded-lg">Update</button>
            <Link href="/categories" class="ml-4 text-primary">Cancel</Link>
        </form>
    </div>
</template>

