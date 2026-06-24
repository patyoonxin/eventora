// src/stores/auth.js
import { defineStore } from "pinia";
import { ref, computed } from "vue";
import { useNotificationStore } from "./notificationStore";
import router from "@/router";

export const useAuthStore = defineStore('auth', () => {

  const API_BASE = import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000'

  // 1. State (Global storage)
  const token = ref(localStorage.getItem('eventora_token') || null)
  const user = ref(JSON.parse(localStorage.getItem('eventora_user')) || null)
  
  // 2. Getters 
  const isLoggedIn = computed(() => !!token.value)
  const userRole = computed(() => user.value?.role || null)
  const userInitials = computed(() => {
        if (!user.value?.name) return '?'
        return user.value.name
            .split(' ')
            .map(n => n[0])
            .join('')
            .toUpperCase()
    })

  // 3. Actions (The exact API call moved from your component)
  const login = async (email, password) => {

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

    await fetchProfile()

    return user.value
  }

  const fetchProfile = async () => {
    if (!token.value) return

    console.log('✅ Token being sent:', token.value)

    const response = await fetch(`${API_BASE}/api/profile`, {
        headers: {
            Authorization: `Bearer ${token.value}`,
            Accept: 'application/json',
        },
    })

    console.log('Response status:', response.status)
    const text = await response.text() // Read as text first
    console.log('Raw response:', text)  // See exactly what PHP returns

    if (response.status === 401) {
      logout() // Token expired — clear everything
      return
    }

    const data = JSON.parse(text)


    //const data = await response.json()

    // Merge into existing user so nothing gets lost
    user.value = { ...user.value, ...data }
    localStorage.setItem('eventora_user', JSON.stringify(user.value))
  }

  const updateProfile = async (payload) => {
    const response = await fetch(`${API_BASE}/api/profile`, {
      method: 'PUT',
      headers: {
        'Content-Type': 'application/json',
        Authorization: `Bearer ${token.value}`,
        Accept: 'application/json',
      },
      body: JSON.stringify(payload),
    })

    if (!response.ok) {
      const data = await response.json()
      throw new Error(data.error || 'Failed to update profile')
    }

    // Sync local state with what was saved
    user.value = { ...user.value, ...payload }
    localStorage.setItem('eventora_user', JSON.stringify(user.value))
  }

 const updateAvatar = async (file) => {
  console.log('Token for avatar upload:', token.value) 
  const formData = new FormData()
  formData.append('avatar', file)

  const response = await fetch(`${API_BASE}/api/profile/avatar`, {
    method: 'POST',
    headers: {
      Authorization: `Bearer ${token.value}`,
    },
    body: formData,
  })

  const text = await response.text()
  console.log('Avatar upload response:', text)

  if (!response.ok) {
    throw new Error('Failed to upload avatar')
  }

  const data = JSON.parse(text)  // ← reuse text already read
  user.value = { ...user.value, profile_picture: data.profile_picture }
  localStorage.setItem('eventora_user', JSON.stringify(user.value))

  return data.profile_picture
}

const changePassword = async (currentPassword, newPassword) => {
  const response = await fetch(`${API_BASE}/api/profile/password`, {
    method: 'PUT',
    headers: {
      'Content-Type': 'application/json',
      Authorization: `Bearer ${token.value}`,
      Accept: 'application/json',
    },
    body: JSON.stringify({
      current_password: currentPassword,
      new_password: newPassword,
    }),
  })

  const data = await response.json()
  if (!response.ok) {
    throw new Error(data.error || 'Failed to update password')
  }
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
}

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
    userInitials,
    login,
    logout,
    fetchProfile,
    updateProfile,
    updateAvatar,
    changePassword,
  }
})