<template>
  <div class="max-w-2xl mx-auto p-4">
    <div class="flex items-center gap-4 mb-6">
      <button 
        @click="router.back()"
        class="w-10 h-10 flex items-center justify-center bg-[var(--bg)] text-[var(--text-h)] border border-[var(--border)] rounded-full shadow-sm hover:scale-105 active:scale-95 transition-transform focus:outline-none"
        aria-label="Go back"
      >
        <span class="text-xl font-bold">←</span>
      </button>
      
      <h2 class="text-2xl font-bold text-[var(--text-h)]">Notifications</h2>
    </div>
    
    <div v-if="store.notifications.length === 0" class="text-center py-8 text-[var(--text)] border-2 border-dashed border-[var(--border)] rounded-xl">
      No notifications yet!
    </div>

    <div v-else class="space-y-3">
      <div 
        v-for="item in store.notifications" 
        :key="item.id" 
        :class="[
          'p-4 rounded-xl border border-[var(--border)] bg-[var(--bg)] shadow-sm transition-all duration-200 cursor-pointer hover:translate-y-[-2px] hover:shadow-md',
          { 'bg-[var(--accent-bg)] border-l-4 border-l-[var(--accent)]': !item.is_read }
        ]"
        @click="handleNotificationClick(item)"
      >
        <div class="flex justify-between items-center text-xs text-[var(--text)] mb-2">
          <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-[var(--code-bg)] text-[var(--text-h)] uppercase tracking-wider">
            {{ item.type }}
          </span>
          <small class="text-[var(--text)]">{{ new Date(item.created_at).toLocaleDateString() }}</small>
        </div>
        
        <h3 class="font-semibold text-[var(--text-h)] mb-1">{{ item.title }}</h3>
        <p class="text-sm text-[var(--text)] leading-relaxed">{{ item.message }}</p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { onMounted } from 'vue';
import { useRouter } from 'vue-router'; // <-- Added this
import { useNotificationStore } from '@/stores/notificationStore';

const store = useNotificationStore();
const router = useRouter(); // <-- Added this

onMounted(() => {
  store.fetchNotifications();
});

const handleNotificationClick = (item) => {
  if (!item.is_read) {
    store.markAsRead(item.id);
  }
};
</script>