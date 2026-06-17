<template>
  <nav class="flex items-center justify-between px-6 py-4 bg-white shadow-lg">
    <div class="flex items-center">
      <img src="@/assets/logo.png" alt="Logo" class="h-8 w-auto" />
    </div>

    <div class="flex items-center gap-3">
      <button
        @click="goToProfile"
        class="flex items-center justify-center w-10 h-10 rounded-full bg-gray-100 hover:bg-gray-200 transition-colors duration-200 overflow-hidden"
        title="Profile"
      >
        <img
          v-if="userAvatar"
          :src="userAvatar"
          alt="Profile"
          class="w-10 h-10 rounded-full object-cover"
        />
        <i v-else class="pi pi-user text-gray-700"></i>
      </button>

      <button
        @click="handleLogout"
        class="flex items-center gap-2 px-4 py-2 rounded-full bg-red-50 hover:bg-red-100 text-red-500 text-sm font-medium transition-colors duration-200"
        title="Sign Out"
      >
        <i class="pi pi-sign-out"></i>
        <span>Sign Out</span>
      </button>
    </div>
  </nav>
</template>

<script>
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { computed } from 'vue'

export default {
  name: 'A_UpperNav',

  setup() {
    const router = useRouter()
    const authStore = useAuthStore()

    const userAvatar = computed(() => authStore.user?.profile_picture || null)

    function goToProfile() {
      router.push('/profile')
    }

    function handleLogout() {
      authStore.logout()
      router.push('/login')
    }

    return { userAvatar, goToProfile, handleLogout }
  }
}
</script>

<style scoped>
</style>