<script setup>
import { ref, onMounted, computed } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useAuthStore } from "@/stores/auth"; 

const route = useRoute();
const router = useRouter();
const eventId = route.params.id;
const API_BASE = import.meta.env.VITE_API_BASE_URL;
const authStore = useAuthStore();

const event = ref(null);
const isLoading = ref(true);
const errorMessage = ref("");
const isSubmitting = ref(false); // Prevents duplicate double-clicks

// Fetch event payload on component mount
onMounted(async () => {
  try {
    const response = await fetch(`${API_BASE}/api/admin/events/${eventId}`, {
      headers: {
        "Authorization": `Bearer ${authStore.token}`,
        "Accept": "application/json",
      },
    });
    const json = await response.json();

    if (response.ok && json.status === "success") {
      event.value = json.data;
    } else {
      throw new Error(json.message || "Event could not be found.");
    }
  } catch (err) {
    errorMessage.value = err.message || "Failed to safely mount event metrics.";
  } finally {
    isLoading.value = false;
  }
});

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
  return date.toLocaleDateString("en-GB", { day: "numeric", month: "long", year: "numeric" });
};

// Centralized Status Update Workflow (Approve / Reject / Cancel)
const submitReview = async (newStatus) => {
  if (!confirm(`Are you sure you want to set this event status to ${newStatus}?`)) return;
  
  isSubmitting.value = true;
  try {
    const response = await fetch(`${API_BASE}/api/admin/events/${eventId}/review`, {
      method: "POST",
      headers: {
        "Authorization": `Bearer ${authStore.token}`,
        "Content-Type": "application/json",
        "Accept": "application/json"
      },
      body: JSON.stringify({ status: newStatus })
    });

    const json = await response.json();

    if (response.ok && json.status === "success") {
      alert(`Event status updated successfully to ${newStatus}!`);
      router.push('/admin/home'); 
    } else {
      throw new Error(json.message || `Failed to transition state to ${newStatus}.`);
    }
  } catch (err) {
    alert(err.message);
  } finally {
    isSubmitting.value = false;
  }
};

// Permanent Hard-Delete Workflow Handler (Satisfies full entity deletion criteria)
const handleDelete = async () => {
  if (!confirm("CRITICAL WARNING: Are you absolutely sure you want to permanently DELETE this event record from the repository? This operation is irreversible.")) return;

  isSubmitting.value = true;
  try {
    const response = await fetch(`${API_BASE}/api/admin/events/${eventId}`, {
      method: "DELETE",
      headers: {
        "Authorization": `Bearer ${authStore.token}`,
        "Accept": "application/json"
      }
    });

    const json = await response.json();

    if (response.ok && json.status === "success") {
      alert("Event record successfully removed from storage.");
      router.push('/admin/home');
    } else {
      throw new Error(json.message || "Failed to finalize database purge.");
    }
  } catch (err) {
    alert(err.message);
  } finally {
    isSubmitting.value = false;
  }
};

const handleReject = () => submitReview('rejected');
const handleApprove = () => submitReview('approved');
const handleCancel = () => submitReview('cancelled');
</script>

<template>
  <div class="min-h-screen bg-white dark:bg-gray-900 pb-24">
    <div v-if="isLoading" class="text-center py-20 text-gray-500">
      Loading organizer metrics...
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
          @click="router.back()"
          class="absolute top-5 left-5 w-12 h-12 flex items-center justify-center bg-white dark:bg-gray-800 text-gray-800 dark:text-white rounded-full shadow-lg hover:scale-105 active:scale-95 transition-all"
        >
          <span class="text-xl font-bold">←</span>
        </button>
      </div>

      <div class="text-left space-y-3 px-1">
        <h2
          class="text-2xl sm:text-3xl font-extrabold text-gray-900 dark:text-white tracking-tight uppercase"
        >
          {{ event.title }}
        </h2>

        <div
          class="flex items-center space-x-2 text-gray-700 dark:text-gray-300"
        >
          <span class="pi pi-user text-lg"></span>
          <span class="font-medium text-sm sm:text-base">{{
            event.society_name
          }}</span>
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

        <p
          class="text-gray-400 dark:text-gray-400 text-sm sm:text-base leading-relaxed pt-2"
        >
          {{ event.description || "No description provided for this event." }}
        </p>

        <div
          class="text-gray-400 dark:text-gray-500 text-xs sm:text-sm font-semibold pt-2 uppercase tracking-widest"
        >
          <span>RM{{ parseFloat(event.price).toFixed(2) }}</span>
          <span class="mx-2">|</span>
          <span>{{ formatDate(event.starts_at) }}</span>
          <span class="mx-2">|</span>
          <span>{{ event.venue }}</span>
        </div>
      </div>

      <div
        class="mt-8 bg-gray-50 dark:bg-gray-800/40 border border-gray-100 dark:border-gray-800 rounded-[2rem] py-8 px-6 text-center"
      >
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
          <span class="font-bold text-gray-600 dark:text-gray-300 capitalize">{{
            event.status 
          }}</span>
        </div>
      </div>

      <div
        class="mt-auto pt-8 pb-6 flex items-center gap-4 bg-white dark:bg-gray-900"
      >
        <template v-if="event.status === 'pending'">
          <button
            @click="handleReject"
            :disabled="isSubmitting"
            type="button"
            class="flex items-center justify-center gap-2 px-5 py-4 bg-red-100 dark:bg-red-950/60 text-red-700 dark:text-red-300 font-bold rounded-2xl min-w-[110px] sm:min-w-[130px] disabled:opacity-50"
          >
            Reject          
          </button>

          <button
            @click="handleApprove"
            :disabled="isSubmitting"
            type="button"
            class="flex-1 py-4 text-base sm:text-lg font-black text-white tracking-[0.2em] uppercase bg-gradient-to-r from-blue-600 to-purple-500 rounded-2xl shadow-md shadow-purple-500/20 hover:opacity-95 transition-opacity active:scale-[0.99] disabled:opacity-50"
          >
            Approve
          </button>
        </template>

        <template v-else-if="event.status === 'approved'">
          <button
            @click="handleCancel"
            :disabled="isSubmitting"
            type="button"
            class="flex-1 py-4 text-base sm:text-lg font-black text-white tracking-[0.2em] uppercase bg-gradient-to-r from-amber-500 to-red-500 rounded-2xl shadow-md hover:opacity-95 transition-opacity disabled:opacity-50"
          >
            Cancel Event
          </button>
        </template>

        <template v-else-if="event.status === 'rejected' || event.status === 'cancelled'">
          <button
            @click="handleDelete"
            :disabled="isSubmitting"
            type="button"
            class="flex-1 py-4 text-base sm:text-lg font-black text-white tracking-[0.2em] uppercase bg-red-600 rounded-2xl shadow-md shadow-red-600/20 hover:bg-red-700 transition-colors disabled:opacity-50"
          >
            Delete Record
          </button>
        </template>
      </div>
      </div>
  </div>
</template>