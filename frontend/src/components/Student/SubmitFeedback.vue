<script setup>
import { ref, onMounted } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useAuthStore } from "@/stores/auth";

const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();

const eventId = route.params.id; // Grab the event context from URL params
const API_BASE = import.meta.env.VITE_API_BASE_URL;

// Component State
const eventTitle = ref("Loading Event...");
const rating = ref(0); // Holds numeric star count (1 to 5)
const hoverRating = ref(0); // Controls dynamic star highlighting on hover
const comments = ref("");
const isSubmitting = ref(false);

console.log("TOKEN BEFORE SUBMIT:", authStore.token);

// Fetch basic event details to display the correct title at the top header context
onMounted(async () => {
  try {
    const response = await fetch(`${API_BASE}/api/student/events/${eventId}`, {
      headers: {
        Authorization: `Bearer ${authStore.token}`,
        Accept: "application/json",
      },
    });
    const json = await response.json();
    if (response.ok && json.status === "success") {
      eventTitle.value = json.data.title;
    } else {
      eventTitle.value = " "; 
    }
  } catch (err) {
    console.error("Error retrieving event header info:", err);
    eventTitle.value = " ";
  }
});

// Submit workflow handler
const handleSaveFeedback = async () => {
  if (rating.value === 0) {
    alert("Please select a star rating before saving your review.");
    return;
  }

  isSubmitting.value = true;
  try {
    const response = await fetch(`${API_BASE}/api/student/feedback`, {
      method: "POST",
      headers: {
        Authorization: `Bearer ${authStore.token}`,
        "Content-Type": "application/json",
        Accept: "application/json",
      },
      body: JSON.stringify({
        event_id: eventId,
        rating: rating.value,
        comments: comments.value.trim(),
      }),
    });

    const json = await response.json();

    if (response.ok && json.status === "success") {
      alert(
        "Thank you! Your experience feedback has been preserved successfully.",
      );
      router.back(); // Take them back to their past history logs
    } else {
      throw new Error(json.message || "Failed to submit review.");
    }
  } catch (err) {
    alert(err.message);
  } finally {
    isSubmitting.value = false;
  }
};
</script>

<template>
  <div
    class="min-h-screen bg-white dark:bg-gray-900 text-left flex justify-center items-start pt-6 px-4"
  >
    <div
      class="w-full max-w-md mx-auto flex flex-col min-h-[88vh] relative pb-20"
    >
      <div class="flex items-center gap-4 mb-8">
        <button
          @click="router.back()"
          class="text-xl font-bold text-slate-700 dark:text-gray-300 hover:scale-110 transition-transform active:scale-95"
        >
          ←
        </button>
        <h2
          class="text-xl font-black text-slate-800 dark:text-white uppercase tracking-wide truncate"
        >
          {{ eventTitle }}
        </h2>
      </div>

      <div class="flex items-center gap-4 mb-6 px-1">
        <div
          class="w-14 h-14 bg-slate-400 dark:bg-slate-600 rounded-full flex-shrink-0 shadow-sm"
        ></div>
        <div>
          <h4 class="text-base font-bold text-slate-800 dark:text-gray-200">
            {{ authStore.user?.name || "Username" }}
          </h4>
          <p class="text-xs text-gray-400">Verified Attendee Participant</p>
        </div>
      </div>

      <div class="flex items-center justify-start space-x-2 mb-6 px-1">
        <button
          v-for="star in 5"
          :key="star"
          type="button"
          @click="rating = star"
          @mouseenter="hoverRating = star"
          @mouseleave="hoverRating = 0"
          class="text-3xl focus:outline-none transition-transform duration-100 active:scale-90"
        >
          <span
            :class="[
              star <= (hoverRating || rating)
                ? 'text-orange-500'
                : 'text-gray-200 dark:text-gray-700',
            ]"
          >
            ★
          </span>
        </button>
      </div>

      <div class="px-1">
        <textarea
          v-model="comments"
          placeholder="Share details of your own experience in this event..."
          rows="5"
          class="w-full p-5 bg-slate-50 dark:bg-gray-800 text-slate-800 dark:text-white text-sm rounded-2xl border-0 focus:ring-2 focus:ring-purple-400 resize-none placeholder-gray-400 dark:placeholder-gray-500 shadow-inner"
        ></textarea>
      </div>

      <div class="mt-auto pt-8">
        <button
          @click="handleSaveFeedback"
          :disabled="isSubmitting"
          type="button"
          class="w-full py-4 text-xl font-bold text-white tracking-[0.15em] bg-gradient-to-r from-blue-600 to-purple-500 rounded-2xl shadow-lg hover:opacity-95 transition-all disabled:opacity-40 active:scale-[0.99]"
        >
          {{ isSubmitting ? "PROCESSING..." : "SAVE" }}
        </button>
      </div>
    </div>
  </div>
</template>
