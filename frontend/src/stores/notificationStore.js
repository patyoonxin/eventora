import { defineStore } from 'pinia';
import axios from 'axios';
import { useAuthStore } from "@/stores/auth"; 

const API_BASE = import.meta.env.VITE_API_BASE_URL;

export const useNotificationStore = defineStore('notification', {
  state: () => ({
    notifications: []
  }),

  getters: {
    unreadCount: (state) =>
      Array.isArray(state.notifications)
        ? state.notifications.filter(n => !n.is_read).length
        : 0
  },

  actions: {
    async fetchNotifications() {
      try {
        const authStore = useAuthStore();

        const response = await axios.get(
          `${API_BASE}/api/notifications`,
          {
            headers: {
              Authorization: `Bearer ${authStore.token}`,
              Accept: 'application/json'
            }
          }
        );

        console.log(response.data);

        this.notifications =
          response.data?.data ||
          response.data?.notifications ||
          response.data ||
          [];
      } catch (error) {
        console.error("Failed to load notifications", error);
        this.notifications = [];
      }
    },

    async markAsRead(id) {
      try {
        const authStore = useAuthStore();

        await axios.put(
          `${API_BASE}/api/notifications/${id}/read`,
          {},
          {
            headers: {
              Authorization: `Bearer ${authStore.token}`,
              Accept: 'application/json'
            }
          }
        );

        const target = this.notifications.find(n => n.id === id);
        if (target) target.is_read = 1;

      } catch (error) {
        console.error("Failed to update notification status", error);
      }
    }
  }
});