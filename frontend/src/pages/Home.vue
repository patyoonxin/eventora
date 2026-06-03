<script setup>
import { ref, onMounted, computed } from "vue";
import EventCard from "@/components/Student/EventCard.vue";
import FilterSortBar from "@/components/SearchFilterSortBar.vue"; // Import search UI component

// API Data States
const events = ref([]);
const isLoading = ref(true);
const errorMessage = ref("");

// Isolated Filter UI State management variables
const searchQuery = ref("");
const sortBy = ref("date-asc");
const selectedCategories = ref([]);
const selectedPriceTypes = ref([]);
const API_BASE = import.meta.env.VITE_API_BASE_URL;

const categoriesList = ["Academic", "Sports", "Cultural", "Religious"];

onMounted(async () => {
  try {
    const response = await fetch(`${API_BASE}/api/events`);
    const json = await response.json();
    if (json.status === "success") {
      events.value = json.data;
    } else {
      throw new Error(json.message);
    }
  } catch (err) {
    errorMessage.value = "Failed to load events.";
    console.error(err);
  } finally {
    isLoading.value = false;
  }
});

// Core Pipeline stays contextually clean and reads directly from local variables!
const filteredAndSortedEvents = computed(() => {
  let result = [...events.value];

  // 1. Search Box Filter
  if (searchQuery.value.trim()) {
    const query = searchQuery.value.toLowerCase().trim();
    result = result.filter(event => 
      event.title?.toLowerCase().includes(query) ||
      event.description?.toLowerCase().includes(query) ||
      event.society_name?.toLowerCase().includes(query)
    );
  }

  // 2. Category multi-select filter
  if (selectedCategories.value.length > 0) {
    result = result.filter(event => {
      if (!event.category_tags) return false;
      const eventTags = event.category_tags.split(",").map(t => t.trim().toLowerCase());
      return selectedCategories.value.some(cat => eventTags.includes(cat.toLowerCase()));
    });
  }

  // 3. Admission Cost Filter
  if (selectedPriceTypes.value.length > 0) {
    result = result.filter(event => {
      const isFree = parseFloat(event.price) === 0;
      if (selectedPriceTypes.value.includes("free") && isFree) return true;
      if (selectedPriceTypes.value.includes("paid") && !isFree) return true;
      return false;
    });
  }

  // 4. Sorting Pipeline
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
    <div class="mb-4 text-left">
      <h2 class="text-3xl font-extrabold text-gray-900 dark:text-white pl-5">
        Explore Upcoming Events
      </h2>
    </div>

    <div class="space-y-6">
      <FilterSortBar 
        v-model:searchQuery="searchQuery"
        v-model:sortBy="sortBy"
        v-model:selectedCategories="selectedCategories"
        v-model:selectedPriceTypes="selectedPriceTypes"
        :categoriesList="categoriesList"
        searchPlaceholder="Search by event title, keywords, or host club..."
      />

      <div v-if="isLoading" class="text-center text-gray-500 py-24 font-medium">
        Loading amazing student activities...
      </div>

      <div v-else-if="errorMessage" class="text-red-500 bg-red-50 dark:bg-red-950/20 p-4 rounded-xl text-center">
        {{ errorMessage }}
      </div>

      <div v-else-if="filteredAndSortedEvents.length === 0" class="text-center bg-gray-50 dark:bg-gray-800/40 rounded-2xl py-20 border-2 border-dashed border-gray-200 dark:border-gray-700">
        <p class="pi pi-question text-3xl mb-2"></p>
        <h4 class="text-lg font-bold text-gray-700 dark:text-gray-300">No events match your selection</h4>
        <p class="text-sm text-gray-400 mt-1">Try adjusting your search criteria or toggling different filter categories.</p>
      </div>

      <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-2 xl:grid-cols-3 gap-6 justify-items-center">
        <EventCard
          v-for="item in filteredAndSortedEvents"
          :key="item.id"
          :event="item"
        />
      </div>
    </div>
  </div>
</template>