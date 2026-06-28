<script setup>
import { useRouter } from "vue-router";
const API_BASE = import.meta.env.VITE_API_BASE_URL;

// Define incoming dataset from our parent page (A_Home.vue)
const props = defineProps({
  events: {
    type: Array,
    required: true
  }
});

const router = useRouter();
</script>

<template>
  <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-800/50 divide-y divide-gray-100 dark:divide-gray-800 text-left max-w-4xl mx-auto">
    <div 
      v-for="(event, index) in props.events" 
      :key="event.id"
      class="p-5 flex items-center justify-between gap-4 transition-all duration-200 hover:bg-slate-50/50 dark:hover:bg-slate-800/30 cursor-pointer"
      @click="router.push(`/admin/events/${event.id}`)"
    >
      <div class="flex items-center gap-4">
        <img
          :src="
            event.image_path
              ? `${API_BASE}/${event.image_path}`
              : 'https://images.unsplash.com/photo-1511578314322-379afb476865'
          "
          :alt="event.title" 
          class="w-16 h-12 rounded-xl flex-shrink-0 shadow-sm object-cover" 
        />
        
        <div>
          <h3 class="text-base font-bold text-slate-800 dark:text-white leading-snug hover:text-purple-600 transition-colors">
            {{ event.title }}
          </h3>
          <p class="text-xs sm:text-sm font-medium text-slate-400 dark:text-slate-500 mt-0.5">
            By {{ event.society_name  }}
          </p>
        </div>
      </div>

      <div class="text-gray-300 dark:text-gray-600 pr-2">
        <span class="text-xl">➔</span>
      </div>
    </div>
  </div>
</template>