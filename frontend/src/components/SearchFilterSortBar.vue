<script setup>
import { ref, onMounted, onUnmounted } from "vue";

// Define incoming configurations
defineProps({
  categoriesList: {
    type: Array,
    default: () => ["Academic", "Sports", "Cultural", "Religious"],
  },
  searchPlaceholder: {
    type: String,
    default: "Search...",
  },
});

// Bind models back to parent states
const searchQuery = defineModel("searchQuery", { type: String, default: "" });
const sortBy = defineModel("sortBy", { type: String, default: "date-asc" });
const selectedCategories = defineModel("selectedCategories", {
  type: Array,
  default: () => [],
});
const selectedPriceTypes = defineModel("selectedPriceTypes", {
  type: Array,
  default: () => [],
});

// UI Dropdown Toggling Controls
const isFilterOpen = ref(false);
const toggleFilter = () => {
  isFilterOpen.value = !isFilterOpen.value;
};

// Auto-close menu window when clicking outside anywhere on the window
const closeFilterOnOutsideClick = () => {
  if (isFilterOpen.value) isFilterOpen.value = false;
};

onMounted(() => window.addEventListener("click", closeFilterOnOutsideClick));
onUnmounted(() =>
  window.removeEventListener("click", closeFilterOnOutsideClick),
);
</script>

<template>
  <div
    class="flex flex-col sm:flex-row gap-4 items-center justify-between bg-white dark:bg-gray-800 p-4 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700"
  >
    <div class="relative w-full sm:max-w-md">
      <span
        class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none"
      >
        <i class="pi pi-search text-gray-400"></i>
      </span>
      <input
        type="text"
        v-model="searchQuery"
        :placeholder="searchPlaceholder"
        class="w-full pl-9 pr-4 py-2.5 text-sm border-2 border-gray-100 dark:border-gray-700 rounded-xl bg-gray-50/50 dark:bg-gray-900 focus:outline-none focus:border-purple-500 transition-colors dark:text-white"
      />
    </div>

    <div class="flex items-center gap-3 w-full sm:w-auto justify-end relative">
      <div class="flex items-center gap-2">
        <label
          for="sort"
          class="text-xs font-bold text-gray-400 uppercase tracking-wider whitespace-nowrap"
        >
          Sort By:
        </label>
        <select
          id="sort"
          v-model="sortBy"
          class="px-3 py-2.5 text-sm bg-gray-50/50 dark:bg-gray-900 border-2 border-gray-100 dark:border-gray-700 rounded-xl focus:outline-none focus:border-purple-500 cursor-pointer dark:text-white font-medium"
        >
          <slot name="sort-options">
            <option value="date-asc">Date: Soonest First</option>
            <option value="date-desc">Date: Furthest First</option>
            <option value="price-low">Price: Low to High</option>
            <option value="price-high">Price: High to Low</option>
          </slot>
        </select>
      </div>

      <div class="h-6 w-px bg-gray-200 dark:bg-gray-700 hidden sm:block"></div>

      <div class="relative">
        <button
          @click.stop="toggleFilter"
          type="button"
          class="flex items-center gap-2 px-4 py-2.5 text-sm bg-gray-50/50 dark:bg-gray-900 border-2 border-gray-100 dark:border-gray-700 rounded-xl hover:border-purple-500 dark:hover:border-purple-500 text-gray-700 dark:text-white transition-colors cursor-pointer font-medium"
        >
          <i class="pi pi-filter text-gray-500"></i>
        </button>

        <div
          v-if="isFilterOpen"
          @click.stop
          class="absolute right-0 mt-2 w-64 bg-white dark:bg-gray-800 p-5 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700 z-50 text-left space-y-5"
        >
          <div>
            <label
              class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-2.5"
            >
              Categories
            </label>
            <div class="space-y-2.5 max-h-48 overflow-y-auto pr-1">
              <div
                v-for="cat in categoriesList"
                :key="cat"
                class="flex items-center gap-3"
              >
                <input
                  :id="cat"
                  type="checkbox"
                  :value="cat"
                  v-model="selectedCategories"
                  class="w-3.5 h-3.5 rounded text-purple-600 accent-purple-600 border-gray-300 dark:border-gray-600 cursor-pointer"
                />
                <label
                  :for="cat"
                  class="text-xs text-gray-600 dark:text-gray-400 cursor-pointer select-none"
                >
                  {{ cat }}
                </label>
              </div>
            </div>
          </div>

          <hr class="border-gray-100 dark:border-gray-700" />

          <div>
            <label
              class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-2.5"
            >
              Admission Type
            </label>
            <div class="space-y-2.5">
              <div class="flex items-center gap-3">
                <input
                  id="type-free"
                  type="checkbox"
                  value="free"
                  v-model="selectedPriceTypes"
                  class="w-3.5 h-3.5 rounded text-purple-600 accent-purple-600 border-gray-300 dark:border-gray-600 cursor-pointer"
                />
                <label
                  for="type-free"
                  class="text-xs text-gray-600 dark:text-gray-400 cursor-pointer select-none"
                >
                  Free Events (RM0)
                </label>
              </div>
              <div class="flex items-center gap-3">
                <input
                  id="type-paid"
                  type="checkbox"
                  value="paid"
                  v-model="selectedPriceTypes"
                  class="w-3.5 h-3.5 rounded text-purple-600 accent-purple-600 border-gray-300 dark:border-gray-600 cursor-pointer"
                />
                <label
                  for="type-paid"
                  class="text-xs text-gray-600 dark:text-gray-400 cursor-pointer select-none"
                >
                  Paid Entry Tickets
                </label>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
