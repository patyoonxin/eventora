// src/stores/auth.js
import { defineStore } from "pinia";
import { ref, computed } from "vue";
import { useNotificationStore } from "./notificationStore";
import router from "@/router";

export const useAuthStore = defineStore("auth", () => {
  // 1. State (Global storage)
  const token = ref(localStorage.getItem("eventora_token") || null);
  const user = ref(JSON.parse(localStorage.getItem("eventora_user")) || null);

  // 2. Getters
  const isLoggedIn = computed(() => !!token.value);
  const userRole = computed(() => user.value?.role || null);

  // 3. Actions (The exact API call moved from your component)
  const login = async (email, password) => {
    const API_BASE = import.meta.env.VITE_API_BASE_URL;

    const response = await fetch(`${API_BASE}/api/login`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
      },
      body: JSON.stringify({ email, password }),
    });

    const data = await response.json();

    if (!response.ok) {
      throw new Error(data.error || "Invalid server credentials");
    }

    // Update global state
    token.value = data.token;
    user.value = data.user;

    // Browser persistence matching your design
    localStorage.setItem("eventora_token", data.token);
    localStorage.setItem("eventora_user", JSON.stringify(data.user));

    // --- TRIGGER RECOMMENDATION NON-BLOCKING ---
    // Do NOT use 'await' here so the user can enter the application instantly!
    // We pass the fresh token in the Authorization Header for JwtAuthMiddleware.
    const notifStore = useNotificationStore();
    
    fetch(`${API_BASE}/api/notifications/generate-recommendations`, {
      method: "POST",
      headers: {
        "Authorization": `Bearer ${data.token}`,
        "Accept": "application/json"
      }
    })
    .then(() => notifStore.fetchNotifications()) // Fetch updated list once generated
    .catch((err) => console.error("Silent recommendation check failed:", err));

    return data.user; // Return the user object so the component can redirect
  };

  const logout = () => {
    token.value = null;
    user.value = null;
    localStorage.removeItem("eventora_token");
    localStorage.removeItem("eventora_user");
  };

  return {
    token,
    user,
    isLoggedIn,
    userRole,
    login,
    logout,
  };
});
