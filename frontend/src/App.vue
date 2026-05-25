<script setup>
import { RouterView, useRoute } from 'vue-router'
import { computed } from 'vue'
import UpperNav from './components/Student/UpperNav.vue'
import BottomNav from './components/Student/BottomNav.vue'

const route = useRoute()
const showNav = computed(() => !route.meta.hideNav)
</script>

<template>
  <main class="app-container">
    <UpperNav v-if="showNav" />
    <RouterView />
    <BottomNav v-if="showNav" />
  </main>
</template>