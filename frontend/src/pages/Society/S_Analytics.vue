<script setup>
// 1. Give the imported component a name (e.g., SocietyAnalytics)
import SocietyAnalytics from "@/components/Society/SocietyAnalytics.vue";
</script>

<template>
  <div class="min-h-screen p-4 sm:p-8 max-w-7xl mx-auto">
    <div class="mb-6 text-left items-center justify-between pl-2">
      <h2 class="text-3xl font-extrabold text-gray-900 dark:text-white">
        Analytics Dashboard
      </h2>
      <p class="text-sm text-gray-400 mt-1">Real-time engagement metrics for your active events</p>
    </div>

    <div class="space-y-6">
      <SocietyAnalytics />
    </div>
  </div>
</template>