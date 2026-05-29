<template>
  <nav class="sticky bottom-0 bg-gradient-to-r from-purple-600 to-blue-600 shadow-2xl z-50">
    <div class="flex justify-around items-center h-14">
      <!-- Home Link -->
      <router-link 
        to="/admin/home" 
        class="flex flex-col items-center gap-0.5 py-1 px-3 rounded-lg transition-all duration-300 hover:scale-105"
        :class="isActive('/admin/home') ? 'text-white scale-105' : 'text-white opacity-70 hover:opacity-100'"
      >
        <i class="pi pi-home text-lg"></i>
        <span class="text-[10px] font-medium">Home</span>
      </router-link>

      <!-- All Events Link -->
      <router-link 
        to="/admin/all-events" 
        class="flex flex-col items-center gap-0.5 py-1 px-3 rounded-lg transition-all duration-300 hover:scale-105"
        :class="isActive('/admin/all-events') ? 'text-white scale-105' : 'text-white opacity-70 hover:opacity-100'"
      >
        <i class="pi pi-book text-lg"></i>
        <span class="text-[10px] font-medium">All Events</span>
      </router-link>

      <!-- Society Analytics Link -->
      <router-link 
        to="/admin/analytics" 
        class="flex flex-col items-center gap-0.5 py-1 px-3 rounded-lg transition-all duration-300 hover:scale-105"
        :class="isActive('/admin/analytics') ? 'text-white scale-105' : 'text-white opacity-70 hover:opacity-100'"
      >
        <i class="pi pi-chart-bar text-lg"></i>
        <span class="text-[10px] font-medium">Society Analytics</span>
      </router-link>

      <!-- User Management Link -->
      <router-link 
        to="/admin/users" 
        class="flex flex-col items-center gap-0.5 py-1 px-3 rounded-lg transition-all duration-300 hover:scale-105"
        :class="isActive('/admin/users') ? 'text-white scale-105' : 'text-white opacity-70 hover:opacity-100'"
      >
        <i class="pi pi-users text-lg"></i>
        <span class="text-[10px] font-medium">Users</span>
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
