<script setup>
import { RouterView, useRoute } from 'vue-router'
import { computed } from 'vue'

// Import Student navigation components
import UpperNav from './components/Student/UpperNav.vue'
import BottomNav from './components/Student/BottomNav.vue'

// Import Society navigation components
import S_UpperNav from './components/Society/S_UpperNav.vue'
import S_BottomNav from './components/Society/S_BottomNav.vue'

// Import Admin navigation components
import A_UpperNav from './components/Admin/A_UpperNav.vue'
import A_BottomNav from './components/Admin/A_BottomNav.vue'

const route = useRoute()
const currentRole = computed(() => route.meta.role)
</script>

<template>
  <main class="app-container">
    <A_UpperNav v-if="currentRole === 'admin'" />
    <UpperNav v-if="currentRole === 'student'" />
    <S_UpperNav v-if="currentRole === 'society'" />

    <main class="content-viewport">
      <RouterView />
    </main>

    <A_BottomNav v-if="currentRole === 'admin'" />
    <BottomNav v-if="currentRole === 'student'" />
    <S_BottomNav v-if="currentRole === 'society'" />
  </main>
</template>