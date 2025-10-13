<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'

const form = useForm({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
})

function submit() {
  form.post('/register-admin')
}
</script>

<template>
  <div class="min-h-screen flex items-center justify-center bg-gray-50 dark:bg-gray-900">
    <form @submit.prevent="submit" class="w-full max-w-md p-8 bg-white dark:bg-gray-800 rounded shadow">
      <h1 class="text-2xl font-bold mb-6 text-center">Admin Registration</h1>
      <div class="mb-4">
        <label class="block mb-1 font-medium" for="name">Name</label>
        <input id="name" v-model="form.name" type="text" class="w-full px-3 py-2 border rounded" />
        <div v-if="form.errors.name" class="text-red-500 text-sm mt-1">{{ form.errors.name }}</div>
      </div>
      <div class="mb-4">
        <label class="block mb-1 font-medium" for="email">Email</label>
        <input id="email" v-model="form.email" type="email" class="w-full px-3 py-2 border rounded" />
        <div v-if="form.errors.email" class="text-red-500 text-sm mt-1">{{ form.errors.email }}</div>
      </div>
      <div class="mb-4">
        <label class="block mb-1 font-medium" for="password">Password</label>
        <input id="password" v-model="form.password" type="password" class="w-full px-3 py-2 border rounded" />
        <div v-if="form.errors.password" class="text-red-500 text-sm mt-1">{{ form.errors.password }}</div>
      </div>
      <div class="mb-6">
        <label class="block mb-1 font-medium" for="password_confirmation">Confirm Password</label>
        <input id="password_confirmation" v-model="form.password_confirmation" type="password" class="w-full px-3 py-2 border rounded" />
      </div>
      <button type="submit" :disabled="form.processing" class="w-full py-2 px-4 bg-blue-600 text-white rounded hover:bg-blue-700">Register</button>
    </form>
  </div>
</template>

