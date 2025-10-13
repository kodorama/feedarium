<script setup lang="ts">
import { useForm, Link, router, usePage } from '@inertiajs/vue3';
const user = usePage().props.user;
const form = useForm({ name: user?.name ?? '', email: user?.email ?? '' });
function submit() {
    form.put(`/api/users/${user.id}`, {
        onSuccess: () => router.visit('/users'),
    });
}
</script>
<template>
    <div class="p-6 max-w-lg mx-auto">
        <h1 class="text-2xl font-bold mb-4">Edit User</h1>
        <form @submit.prevent="submit" class="space-y-4">
            <div>
                <label class="block mb-1 font-semibold">Name</label>
                <input v-model="form.name" type="text" class="w-full border rounded px-3 py-2" />
                <div v-if="form.errors.name" class="text-red-500 text-sm">{{ form.errors.name }}</div>
            </div>
            <div>
                <label class="block mb-1 font-semibold">Email</label>
                <input v-model="form.email" type="email" class="w-full border rounded px-3 py-2" />
                <div v-if="form.errors.email" class="text-red-500 text-sm">{{ form.errors.email }}</div>
            </div>
            <button type="submit" :disabled="form.processing" class="bg-primary text-white px-4 py-2 rounded-lg">Update</button>
            <Link href="/users" class="ml-4 text-primary">Cancel</Link>
        </form>
    </div>
</template>

