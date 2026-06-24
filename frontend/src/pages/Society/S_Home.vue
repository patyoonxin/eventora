<script setup>
import { ref, onMounted, computed } from "vue";
import { useRouter } from "vue-router";
import { useAuthStore } from "@/stores/auth"; 
import EventCard from "@/components/Student/EventCard.vue";
import FilterSortBar from "@/components/SearchFilterSortBar.vue"; 

const router = useRouter();
const upcomingEvents = ref([]);
const isLoading = ref(true);
const API_BASE = import.meta.env.VITE_API_BASE_URL;
const authStore = useAuthStore();

const searchQuery = ref("");
const sortBy = ref("date-asc");
const selectedCategories = ref([]);
const selectedPriceTypes = ref([]);
const categoriesList = ["Academic", "Sports", "Cultural", "Religious"];

onMounted(async () => {
  try {
    const response = await fetch(
      `${API_BASE}/api/society/upcoming-events`,
      {
        headers: {
          "Authorization": `Bearer ${authStore.token}`,
          "Accept": "application/json",
        },
      },
    );

    const json = await response.json();

    if (json.status === "success") {
      upcomingEvents.value = json.data;
    }
  } catch (err) {
    console.error("Failed to load society dashboard records:", err);
  } finally {
    isLoading.value = false;
  }
});

const filteredAndSortedUpcomingEvents = computed(() => {
  let result = [...upcomingEvents.value];

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
  <div class="min-h-screen px-4 sm:px-6 lg:px-8 py-8 bg-white dark:bg-gray-900">
    <div class="max-w-7xl mx-auto">
      <div class="mb-6 text-left flex items-center justify-between pl-2">
        <h2 class="text-3xl font-extrabold text-gray-900 dark:text-white">
          Your Upcoming Events
        </h2>

      <button
        @click="router.push('/society/events/create')"
        type="button"
        class="w-12 h-12 flex items-center justify-center bg-[#aa3bff] hover:opacity-90 text-white rounded-2xl shadow-md transition-transform active:scale-95 focus:outline-none text-2xl font-bold"
        title="Create New Event"
      >
        +
      </button>
    </div>

    <div class="space-y-6">
      
      <FilterSortBar 
        v-model:searchQuery="searchQuery"
        v-model:sortBy="sortBy"
        v-model:selectedCategories="selectedCategories"
        v-model:selectedPriceTypes="selectedPriceTypes"
        :categoriesList="categoriesList"
        searchPlaceholder="Search your upcoming events..."
      />

      <div v-if="isLoading" class="text-center text-gray-500 py-24 font-medium">
        Loading scheduled records...
      </div>

      <div 
        v-else-if="upcomingEvents.length > 0 && filteredAndSortedUpcomingEvents.length === 0" 
        class="text-center bg-gray-50 dark:bg-gray-800/40 rounded-2xl py-20 border-2 border-dashed border-gray-200 dark:border-gray-700"
      >
        <p class="pi pi-question text-3xl mb-2 text-gray-400"></p>
        <h4 class="text-lg font-bold text-gray-700 dark:text-gray-300">No events match your criteria</h4>
        <p class="text-sm text-gray-400 mt-1">Try adjusting your active filter checkboxes or your search query string.</p>
      </div>

      <div
        v-else-if="upcomingEvents.length === 0"
        class="text-center py-20 text-gray-500 dark:text-gray-400 font-medium bg-gray-50 dark:bg-gray-800/40 border-2 border-dashed rounded-3xl"
      >
        No upcoming activities posted yet. Click the + button to launch your first event request!
      </div>

      <div
        v-else
        class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-2 xl:grid-cols-3 gap-6 justify-items-center"
      >
        <EventCard
          v-for="item in filteredAndSortedUpcomingEvents"
          :key="item.id"
          :event="item"
          :status="item.status"
          @click="router.push(`/society/events/${item.id}`)"
        />
      </div>

    </div>
  </div>
</div>
</template>
