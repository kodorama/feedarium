<script setup lang="ts">
import { useFeedNotifications } from '../../composables/useFeedNotifications'
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'

const { feedUpdated, resetNotification } = useFeedNotifications()
const feeds = ref([]) // Replace with actual Inertia prop or API call

function refreshFeeds() {
  router.reload({ only: ['feeds'] })
  resetNotification()
}
</script>

<template>
  <div class="p-8">
    <h1 class="text-2xl font-bold mb-4">RSS Feeds</h1>
    <div v-if="feedUpdated" class="mb-4">
      <div class="bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded flex items-center justify-between">
        <span>Feed updated! Please refresh to see the latest changes.</span>
        <button @click="refreshFeeds" class="ml-4 px-3 py-1 bg-blue-600 text-white rounded">Refresh</button>
      </div>
    </div>
    <ul class="space-y-2">
      <li v-for="feed in feeds" :key="feed.id" class="p-4 bg-white dark:bg-gray-800 rounded shadow">
        <div class="font-semibold">{{ feed.name }}</div>
        <div class="text-sm text-gray-500">{{ feed.url }}</div>
        <div class="text-sm">{{ feed.description }}</div>
      </li>
    </ul>
  </div>
</template>
