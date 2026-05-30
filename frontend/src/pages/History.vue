<script setup>
import { ref, onMounted } from 'vue'
import EventCard from '@/components/Student/EventCard.vue'

const pastEvents = ref([])
const isLoading = ref(true)

onMounted(async () => {
  try {
    const response = await fetch('http://localhost:8000/api/users/past-events')
    const json = await response.json()
    if (json.status === 'success') {
      pastEvents.value = json.data
    }
  } catch (err) {
    console.error('Error fetching past history:', err)
  } finally {
    isLoading.value = false
  }
})
</script>

<template>
  <div class="p-8 max-w-7xl mx-auto text-left">
    <h2 class="text-3xl font-extrabold text-gray-900 dark:text-white mb-2">My Past Events</h2>
    <p class="text-sm text-gray-500 mb-8">Review the campus workshops and programs you successfully attended.</p>

    <div v-if="isLoading" class="text-center py-12 text-gray-400">Loading history data...</div>

    <div v-else-if="pastEvents.length === 0" class="text-center py-12 text-gray-400 border-2 border-dashed rounded-2xl">
      You haven't attended any events yet!
    </div>

    <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
      <EventCard 
        v-for="item in pastEvents" 
        :key="item.id" 
        :event="item"
        :ticketNumber="item.ticket_number" />
    </div>
  </div>
</template>