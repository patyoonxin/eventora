<script setup>
import { ref, onMounted } from 'vue'
import EventCard from '@/components/Student/EventCard.vue'

const events = ref([])
const isLoading = ref(true)
const errorMessage = ref('')

onMounted(async () => {
  try {
    const response = await fetch('http://localhost:8000/api/events')
    const json = await response.json()
    
    if (json.status === 'success') {
      events.value = json.data
    } else {
      throw new Error(json.message)
    }
  } catch (err) {
    errorMessage.value = 'Failed to load events.'
    console.error(err)
  } finally {
    isLoading.value = false
  }
})
</script>

<template>
  <div class="p-8 max-w-7xl mx-auto">
    <h2 class="text-3xl font-extrabold text-gray-900 dark:text-white mb-6">
      Explore Upcoming Events
    </h2>

    <div v-if="isLoading" class="text-center text-gray-500 py-12">
      Loading amazing student activities...
    </div>

    <div v-else-if="errorMessage" class="text-red-500 bg-red-50 p-4 rounded-xl mb-6">
      {{ errorMessage }}
    </div>

    <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8 justify-items-center">
      <EventCard 
        v-for="item in events" 
        :key="item.id" 
        :event="item" 
      />
    </div>
  </div>
</template>