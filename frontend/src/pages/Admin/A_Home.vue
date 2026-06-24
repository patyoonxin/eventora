<script setup>
import { ref, onMounted, computed } from "vue";
import { useAuthStore } from "@/stores/auth"; 
import FilterSortBar from "@/components/SearchFilterSortBar.vue"; 
import PendingApprovals from "@/components/Admin/PendingApprovals.vue"; // Ensure correct path

const authStore = useAuthStore();
const upcomingEvents = ref([]);
const isLoading = ref(true);
const errorMessage = ref("");
const API_BASE = import.meta.env.VITE_API_BASE_URL;

const searchQuery = ref("");
const sortBy = ref("date-asc");
const selectedCategories = ref([]);
const selectedPriceTypes = ref([]);
const categoriesList = ["Academic", "Sports", "Cultural", "Religious"];

// 1. Fetching logic moved here so data can pass into filters natively
onMounted(async () => {
  try {
    const response = await fetch(`${API_BASE}/api/admin/pending-events`, {
      method: "GET",
      headers: {
        "Authorization": `Bearer ${authStore.token}`,
        "Accept": "application/json",
        "Content-Type": "application/json"
      }
    });

    const json = await response.json();
    if (response.ok && json.status === "success") {
      upcomingEvents.value = json.data;
    } else {
      throw new Error(json.message || "Failed to load pending events.");
    }
  } catch (err) {
    errorMessage.value = err.message || "Error connecting to the validation server.";
    console.error(err);
  } finally {
    isLoading.value = false;
  }
});

// 2. The pipeline stays pristine and acts on upcomingEvents
const filteredAndSortedUpcomingEvents = computed(() => {
  let result = [...upcomingEvents.value];

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

// 3. Callback to remove approved/rejected records from view locally
const handleEventReviewed = (id) => {
  upcomingEvents.value = upcomingEvents.value.filter(e => e.id !== id);
};
</script>

<template>
  <div class="min-h-screen px-4 sm:px-6 lg:px-8 py-8 bg-white dark:bg-gray-900">
    <div class="max-w-7xl mx-auto">
      <div class="mb-6 text-left flex items-center justify-between pl-2">
        <h2 class="text-3xl font-extrabold text-gray-900 dark:text-white">
          Pending Events Dashboard
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

      <div v-if="isLoading" class="text-center text-gray-500 py-24 font-medium">
        Loading scheduled approval records...
      </div>

      <div v-else-if="errorMessage" class="text-red-600 bg-red-50 dark:bg-red-950/20 p-4 rounded-xl text-center text-sm font-semibold">
        {{ errorMessage }}
      </div>

      <div 
        v-else-if="upcomingEvents.length > 0 && filteredAndSortedUpcomingEvents.length === 0" 
        class="text-center bg-gray-50 dark:bg-gray-800/40 rounded-2xl py-20 border-2 border-dashed border-gray-200 dark:border-gray-700"
      >
        <p class="pi pi-question text-3xl mb-2 text-gray-400"></p>
        <h4 class="text-lg font-bold text-gray-700 dark:text-gray-300">No matching approvals found</h4>
        <p class="text-sm text-gray-400 mt-1">Try resetting your filter checkmarks or refinement strings.</p>
      </div>

      <div
        v-else-if="upcomingEvents.length === 0"
        class="text-center py-20 text-gray-500 dark:text-gray-400 font-medium bg-gray-50 dark:bg-gray-800/40 border-2 border-dashed rounded-3xl"
      >
        All caught up! There are no pending events requiring faculty authorization at this time. 
      </div>

      <div v-else>
        <PendingApprovals 
          :events="filteredAndSortedUpcomingEvents" 
          @reviewed="handleEventReviewed"
        />
      </div>
    </div>
  </div>
</div>
</template>