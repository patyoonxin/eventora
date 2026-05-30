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

// Temporary mocked registrant count since backend registration isn't built yet
const currentRegistrants = ref(30) 

onMounted(async () => {
  try {
    // Fetch a single event's details from the backend
    const response = await fetch(`http://localhost:8000/api/events`)
    const json = await response.json()
    
    if (json.status === 'success') {
      // Find the specific event in our dataset that matches the route ID
      const foundEvent = json.data.find(e => e.id == eventId)
      if (foundEvent) {
        event.value = foundEvent
      } else {
        throw new Error('Event not found')
      }
    } else {
      throw new Error(json.message)
    }
  } catch (err) {
    errorMessage.value = 'Failed to load event details.'
    console.error(err)
  } finally {
    isLoading.value = false
  }
})

// Splitting comma-separated tags into individual elements
const tagsArray = computed(() => {
  if (!event.value || !event.value.category_tags) return []
  return event.value.category_tags.split(',').map(tag => tag.trim())
})

// Reusing your category tag coloring logic
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

// Formatting date and prices helpers
const formatDate = (dateString) => {
  if (!dateString) return ''
  const date = new Date(dateString)
  return date.toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric' })
}

const formatPrice = (price) => {
  return parseFloat(price) === 0 ? 'RM0' : `RM${parseFloat(price).toFixed(2)}`
}

const handleRegisterClick = () => {
  alert('Registration backend coming soon!')
}
</script>

<template>
  <div class="min-h-screen bg-white dark:bg-gray-900 pb-24">
    
    <div v-if="isLoading" class="text-center py-20 text-gray-500">Loading details...</div>
    <div v-else-if="errorMessage" class="text-center py-20 text-red-500">{{ errorMessage }}</div>
    
    <div v-else class="max-w-xl mx-auto px-4 pt-4 flex flex-col min-h-[90vh] relative">
      
      <div class="relative w-full h-64 sm:h-72 bg-purple-200 dark:bg-purple-900 rounded-[2.5rem] overflow-hidden shadow-sm mb-6">
        <img 
          :src="event.image_path || 'https://via.placeholder.com/600x400?text=EventORA'" 
          :alt="event.title"
          class="w-full h-full object-cover"
        />
        
        <button 
          @click="router.back()"
          class="absolute top-5 left-5 w-12 h-12 flex items-center justify-center bg-white dark:bg-gray-800 text-gray-800 dark:text-white rounded-full shadow-lg hover:scale-105 active:scale-95 transition-transform focus:outline-none"
          aria-label="Go back"
        >
          <span class="text-xl font-bold">←</span>
        </button>
      </div>

      <div class="text-left space-y-3 px-1 flex-grow">
        <h2 class="text-2xl sm:text-3xl font-extrabold text-gray-900 dark:text-white tracking-tight uppercase">
          {{ event.title }}
        </h2>

        <div class="flex items-center space-x-2 text-gray-700 dark:text-gray-300">
          <span class="pi pi-user text-lg"></span>
          <span class="font-medium text-sm sm:text-base">{{ event.society_name || 'Computing Club' }}</span>
        </div>

        <div class="flex flex-wrap gap-2 pt-1">
          <span 
            v-for="(tag, index) in tagsArray" 
            :key="index"
            :class="['px-3 py-1 text-xs font-bold rounded-full uppercase tracking-wider', getTagStyles(tag)]"
          >
            {{ tag }}
          </span>
        </div>

        <p class="text-gray-400 dark:text-gray-400 text-sm sm:text-base leading-relaxed pt-2">
          {{ event.description || 'No detailed description available for this event yet.' }}
        </p>

        <div class="text-gray-400 dark:text-gray-500 text-sm sm:text-base font-semibold pt-2">
          <span>{{ formatPrice(event.price) }}</span>
          <span class="mx-2">|</span>
          <span>{{ formatDate(event.starts_at) }}</span>
          <span class="mx-2">|</span>
          <span>{{ event.venue }}</span>
        </div>
      </div>

      <div class="mt-8 pt-4 pb-6 flex items-center gap-4 bg-white dark:bg-gray-900 border-t border-gray-50 dark:border-gray-800">
        
        <div class="flex items-center justify-center gap-1.5 px-5 py-4 bg-purple-100 dark:bg-purple-950/60 rounded-2xl min-w-[110px] sm:min-w-[130px]">
          <span class="pi pi-users text-lg text-gray-700 dark:text-gray-300"></span>
          <span class="text-xl sm:text-xl font-semibold text-gray-800 dark:text-gray-200">
            {{ currentRegistrants }}/{{ event.capacity || 50 }}
          </span>
        </div>

        <button 
          @click="handleRegisterClick"
          type="button"
          class="flex-1 py-4 text-base sm:text-lg font-bold text-white tracking-widest uppercase bg-gradient-to-r from-blue-600 to-purple-500 dark:from-blue-500 dark:to-purple-500 rounded-2xl shadow-md shadow-purple-500/20 hover:opacity-95 transition-opacity active:scale-[0.99] focus:outline-none"
        >
          Register
        </button>

      </div>

    </div>
  </div>
</template>