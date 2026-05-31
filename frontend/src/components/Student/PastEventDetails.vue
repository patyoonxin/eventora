<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'

const route = useRoute()
const router = useRouter()
const eventId = route.params.id

// Component States
const event = ref(null)
const isLoading = ref(true)
const errorMessage = ref('')

onMounted(async () => {
  try {
    const token = localStorage.getItem('eventora_token')
    
    // We fetch from our PROTECTED past-events endpoint to get the ticket_number
    const response = await fetch(`http://localhost:8000/api/users/past-events`, {
      headers: {
        'Authorization': `Bearer ${token}`,
        'Accept': 'application/json'
      }
    })
    const json = await response.json()
    
    if (json.status === 'success') {
      const foundEvent = json.data.find(e => e.id == eventId)
      if (foundEvent) {
        event.value = foundEvent
      } else {
        throw new Error('Record not found in your history.')
      }
    } else {
      throw new Error(json.message)
    }
  } catch (err) {
    errorMessage.value = err.message || 'Failed to load details.'
  } finally {
    isLoading.value = false
  }
})

// UI Helpers (Same as eventDetails)
const tagsArray = computed(() => {
  if (!event.value || !event.value.category_tags) return []
  return event.value.category_tags.split(',').map(tag => tag.trim())
})

const getTagStyles = (tag) => {
  const cleanTag = tag.toLowerCase()
  const styleMap = {
    academic: 'bg-blue-100 text-blue-600 dark:bg-blue-950/40 dark:text-blue-300',
    sports: 'bg-emerald-100 text-emerald-600 dark:bg-emerald-950/40 dark:text-emerald-300',
    cultural: 'bg-amber-100 text-amber-600 dark:bg-amber-950/40 dark:text-amber-300',
    religious: 'bg-purple-100 text-purple-600 dark:bg-purple-950/40 dark:text-purple-300'
  }
  return styleMap[cleanTag] || 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300'
}

const formatDate = (dateString) => {
  if (!dateString) return ''
  const date = new Date(dateString)
  return date.toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric' })
}
</script>

<template>
  <div class="min-h-screen bg-white dark:bg-gray-900 pb-24">
    
    <div v-if="isLoading" class="text-center py-20 text-gray-500">Loading your history...</div>
    <div v-else-if="errorMessage" class="text-center py-20 text-red-500">{{ errorMessage }}</div>
    
    <div v-else class="max-w-xl mx-auto px-4 pt-4 flex flex-col min-h-[90vh] relative">
      
      <div class="relative w-full h-64 sm:h-72 bg-purple-200 dark:bg-purple-900 rounded-[2.5rem] overflow-hidden shadow-sm mb-6">
        <img 
          :src="event.image_path || 'https://via.placeholder.com/600x400?text=EventORA'" 
          class="w-full h-full object-cover"
        />
        <button @click="router.back()" class="absolute top-5 left-5 w-12 h-12 flex items-center justify-center bg-white dark:bg-gray-800 text-gray-800 dark:text-white rounded-full shadow-lg">
          <span class="text-xl font-bold">←</span>
        </button>
      </div>

      <div class="text-left space-y-3 px-1">
        <h2 class="text-2xl sm:text-3xl font-extrabold text-gray-900 dark:text-white tracking-tight uppercase">
          {{ event.title }}
        </h2>

        <div class="flex items-center space-x-2 text-gray-700 dark:text-gray-300">
          <span class="pi pi-user text-lg"></span>
          <span class="font-medium text-sm sm:text-base">{{ event.society_name }}</span>
        </div>

        <div class="flex flex-wrap gap-2 pt-1">
          <span v-for="(tag, index) in tagsArray" :key="index" :class="['px-3 py-1 text-xs font-bold rounded-full uppercase tracking-wider', getTagStyles(tag)]">
            {{ tag }}
          </span>
        </div>

        <p class="text-gray-400 dark:text-gray-400 text-sm sm:text-base leading-relaxed pt-2">
          {{ event.description }}
        </p>

        <div class="text-gray-400 dark:text-gray-500 text-xs sm:text-sm font-semibold pt-2 uppercase tracking-widest">
          <span>RM{{ parseFloat(event.price).toFixed(2) }}</span>
          <span class="mx-2">|</span>
          <span>{{ formatDate(event.starts_at) }}</span>
          <span class="mx-2">|</span>
          <span>{{ event.venue }}</span>
        </div>
      </div>

      <div class="mt-8 mb-4 bg-white dark:bg-gray-800 border-2 border-gray-100 dark:border-gray-700 rounded-[2rem] py-10 shadow-sm text-center">
        <div class="text-5xl sm:text-6xl font-black text-gray-900 dark:text-white tracking-tighter">
          {{ event.ticket_number }}
        </div>
        <div class="text-xs sm:text-sm font-bold text-gray-400 dark:text-gray-500 mt-2 uppercase tracking-[0.3em]">
          Ticket Number
        </div>
      </div>

      <div class="mt-auto pt-6 pb-6 grid grid-cols-2 gap-4">
        <button 
          type="button"
          class="flex-1 py-4 text-base sm:text-lg font-bold text-white tracking-widest uppercase bg-gradient-to-r from-blue-600 to-purple-500 dark:from-blue-500 dark:to-purple-500 rounded-2xl shadow-md shadow-purple-500/20 hover:opacity-95 transition-opacity active:scale-[0.99] focus:outline-none"
        >
          Feedback
        </button>

        <button 
          type="button"
          class="flex-1 py-4 text-base sm:text-lg font-bold text-white tracking-widest uppercase bg-gradient-to-r from-blue-600 to-purple-500 dark:from-blue-500 dark:to-purple-500 rounded-2xl shadow-md shadow-purple-500/20 hover:opacity-95 transition-opacity active:scale-[0.99] focus:outline-none"
        >
          Digital Cert
        </button>
      </div>

    </div>
  </div>
</template>