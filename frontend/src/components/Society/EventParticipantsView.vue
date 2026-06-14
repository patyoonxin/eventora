<script setup>
import { ref, onMounted } from "vue";
import { useRoute } from "vue-router";
import axios from "axios";
import { useAuthStore } from "@/stores/auth";

const route = useRoute();
const API_BASE = import.meta.env.VITE_API_BASE_URL;
const authStore = useAuthStore();

const eventId = route.params.id;

const participants = ref([]);
const isLoading = ref(true);
const errorMessage = ref("");

const formatDate = (dateString) => {
  if (!dateString) return "-";

  return new Date(dateString).toLocaleDateString("en-GB", {
    day: "numeric",
    month: "short",
    year: "numeric",
  });
};

onMounted(async () => {
  try {
    const response = await fetch(
      `${API_BASE}/api/society/events/${eventId}/participants`,
      {
        headers: {
          Authorization: `Bearer ${authStore.token}`,
          Accept: "application/json",
        },
      },
    );

    if (!response.ok) {
      throw new Error(`HTTP ${response.status}`);
    }

    const json = await response.json();

    if (json.status === "success") {
      participants.value = json.data;
    } else {
      throw new Error(json.message);
    }
  } catch (error) {
    console.error("Error fetching participants:", error);
    errorMessage.value = error.message || "Failed to load participants.";
  } finally {
    isLoading.value = false;
  }
});

const handleSendNotification = async () => {
  try {
    const response = await fetch(
      `${API_BASE}/api/society/events/${eventId}/send-reminders`,
      {
        method: "POST",
        headers: {
          Authorization: `Bearer ${authStore.token}`,
          Accept: "application/json",
          "Content-Type": "application/json",
        },
      },
    );

    if (!response.ok) {
      const errorText = await response.text();
      throw new Error(errorText || `HTTP ${response.status}`);
    }

    const json = await response.json();

    alert(
      json.message ||
      "Reminders sent to all registered attendees successfully!"
    );
  } catch (error) {
    console.error("Failed to send reminders:", error);
    alert(error.message || "Failed to send reminders");
  }
};
</script>

<template>
  <div class="max-w-4xl mx-auto p-4 sm:p-6 min-h-screen flex flex-col justify-between">
    <div>
      <div class="mb-6">
        <router-link
          :to="{ name: 'SocietyEventDetails', params: { id: eventId } }"
          class="inline-flex items-center text-sm font-medium text-[var(--text)] hover:text-[var(--accent)] transition-colors duration-200 group"
        >
          <span class="mr-2 transform group-hover:-translate-x-1 transition-transform duration-200">←</span>
          Back to Event Details
        </router-link>
      </div>

      <div class="mb-8">
        <h2 class="text-2xl sm:text-3xl font-bold text-[var(--text-h)] tracking-tight">
          Registered Participants
        </h2>
        <p class="text-sm sm:text-base text-[var(--text)] mt-1 font-medium">
          Total Registrations:
          <span class="text-[var(--accent)] font-semibold">{{ participants.length }}</span>
        </p>
      </div>

      <div
        v-if="participants.length === 0"
        class="text-center py-12 px-4 text-[var(--text)] border-2 border-dashed border-[var(--border)] rounded-xl"
      >
        <p class="text-lg font-medium">
          No students have registered for this event yet.
        </p>
      </div>

      <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div
          v-for="person in participants"
          :key="person.ticket_id"
          class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 p-4 rounded-xl border border-[var(--border)] bg-[var(--bg)] shadow-sm hover:shadow-md transition-all duration-200"
        >
          <div class="space-y-1.5">
            <h3 class="font-semibold text-lg text-[var(--text-h)] leading-none">
              {{ person.user_name }}
            </h3>
            <p class="text-sm text-[var(--text)] flex items-center gap-2">
              <span aria-hidden="true" class="pi pi-envelope"></span> {{ person.user_email }}
            </p>
            <p class="text-xs text-[var(--text)] flex items-center gap-2 opacity-80">
              <span aria-hidden="true" class="pi pi-calendar"></span> Registered:
              {{ new Date(person.issued_at).toLocaleDateString() }}
            </p>
          </div>

          <div class="flex items-center sm:justify-end">
            <span
              :class="[
                'inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold tracking-wide uppercase border',
                person.ticket_status === 'valid' || person.ticket_status === 'checked_in'
                  ? 'bg-emerald-50 dark:bg-emerald-950/30 text-emerald-700 dark:text-emerald-400 border-emerald-200 dark:border-emerald-800'
                  : 'bg-amber-50 dark:bg-amber-950/30 text-amber-700 dark:text-amber-400 border-amber-200 dark:border-amber-800',
              ]"
            >
              {{ person.ticket_status }}
            </span>
          </div>
        </div>
      </div>
    </div>

    <div class="mt-12 pt-6 border-t border-[var(--border)] flex justify-center w-full">
      <button
        @click="handleSendNotification"
        class="w-full sm:max-w-md flex-1 py-4 text-base sm:text-lg font-black text-white tracking-[0.15em] uppercase bg-gradient-to-r from-purple-600 to-indigo-500 rounded-2xl shadow-md shadow-purple-500/20 hover:opacity-95 transition-opacity active:scale-[0.99]"
      >
        Send Notification
      </button>
    </div>
  </div>
</template>
