<script setup>
import { ref, onMounted, computed } from "vue";
import { useAuthStore } from "@/stores/auth"; 
import FilterSortBar from "@/components/SearchFilterSortBar.vue"; 
import eventList from "@/components/Admin/PendingApprovals.vue"; // Ensure correct path

const authStore = useAuthStore();
const allEvents = ref([]);
const isLoading = ref(true);
const errorMessage = ref("");
const API_BASE = import.meta.env.VITE_API_BASE_URL;

const searchQuery = ref("");
const sortBy = ref("date-asc");
const selectedCategories = ref([]);
const selectedPriceTypes = ref([]);
const selectedStatus = ref("all"); // Options: "all", "approved", "pending", "cancelled", "rejected"
const categoriesList = ["Academic", "Sports", "Cultural", "Religious"];

// 1. Fetching logic moved here so data can pass into filters natively
onMounted(async () => {
  try {
    const response = await fetch(`${API_BASE}/api/admin/all-events`, {
      method: "GET",
      headers: {
        "Authorization": `Bearer ${authStore.token}`,
        "Accept": "application/json",
        "Content-Type": "application/json"
      }
    });

    const json = await response.json();
    if (response.ok && json.status === "success") {
      allEvents.value = json.data;
    } else {
      throw new Error(json.message || "Failed to load all events.");
    }
  } catch (err) {
    errorMessage.value = err.message || "Error connecting to the validation server.";
    console.error(err);
  } finally {
    isLoading.value = false;
  }
});

// 2. The pipeline stays pristine and acts on allEvents
const filteredAndSortedAllEvents = computed(() => {
  let result = [...allEvents.value];

  if (selectedStatus.value !== "all") {
      result = result.filter(event => {
        // Safely check and compare matching strings in lowercase
        return event.status?.toLowerCase() === selectedStatus.value.toLowerCase();
      });
    }

  if (searchQuery.value.trim()) {
    const query = searchQuery.value.toLowerCase().trim();
    result = result.filter(event => 
      event.title?.toLowerCase().includes(query) ||
      event.description?.toLowerCase().includes(query) ||
      event.society_name?.toLowerCase().includes(query)
    );
  }

  if (selectedCategories.value.length > 0) {
    result = result.filter(event => {
      if (!event.category_tags) return false;
      const eventTags = event.category_tags.split(",").map(t => t.trim().toLowerCase());
      return selectedCategories.value.some(cat => eventTags.includes(cat.toLowerCase()));
    });
  }

  if (selectedPriceTypes.value.length > 0) {
    result = result.filter(event => {
      const isFree = parseFloat(event.price) === 0;
      if (selectedPriceTypes.value.includes("free") && isFree) return true;
      if (selectedPriceTypes.value.includes("paid") && !isFree) return true;
      return false;
    });
  }

  result.sort((a, b) => {
    if (sortBy.value === "date-asc") return new Date(a.starts_at) - new Date(b.starts_at);
    if (sortBy.value === "date-desc") return new Date(b.starts_at) - new Date(a.starts_at);
    if (sortBy.value === "price-low") return parseFloat(a.price) - parseFloat(b.price);
    if (sortBy.value === "price-high") return parseFloat(b.price) - parseFloat(a.price);
    return 0;
  });

  return result;
});

</script>

<template>
  <div class="min-h-screen p-4 sm:p-8 max-w-7xl mx-auto">
    <div class="mb-6 text-left flex items-center justify-between pl-2">
      <h2 class="text-3xl font-extrabold text-gray-900 dark:text-white">
        All Events Lists
      </h2>
    </div>

    <div class="space-y-6">
      <FilterSortBar 
        v-model:searchQuery="searchQuery"
        v-model:sortBy="sortBy"
        v-model:selectedCategories="selectedCategories"
        v-model:selectedPriceTypes="selectedPriceTypes"
        :categoriesList="categoriesList"
        searchPlaceholder="Search pending submissions..."
      />

      <div class="flex items-center gap-2 overflow-x-auto pb-2 whitespace-nowrap scrollbar-none px-1 ">
        <button 
          v-for="status in ['all', 'pending', 'approved', 'cancelled', 'rejected']" 
          :key="status"
          @click="selectedStatus = status"
          type="button"
          :class="[
            selectedStatus === status 
              ? 'bg-purple-600 text-white font-black shadow-md shadow-purple-500/20' 
              : 'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-700 font-bold',
            'px-5 py-2.5 rounded-xl text-xs sm:text-xs capitalize transition-all shrink-0'
          ]"
        >
          <span v-if="status === 'pending'"> </span>
          <span v-else-if="status === 'approved'"> </span>
          <span v-else-if="status === 'cancelled'"></span>
          <span v-else-if="status === 'rejected'"></span>
          
          {{ status }}
        </button>
      </div>
      <div v-if="isLoading" class="text-center text-gray-500 py-24 font-medium">
        Loading scheduled approval records...
      </div>

      <div v-else-if="errorMessage" class="text-red-600 bg-red-50 dark:bg-red-950/20 p-4 rounded-xl text-center text-sm font-semibold">
        {{ errorMessage }}
      </div>

      <div 
        v-else-if="allEvents.length > 0 && filteredAndSortedAllEvents.length === 0" 
        class="text-center bg-gray-50 dark:bg-gray-800/40 rounded-2xl py-20 border-2 border-dashed border-gray-200 dark:border-gray-700"
      >
        <p class="pi pi-question text-3xl mb-2 text-gray-400"></p>
        <h4 class="text-lg font-bold text-gray-700 dark:text-gray-300">No matching events found</h4>
        <p class="text-sm text-gray-400 mt-1">Try resetting your filter checkmarks or refinement strings.</p>
      </div>

      <div
        v-else-if="allEvents.length === 0"
        class="text-center py-20 text-gray-500 dark:text-gray-400 font-medium bg-gray-50 dark:bg-gray-800/40 border-2 border-dashed rounded-3xl"
      >
        There are no events to display at this time. 
      </div>

      <div v-else>
        <eventList
          :events="filteredAndSortedAllEvents" 
        />
      </div>
    </div>
  </div>
</template>