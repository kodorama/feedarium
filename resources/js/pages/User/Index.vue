<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
const users = usePage().props.users ?? [];
</script>
<template>
    <div class="p-6">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold">Users</h1>
            <Link href="/users/create" class="bg-primary text-white px-4 py-2 rounded-lg">Add User</Link>
        </div>
        <div v-if="users.length === 0" class="text-gray-500">No users found.</div>
        <ul v-else class="divide-y divide-gray-200">
            <li v-for="user in users" :key="user.id" class="py-2 flex justify-between items-center">
                <span>{{ user.name }}</span>
                <Link :href="`/users/${user.id}/edit`" class="text-primary">Edit</Link>
            </li>
        </ul>
    </div>
</template>

