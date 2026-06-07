<script setup>
import { ref, onMounted, computed } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useAuthStore } from "@/stores/auth";

const route = useRoute();
const router = useRouter();
const eventId = route.params.id;
const API_BASE = import.meta.env.VITE_API_BASE_URL;
const authStore = useAuthStore();

// States
const feedbacks = ref([]);
const eventTitle = ref("Loading Event Details...");
const isLoading = ref(true);
const errorMessage = ref("");

onMounted(async () => {
  try {
    // Assuming your endpoint returns event meta information along with feedback list
    const response = await fetch(`${API_BASE}/api/society/events/${eventId}/feedbacks`, {
      headers: {
        "Authorization": `Bearer ${authStore.token}`,
        "Accept": "application/json",
      },
    });
    const json = await response.json();

    if (json.status === "success") {
      feedbacks.value = json.data.feedbacks || [];
      eventTitle.value = json.data.event_title || "Event Feedback";
    } else {
      throw new Error(json.message || "Failed to retrieve feedback metrics.");
    }
  } catch (err) {
    errorMessage.value = err.message;
  } finally {
    isLoading.value = false;
  }
});

// Aggregate Analytics Calculators
const totalFeedbacks = computed(() => feedbacks.value.length);

const averageRating = computed(() => {
  if (feedbacks.value.length === 0) return 0;
  const sum = feedbacks.value.reduce((acc, curr) => acc + parseFloat(curr.rating), 0);
  return (sum / feedbacks.value.length).toFixed(1);
});

// Formatting Date Helper
const formatDate = (dateString) => {
  if (!dateString) return "";
  const date = new Date(dateString);
  return date.toLocaleDateString("en-GB", {
    day: "numeric",
    month: "short",
    year: "numeric",
  });
};
</script>

<template>
  <div class="min-h-screen bg-gray-50 dark:bg-gray-950 p-4 sm:p-8">
    <div class="max-w-4xl mx-auto">
      
      <div class="flex items-center space-x-4 mb-8 text-left">
        <button
          @click="router.back()"
          class="w-10 h-10 flex items-center justify-center bg-white dark:bg-gray-800 text-gray-800 dark:text-white rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 hover:scale-105 active:scale-95 transition-all"
        >
          <span class="text-lg font-bold">←</span>
        </button>
        <div>
          <span class="text-xs font-bold tracking-widest text-purple-600 dark:text-purple-400 uppercase">Performance Reviews</span>
          <h2 class="text-2xl sm:text-3xl font-extrabold text-gray-900 dark:text-white truncate max-w-xl">
            {{ eventTitle }}
          </h2>
        </div>
      </div>

      <div v-if="isLoading" class="text-center py-24 text-gray-500 font-medium">
        Analyzing attendee review metrics...
      </div>
      
      <div v-else-if="errorMessage" class="text-center py-24 text-red-500 font-medium">
        {{ errorMessage }}
      </div>

      <div v-else class="space-y-6">
        
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div class="bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700/60 rounded-3xl p-6 flex items-center justify-between shadow-sm">
            <div class="text-left">
              <p class="text-sm font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wider">Average Rating</p>
              <h3 class="text-4xl font-black text-gray-900 dark:text-white mt-1">
                {{ averageRating }} <span class="text-lg font-medium text-gray-400">/ 5.0</span>
              </h3>
            </div>
            <div class="w-14 h-14 bg-amber-50 dark:bg-amber-950/30 text-amber-500 rounded-2xl flex items-center justify-center text-2xl font-bold">
                <span class="pi pi-star-fill"></span>
            </div>
          </div>

          <div class="bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700/60 rounded-3xl p-6 flex items-center justify-between shadow-sm">
            <div class="text-left">
              <p class="text-sm font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wider">Total Responses</p>
              <h3 class="text-4xl font-black text-gray-900 dark:text-white mt-1">
                {{ totalFeedbacks }}
              </h3>
            </div>
            <div class="w-14 h-14 bg-purple-50 dark:bg-purple-950/30 text-purple-500 rounded-2xl flex items-center justify-center text-xl">
              <span class="pi pi-comment"></span>
            </div>
          </div>
        </div>

        <div class="text-left mt-8">
          <h4 class="text-lg font-bold text-gray-800 dark:text-gray-200 mb-4 pl-1">
            Individual Feedback Logs
          </h4>

          <div 
            v-if="feedbacks.length === 0" 
            class="text-center bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 rounded-3xl py-20 shadow-sm"
          >
            <p class="pi pi-question text-3xl mb-2"></p>
            <h5 class="font-bold text-gray-700 dark:text-gray-300">No feedback forms filled</h5>
            <p class="text-sm text-gray-400 mt-1 mx-auto max-w-xs">Attendees haven't submitted structural reviews or rating metrics for this project yet.</p>
          </div>

          <div v-else class="space-y-3">
            <div 
              v-for="item in feedbacks" 
              :key="item.id" 
              class="bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 rounded-2xl p-5 shadow-sm transition-all hover:border-gray-200 dark:hover:border-gray-600"
            >
              <div class="flex items-start justify-between gap-4">
                <div class="space-y-1">
                  <div class="flex items-center space-x-2">
                    <span class="text-sm font-bold text-gray-800 dark:text-gray-200">
                      {{ item.student_name || "Anonymous Attendee" }}
                    </span>
                    <span class="text-xs text-gray-300 dark:text-gray-600">|</span>
                    <span class="text-xs font-semibold text-gray-400 dark:text-gray-500">
                      {{ formatDate(item.created_at) }}
                    </span>
                  </div>

                  <p class="text-gray-600 dark:text-gray-400 text-sm leading-relaxed pt-1">
                    {{ item.comment || "Left score metrics without text details." }}
                  </p>
                </div>

                <div class="flex items-center space-x-1.5 px-3 py-1 bg-amber-50 dark:bg-amber-950/30 border border-amber-100 dark:border-amber-900/50 rounded-full shrink-0">
                  <span class="text-amber-500 text-xs pi pi-star-fill"></span>
                  <span class="text-xs font-black text-amber-700 dark:text-amber-400">
                    {{ parseFloat(item.rating).toFixed(0) }}
                  </span>
                </div>
              </div>

            </div>
          </div>

        </div>

      </div>

    </div>
  </div>
</template>