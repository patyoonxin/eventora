<script setup>
import { computed } from "vue";
import { useRouter } from "vue-router";

const props = defineProps({
  event: {
    type: Object,
    required: true,
  },
  // for ticket number
  ticketNumber: {
    type: [String, Number],
    default: "",
  },
  // for socity dashboard status badge
  status: {
    type: String,
    default: "", 
  }
});

const router = useRouter();

// Dynamically split the comma-separated tag string from our database into a usable clean array
const tagsArray = computed(() => {
  if (!props.event.category_tags) return [];
  return props.event.category_tags.split(",").map((tag) => tag.trim());
});

const getTagStyles = (tag) => {
  const cleanTag = tag.toLowerCase();

  const styleMap = {
    academic:
      "bg-blue-50 text-blue-600 border-blue-200 dark:bg-blue-950/40 dark:text-blue-300 dark:border-blue-800/50",
    sports:
      "bg-emerald-50 text-emerald-600 border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-800/50",
    cultural:
      "bg-amber-50 text-amber-600 border-amber-200 dark:bg-amber-950/40 dark:text-amber-300 dark:border-amber-800/50",
    religious:
      "bg-purple-50 text-purple-600 border-purple-200 dark:bg-purple-950/40 dark:text-purple-300 dark:border-purple-800/50",
  };

  // Fallback style just in case a tag doesn't match one of the 4 types
  return (
    styleMap[cleanTag] ||
    "bg-gray-50 text-gray-600 border-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-700"
  );
};

// Helper function to turn SQL datetime string into a beautiful Malaysian/UTM student friendly format
const formatDate = (dateString) => {
  const date = new Date(dateString);
  return date.toLocaleDateString("en-GB", {
    day: "numeric",
    month: "long",
    year: "numeric",
  });
};

// Helper to handle zero-pricing elegantly
const formatPrice = (price) => {
  const numericPrice = parseFloat(price);
  return numericPrice === 0 ? "RM0" : `RM${numericPrice.toFixed(2)}`;
};

// Compute dynamic background and text colors for the society event status pill
const getStatusStyles = computed(() => {
  if (!props.status) return ""
  const state = props.status.toLowerCase()
  
  const styles = {
    approved: "bg-purple-100 text-purple-700 border-purple-200 dark:bg-purple-950/50 dark:text-purple-300 dark:border-purple-800",
    pending: "bg-amber-100 text-amber-700 border-amber-200 dark:bg-amber-950/50 dark:text-amber-300 dark:border-amber-800",
    rejected: "bg-red-100 text-red-700 border-red-200 dark:bg-red-950/50 dark:text-red-300 dark:border-red-800",
    cancelled: "bg-gray-100 text-gray-700 border-gray-200 dark:bg-gray-800 dark:text-gray-400 dark:border-gray-700"
  }
  return styles[state] || styles.pending
})
</script>

<template>
  <div
    @click="
      props.ticketNumber
        ? router.push(`/past-events/${props.event.id}`)
        : router.push(`/events/${props.event.id}`)
    "
    class="cursor-pointer transition-all w-full max-w-sm"
  >
    <div
      class="bg-white dark:bg-gray-800 rounded-3xl shadow-md border border-gray-100 dark:border-gray-700 overflow-hidden transition-all duration-300 hover:shadow-xl hover:-translate-y-1"
    >
      <div
        class="relative w-full h-48 bg-purple-200 dark:bg-purple-900 overflow-hidden"
      >
        <img
          :src="
            props.event.image_path ||
            'https://via.placeholder.com/600x400?text=No+Image+Available'
          "
          :alt="props.event.title"
          class="w-full h-full object-cover"
        />
        <div 
          v-if="props.status" 
          :class="['absolute top-4 left-4 text-xs font-bold uppercase tracking-wider px-3 py-1.5 rounded-full border backdrop-blur-sm shadow-sm', getStatusStyles]"
        >
          {{ props.status }}
        </div>

        <!-- <div class="absolute top-4 left-4 bg-blue-400/90 backdrop-blur-sm text-white text-xs font-semibold px-4 py-1.5 rounded-full shadow-sm">
        Recommend To You
      </div> -->

        <!-- <button 
        type="button" 
        class="absolute top-4 right-4 text-2xl drop-shadow-md transition-transform duration-200 hover:scale-125 focus:outline-none"
        aria-label="Favorite event"
      >
        ⭐
      </button> -->
      </div>

      <div class="p-6 text-left">
        <h3
          class="text-xl font-bold text-gray-800 dark:text-white truncate mb-1"
        >
          {{ props.event.title }}
        </h3>

        <p
          class="text-xs font-medium text-purple-600 dark:text-purple-400 mb-2"
        >
          By {{ props.event.society_name }}
        </p>

        <p
          class="text-gray-500 dark:text-gray-400 text-sm leading-relaxed line-clamp-2 mb-4"
        >
          {{ props.event.description || "No description provided." }}
        </p>

        <div
          class="flex items-center justify-between flex-wrap gap-y-3 pt-2 border-t border-gray-50 dark:border-gray-700"
        >
          <div
            class="text-gray-400 dark:text-gray-400 text-xs font-medium flex items-center space-x-1.5"
          >
            <span class="font-semibold text-gray-600 dark:text-gray-300">{{
              formatPrice(props.event.price)
            }}</span>
            <span>|</span>
            <span>{{ formatDate(props.event.starts_at) }}</span>
            <span>|</span>
            <span class="truncate max-w-[100px]">{{ props.event.venue }}</span>
          </div>

          <div class="flex items-center space-x-2">
            <span
              v-for="(tag, index) in tagsArray"
              :key="index"
              :class="[
                'px-3 py-1 text-xs font-bold rounded-full border',
                getTagStyles(tag),
              ]"
            >
              {{ tag }}
            </span>

            <span
              v-if="props.ticketNumber"
              class="text-sm font-semibold text-gray-400 dark:text-gray-500 tracking-wider whitespace-nowrap pl-1"
            >
              TKNO: {{ props.ticketNumber }}
            </span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
