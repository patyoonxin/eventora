// src/stores/auth.js
import { defineStore } from 'pinia'
import { ref, computed } from 'vue'

export const useAuthStore = defineStore('auth', () => {
  // 1. State (Global storage)
  const token = ref(localStorage.getItem('eventora_token') || null)
  const user = ref(JSON.parse(localStorage.getItem('eventora_user')) || null)
  
  // 2. Getters 
  const isLoggedIn = computed(() => !!token.value)
  const userRole = computed(() => user.value?.role || null)

  // 3. Actions (The exact API call moved from your component)
  const login = async (email, password) => {
    const API_BASE = import.meta.env.VITE_API_BASE_URL

    const response = await fetch(`${API_BASE}/api/login`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
      },
      body: JSON.stringify({ email, password }),
    })

    const data = await response.json()

    if (!response.ok) {
      throw new Error(data.error || "Invalid server credentials")
    }

    // Update global state
    token.value = data.token
    user.value = data.user

    // Browser persistence matching your design
    localStorage.setItem("eventora_token", data.token)
    localStorage.setItem("eventora_user", JSON.stringify(data.user))

    return data.user // Return the user object so the component can redirect
  }

  const logout = () => {
    token.value = null
    user.value = null
    localStorage.removeItem("eventora_token")
    localStorage.removeItem("eventora_user")
  }

  return {
    token,
    user,
    isLoggedIn,
    userRole,
    login,
    logout
  }
})