<template>
  <nav class="flex items-center justify-between px-6 py-4 bg-white shadow-lg">
    <div class="flex items-center">
      <img src="@/assets/logo.png" alt="Logo" class="h-8 w-auto" />
    </div>

    <div class="flex items-center gap-4">
      <router-link
        to="/notifications"
        class="relative flex items-center justify-center w-10 h-10 rounded-full bg-gray-100 hover:bg-gray-200"
      >
        <i class="pi pi-bell text-gray-700"></i>

        <span
          v-if="notifStore.unreadCount > 0"
          class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full px-1 min-w-[18px] text-center"
        >
          {{ notifStore.unreadCount }}
        </span>
      </router-link>

      <button
        class="flex items-center justify-center w-10 h-10 rounded-full bg-gray-100 hover:bg-gray-200"
      >
        <i class="pi pi-user text-gray-700"></i>
      </button>
    </div>
  </nav>
</template>

<script setup>
import { onMounted } from "vue";
import { useNotificationStore } from "@/stores/notificationStore";

const notifStore = useNotificationStore();

onMounted(() => {
  notifStore.fetchNotifications();
});
</script>