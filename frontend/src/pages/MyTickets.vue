<template>
  <div class="min-h-screen px-4 sm:px-6 lg:px-8 py-8 bg-white dark:bg-gray-900">
  
      <div class="max-w-7xl mx-auto mb-4 text-left">
        <h2 class="text-3xl font-extrabold text-gray-900 dark:text-white">
          My Tickets
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
          Loading your tickets...
        </div>
        <div v-else-if="errorMessage" class="text-center text-red-500 py-24">
          {{ errorMessage }}
        </div>
        <div v-else>
          <div
            v-if="filteredTickets.length === 0"
            class="text-center bg-gray-50 dark:bg-gray-900 rounded-3xl p-10 border border-dashed border-gray-200 dark:border-gray-700"
          >
            <p class="text-2xl font-semibold text-gray-700 dark:text-gray-300">
              No tickets found
            </p>
            <p class="mt-2 text-gray-500 dark:text-gray-400">
              Try adjusting your search query or register for events.
            </p>
          </div>

          <div
            v-else
            class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-2 gap-6"
          >
            <div
              v-for="ticket in filteredTickets"
              :key="ticket.ticket_id"
              class="relative overflow-hidden rounded-[2rem] border border-gray-200 dark:border-gray-800 p-6 shadow-sm"
              :style="ticketBackgroundStyle(ticket)"
            >
              <div class="absolute inset-0 bg-black/40"></div>

              <div class="relative">
                <div class="flex items-start justify-between gap-4">
                  <div>
                    <p class="text-xl font-bold text-white text-left">
                      {{ ticket.event_title }}
                    </p>
                    <p class="text-sm text-gray-200 text-left">
                      {{ formatDate(ticket.starts_at) }} • {{ ticket.venue }}
                    </p>
                  </div>
                  <span
                    class="inline-flex items-center rounded-full bg-purple-500/20 text-purple-200 border border-purple-400/30 px-3 py-1 text-xs font-semibold uppercase tracking-[0.16em]"
                  >
                    {{ ticket.ticket_status }}
                  </span>
                </div>

                <div class="mt-4 grid grid-cols-2 gap-3 text-sm text-gray-200">
                  <div>
                    <div class="font-semibold text-gray-300">Ticket</div>
                    <div>#{{ ticket.ticket_number }}</div>
                  </div>
                  <div>
                    <div class="font-semibold text-gray-300">Price</div>
                    <div>{{ formatPrice(ticket.price) }}</div>
                  </div>
                </div>

                <div class="mt-4 grid grid-cols-2 gap-3 text-sm text-gray-200">
                  <div>
                    <div class="font-semibold text-gray-300">Seat</div>
                    <div>{{ ticket.seat_number || "Auto-assign" }}</div>
                  </div>
                  <div>
                    <div class="font-semibold text-gray-300">Payment</div>
                    <div>{{ ticket.payment_method || "Free" }}</div>
                  </div>
                </div>

                <div class="mt-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                  <button
                    @click="viewTicket(ticket.ticket_id)"
                    type="button"
                    class="w-full sm:w-auto inline-flex items-center justify-center bg-gradient-to-r from-purple-600 to-blue-600 dark:from-purple-500 dark:to-blue-500 rounded-2xl text-white font-semibold px-5 py-3 transition"
                  >
                    View Ticket
                  </button>
                  <button
                    @click="cancelTicket(ticket.ticket_id)"
                    type="button"
                    :disabled="ticket.ticket_status !== 'valid'"
                    class="w-full sm:w-auto inline-flex items-center justify-center rounded-2xl border border-red-400/50 text-red-400 hover:bg-red-500/10 font-semibold px-5 py-3 transition disabled:opacity-40 disabled:cursor-not-allowed"
                  >
                    Cancel
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import FilterSortBar from "@/components/SearchFilterSortBar.vue";

const router = useRouter();
const authStore = useAuthStore();
const API_BASE = import.meta.env.VITE_API_BASE_URL;

const tickets = ref([]);
const isLoading = ref(true);
const errorMessage = ref('');

// UI Filter State management variables
const searchQuery = ref("");
const sortBy = ref("date-asc");
const selectedCategories = ref([]);
const selectedPriceTypes = ref([]);

const categoriesList = ["Academic", "Sports", "Cultural", "Religious"];

const fetchTickets = async () => {
  try {
    const response = await fetch(`${API_BASE}/api/users/tickets`, {
      headers: {
        Authorization: `Bearer ${authStore.token}`,
        Accept: 'application/json',
      },
    });

    const json = await response.json();
    if (!response.ok) {
      throw new Error(json.message || 'Unable to get your tickets');
    }

    tickets.value = json.data || [];
  } catch (err) {
    errorMessage.value = err.message || 'Failed to load tickets.';
    console.error('Ticket fetch error:', err);
  } finally {
    isLoading.value = false;
  }
};

const formatDate = (dateString) => {
  if (!dateString) return '';
  return new Date(dateString).toLocaleDateString('en-GB', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  });
};

const formatPrice = (price) => {
  if (price === null || price === undefined) return 'RM0';
  return parseFloat(price) === 0 ? 'RM0' : `RM${parseFloat(price).toFixed(2)}`;
};

const ticketBackgroundStyle = (ticket) => {
  if (!ticket.image_path) return {};
  return {
    backgroundImage: `linear-gradient(rgba(15,23,42,0.45), rgba(15,23,42,0.45)), url(${API_BASE}/${ticket.image_path})`,
    backgroundSize: 'cover',
    backgroundPosition: 'center',
  };
};

const viewTicket = (ticketId) => {
  router.push({ name: 'TicketDetail', params: { id: ticketId } });
};

const cancelTicket = async (ticketId) => {
  try {
    const response = await fetch(`${API_BASE}/api/users/tickets/${ticketId}`, {
      method: 'DELETE',
      headers: {
        Authorization: `Bearer ${authStore.token}`,
        Accept: 'application/json',
      },
    });

    const json = await response.json();
    if (!response.ok) {
      throw new Error(json.message || 'Failed to cancel ticket.');
    }

    tickets.value = tickets.value.map((ticket) => {
      if (ticket.ticket_id === ticketId) {
        return { ...ticket, ticket_status: 'cancelled' };
      }
      return ticket;
    });
  } catch (err) {
    errorMessage.value = err.message || 'Unable to cancel ticket.';
    console.error('Cancel ticket error:', err);
  }
};

// Fixed logic to point directly to 'tickets' instead of 'events'
const filteredTickets = computed(() => {
  let result = [...tickets.value];

  // 1. Search Box Filter execution
  if (searchQuery.value.trim()) {
    const query = searchQuery.value.toLowerCase().trim();
    result = result.filter(
      (ticket) =>
        ticket.event_title?.toLowerCase().includes(query) ||
        ticket.venue?.toLowerCase().includes(query) ||
        ticket.ticket_number?.toLowerCase().includes(query)
    );
  }

  // 2. Fallback basic Sorting Pipeline (Handles date ordering if picked)
  result.sort((a, b) => {
    if (sortBy.value === "date-asc") return new Date(a.starts_at) - new Date(b.starts_at);
    if (sortBy.value === "date-desc") return new Date(b.starts_at) - new Date(a.starts_at);
    return 0;
  });

  return result;
});

onMounted(fetchTickets);
</script>