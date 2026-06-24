<script setup>
import { ref, onMounted, computed } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useAuthStore } from "@/stores/auth"; 
import axios from "axios";
import { Capacitor } from '@capacitor/core';
import { Filesystem, Directory } from '@capacitor/filesystem';

const route = useRoute();
const router = useRouter();
const eventId = route.params.id;
const API_BASE = import.meta.env.VITE_API_BASE_URL;
const authStore = useAuthStore();

// Component States
const event = ref(null);
const isLoading = ref(true);
const errorMessage = ref("");
const participants = ref([]);

onMounted(async () => {
  try {
    // 1. UPDATED: Changed the endpoint path from upcoming-events to past-events
    const response = await fetch(
      `${API_BASE}/api/society/past-events`,
      {
        headers: {
          "Authorization": `Bearer ${authStore.token}`,
          "Accept": "application/json",
        },
      },
    );
    const json = await response.json();

    if (json.status === "success") {
      const foundEvent = json.data.find((e) => e.id == eventId);
      if (foundEvent) {
        event.value = foundEvent;

        await fetchParticipants();
      } else {
        throw new Error("Event could not be found in your past society inventory.");
      }
    } else {
      throw new Error(json.message);
    }
  } catch (err) {
    errorMessage.value = err.message || "Failed to safely mount past event metrics.";
  } finally {
    isLoading.value = false;
  }
});

const fetchParticipants = async () => {
  try {
    const response = await fetch(
      `${API_BASE}/api/society/events/${eventId}/participants`,
      {
        headers: {
          Authorization: `Bearer ${authStore.token}`,
          Accept: "application/json",
        },
      }
    );

    const json = await response.json();

    if (json.status === "success") {
      participants.value = json.data;
    } else {
      throw new Error(json.message);
    }
  } catch (err) {
    console.error(err);
  }
};

const registeredCount = computed(() => participants.value.length);

// Formatting Helpers
const tagsArray = computed(() => {
  if (!event.value || !event.value.category_tags) return [];
  return event.value.category_tags.split(",").map((tag) => tag.trim());
});

const getTagStyles = (tag) => {
  const cleanTag = tag.toLowerCase();
  const styleMap = {
    academic: "bg-blue-100 text-blue-600 dark:bg-blue-950/40 dark:text-blue-300",
    sports: "bg-emerald-100 text-emerald-600 dark:bg-emerald-950/40 dark:text-emerald-300",
    cultural: "bg-amber-100 text-amber-600 dark:bg-amber-950/40 dark:text-amber-300",
    religious: "bg-purple-100 text-purple-600 dark:bg-purple-950/40 dark:text-purple-300",
  };
  return styleMap[cleanTag] || "bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300";
};

const formatDate = (dateString) => {
  if (!dateString) return "";
  const date = new Date(dateString);
  return date.toLocaleDateString("en-GB", {
    day: "numeric",
    month: "long",
    year: "numeric",
  });
};

// 2. UPDATED: Changed Edit method to navigate to the feedback sub-view
const handleViewFeedback = () => {
  router.push(`/society/past-events/${eventId}/feedback`);
};

// 3. UPDATED: Changed Cancel method into an attendance report downloader
const isExporting = ref(false);

// Helper function to convert Blob to Base64 (Required by Capacitor Filesystem)
const blobToBase64 = (blob) => {
  return new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onerror = reject;
    reader.onload = () => resolve(reader.result);
    reader.readAsDataURL(blob);
  });
};

const handleExportAttendance = async () => {
  if (isExporting.value) return;
  isExporting.value = true;

  try {
    const response = await axios.get(
      `${API_BASE}/api/society/events/${eventId}/attendance/export`,
      {
        headers: {
          Authorization: `Bearer ${authStore.token}`,
        },
        responseType: "blob",
      }
    );
    const filename = `event_${eventId}_attendance.csv`;

    // --- STRATEGY SPLIT BASED ON PLATFORM ---
    if (Capacitor.isNativePlatform()) {
      // 1. ANDROID / NATIVE LOGIC
      const base64Data = await blobToBase64(response.data);
      
      // Clean the base64 string (remove the "data:text/csv;base64," prefix)
       const pureBase64 = base64Data.split(',')[1];

       const result = await Filesystem.writeFile({
         path: filename,
         data: pureBase64,
         directory: Directory.External, // Saves to device Documents folder
       });

         console.log(result.uri);
     } else {
      // 2. STANDARD WEB BROWSER LOGIC
      const url = window.URL.createObjectURL(new Blob([response.data], { type: 'text/csv' }));
      const link = document.createElement('a');
      link.href = url;
      link.download = filename;
      
      document.body.appendChild(link);
      link.click();
      
      document.body.removeChild(link);
      window.URL.revokeObjectURL(url);
    }

  } catch (error) {
    console.error("Export failed:", error);
    alert("Could not export the attendance report.");
  } finally {
    isExporting.value = false;
  }
};
</script>

<template>
  <div class="min-h-screen bg-white dark:bg-gray-900 pb-24">
    <div v-if="isLoading" class="text-center py-20 text-gray-500">
      Loading historical records...
    </div>
    <div v-else-if="errorMessage" class="text-center py-20 text-red-500">
      {{ errorMessage }}
    </div>

    <div
      v-else
      class="max-w-xl mx-auto px-4 pt-4 flex flex-col min-h-[90vh] relative"
    >
      <div
        class="relative w-full h-64 sm:h-72 bg-purple-200 dark:bg-purple-900 rounded-[2.5rem] overflow-hidden shadow-sm mb-6"
      >
        <img
          :src="
            event.image_path
              ? `${API_BASE}/${event.image_path}`
              : 'https://via.placeholder.com/600x400?text=EventORA'
          "
          class="w-full h-full object-cover"
        />
        <button
          @click="router.push('/society/history')"
          class="absolute top-5 left-5 w-12 h-12 flex items-center justify-center bg-white dark:bg-gray-800 text-gray-800 dark:text-white rounded-full shadow-lg hover:scale-105 active:scale-95 transition-all"
        >
          <span class="text-xl font-bold">←</span>
        </button>
      </div>

      <div class="text-left space-y-3 px-1">
        <h2 class="text-2xl sm:text-3xl font-extrabold text-gray-900 dark:text-white tracking-tight uppercase">
          {{ event.title }}
        </h2>

        <div class="flex items-center space-x-2 text-gray-700 dark:text-gray-300">
          <span class="pi pi-user text-lg"></span>
          <span class="font-medium text-sm sm:text-base">{{ event.society_name }}</span>
        </div>

        <div class="flex flex-wrap gap-2 pt-1">
          <span
            v-for="(tag, index) in tagsArray"
            :key="index"
            :class="[
              'px-3 py-1 text-xs font-bold rounded-full uppercase tracking-wider',
              getTagStyles(tag),
            ]"
          >
            {{ tag }}
          </span>
        </div>

        <p class="text-gray-400 dark:text-gray-400 text-sm sm:text-base leading-relaxed pt-2">
          {{ event.description || "No description provided for this event." }}
        </p>

        <div class="text-gray-400 dark:text-gray-500 text-xs sm:text-sm font-semibold pt-2 uppercase tracking-widest">
          <span>RM{{ parseFloat(event.price).toFixed(2) }}</span>
          <span class="mx-2">|</span>
          <span>{{ formatDate(event.starts_at) }}</span>
          <span class="mx-2">|</span>
          <span>{{ event.venue }}</span>
        </div>
      </div>

      <div class="mt-8 bg-gray-50 dark:bg-gray-800/40 border border-gray-100 dark:border-gray-800 rounded-[2rem] py-8 px-6 text-center">
        <div class="flex flex-col items-center justify-center space-y-2">
          <span class="text-3xl pi pi-file"></span>
          <a
            v-if="event.supporting_document"
            :href="`${API_BASE}/${event.supporting_document}`"
            target="_blank"
            class="text-sm font-medium text-gray-400 dark:text-gray-500 hover:underline"
          >
            View Supporting Document
          </a>
          <span v-else class="text-sm text-gray-400">
            No document uploaded
          </span>
        </div>
      </div>

      <div class="mt-6 text-left px-2 space-y-1">
        <div class="text-sm text-gray-400 dark:text-gray-500 font-medium">
          Status:
          <span class="font-bold text-gray-600 dark:text-gray-300 capitalize">{{ event.status }}</span>
        </div>
        
        <div>
          <button
            @click="handleExportAttendance"
            type="button"
            class="text-xs font-bold text-purple-500 hover:text-purple-600 underline uppercase tracking-wider bg-transparent p-0 border-none cursor-pointer flex items-center gap-1"
          >
            <span class="pi pi-download text-[10px]"></span>
            Export Attendance Report
          </button>
        </div>
      </div>

      <div class="mt-auto pt-8 pb-6 flex items-center gap-4 bg-white dark:bg-gray-900">
        <div
          class="flex items-center justify-center gap-2 px-5 py-4 bg-purple-100 dark:bg-purple-950/60 rounded-2xl min-w-[110px] sm:min-w-[130px]"
        >
          <router-link :to="`/society/events/${eventId}/participants`">
            <span
              class="text-base text-gray-700 dark:text-gray-300 pi pi-users"
            ></span>
            <span
              class="text-base sm:text-lg font-bold text-gray-800 dark:text-gray-200"
            >
              {{ registeredCount }}/{{ event.capacity }}
            </span>
          </router-link>
        </div>

        <button
          @click="handleViewFeedback"
          type="button"
          class="flex-1 py-4 text-base sm:text-lg font-black text-white tracking-[0.15em] uppercase bg-gradient-to-r from-purple-600 to-indigo-500 rounded-2xl shadow-md shadow-purple-500/20 hover:opacity-95 transition-opacity active:scale-[0.99]"
        >
          View Feedback
        </button>
      </div>
    </div>
  </div>
</template>