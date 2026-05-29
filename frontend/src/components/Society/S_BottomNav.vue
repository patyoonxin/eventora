<template>
  <nav class="sticky bottom-0 bg-gradient-to-r from-purple-600 to-blue-600 shadow-2xl z-50">
    <div class="flex justify-around items-center h-14">
      <!-- Home Link -->
      <router-link 
        to="/society/home" 
        class="flex flex-col items-center gap-0.5 py-1 px-3 rounded-lg transition-all duration-300 hover:scale-105"
        :class="isActive('/society/home') ? 'text-white scale-105' : 'text-white opacity-70 hover:opacity-100'"
      >
        <i class="pi pi-home text-lg"></i>
        <span class="text-[10px] font-medium">Home</span>
      </router-link>

      <!-- Scan QR -->
      <router-link 
        to="/society/scan-qr" 
        class="flex flex-col items-center gap-0.5 py-1 px-3 rounded-lg transition-all duration-300 hover:scale-105"
        :class="isActive('/society/scan-qr') ? 'text-white scale-105' : 'text-white opacity-70 hover:opacity-100'"
      >
        <i class="pi pi-qrcode text-lg"></i>
        <span class="text-[10px] font-medium">Scan QR</span>
      </router-link>

      <!-- Past Activities Link -->
      <router-link 
        to="/society/history" 
        class="flex flex-col items-center gap-0.5 py-1 px-3 rounded-lg transition-all duration-300 hover:scale-105"
        :class="isActive('/society/history') ? 'text-white scale-105' : 'text-white opacity-70 hover:opacity-100'"
      >
        <i class="pi pi-book text-lg"></i>
        <span class="text-[10px] font-medium">History</span>
      </router-link>

       <!-- Society Analytics Link -->
      <router-link 
        to="/society/analytics" 
        class="flex flex-col items-center gap-0.5 py-1 px-3 rounded-lg transition-all duration-300 hover:scale-105"
        :class="isActive('/society/analytics') ? 'text-white scale-105' : 'text-white opacity-70 hover:opacity-100'"
      >
        <i class="pi pi-chart-bar text-lg"></i>
        <span class="text-[10px] font-medium">Analytics</span>
      </router-link>
    </div>
  </nav>
</template>

<script setup>
import { useRoute,RouterLink } from 'vue-router'

const route = useRoute()

const isActive = (path) => {
  return route.path === path
}
</script>
