<template>
  <nav class="sticky bottom-0 bg-gradient-to-r from-purple-600 to-blue-600 shadow-2xl z-50">
    <div class="flex justify-around items-center h-14">
      <!-- Home Link -->
      <router-link 
        to="/" 
        class="flex flex-col items-center gap-0.5 py-1 px-3 rounded-lg transition-all duration-300 hover:scale-105"
        :class="isActive('/') ? 'text-white scale-105' : 'text-white opacity-70 hover:opacity-100'"
      >
        <i class="pi pi-home text-lg"></i>
        <span class="text-[10px] font-medium">Home</span>
      </router-link>

      <!-- My Tickets Link -->
      <router-link 
        to="/tickets" 
        class="flex flex-col items-center gap-0.5 py-1 px-3 rounded-lg transition-all duration-300 hover:scale-105"
        :class="isActive('/tickets') ? 'text-white scale-105' : 'text-white opacity-70 hover:opacity-100'"
      >
        <i class="pi pi-ticket text-lg"></i>
        <span class="text-[10px] font-medium">MyTickets</span>
      </router-link>

      <!-- Past Activities Link -->
      <router-link 
        to="/history" 
        class="flex flex-col items-center gap-0.5 py-1 px-3 rounded-lg transition-all duration-300 hover:scale-105"
        :class="isActive('/history') ? 'text-white scale-105' : 'text-white opacity-70 hover:opacity-100'"
      >
        <i class="pi pi-book text-lg"></i>
        <span class="text-[10px] font-medium">History</span>
      </router-link>
    </div>
  </nav>
</template>

<script setup>
import { useRoute } from 'vue-router'

const route = useRoute()

const isActive = (path) => {
  return route.path === path
}
</script>
