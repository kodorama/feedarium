<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

function submit() {
    form.post('/register-admin');
}
</script>

<template>
    <div class="flex min-h-screen items-center justify-center bg-gray-50 dark:bg-gray-900">
        <form @submit.prevent="submit" class="w-full max-w-md rounded bg-white p-8 shadow dark:bg-gray-800">
            <h1 class="mb-6 text-center text-2xl font-bold">Admin Registration</h1>
            <div class="mb-4">
                <label class="mb-1 block font-medium" for="name">Name</label>
                <input id="name" v-model="form.name" type="text" class="w-full rounded border px-3 py-2" />
                <div v-if="form.errors.name" class="mt-1 text-sm text-red-500">{{ form.errors.name }}</div>
            </div>
            <div class="mb-4">
                <label class="mb-1 block font-medium" for="email">Email</label>
                <input id="email" v-model="form.email" type="email" class="w-full rounded border px-3 py-2" />
                <div v-if="form.errors.email" class="mt-1 text-sm text-red-500">{{ form.errors.email }}</div>
            </div>
            <div class="mb-4">
                <label class="mb-1 block font-medium" for="password">Password</label>
                <input id="password" v-model="form.password" type="password" class="w-full rounded border px-3 py-2" />
                <div v-if="form.errors.password" class="mt-1 text-sm text-red-500">{{ form.errors.password }}</div>
            </div>
            <div class="mb-6">
                <label class="mb-1 block font-medium" for="password_confirmation">Confirm Password</label>
                <input id="password_confirmation" v-model="form.password_confirmation" type="password" class="w-full rounded border px-3 py-2" />
            </div>
            <button type="submit" :disabled="form.processing" class="w-full rounded bg-blue-600 px-4 py-2 text-white hover:bg-blue-700">
                Register
            </button>
        </form>
    </div>
</template>
