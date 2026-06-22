<template>
  <div class="min-h-screen bg-white dark:bg-gray-900 pb-16">
    <div class="px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto py-8">
      <h1 class="text-3xl font-bold text-gray-900 dark:text-white mb-4">My Tickets</h1>
      <p class="text-gray-600 dark:text-gray-400 mb-6">View all your event tickets and access your digital pass.</p>

      <div v-if="isLoading" class="text-center text-gray-500 py-24 font-medium">Loading your tickets...</div>
      <div v-else-if="errorMessage" class="text-center text-red-500 py-24">{{ errorMessage }}</div>
      <div v-else>
        <div v-if="tickets.length === 0" class="text-center bg-gray-50 dark:bg-gray-900 rounded-3xl p-10 border border-dashed border-gray-200 dark:border-gray-700">
          <p class="text-2xl font-semibold text-gray-700 dark:text-gray-300">No tickets yet</p>
          <p class="mt-2 text-gray-500 dark:text-gray-400">Register for events to see your tickets here.</p>
        </div>

        <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div v-for="ticket in tickets" :key="ticket.ticket_id" class="relative overflow-hidden rounded-[2rem] border border-gray-200 dark:border-gray-800 p-6 shadow-sm" :style="ticketBackgroundStyle(ticket)">
            <div class="absolute inset-0 bg-black/35"></div>
            <div class="relative">
              <div class="flex items-start justify-between gap-4">
                <div>
                  <h2 class="text-xl font-bold text-gray-900 dark:text-white">{{ ticket.event_title }}</h2>
                  <p class="text-sm text-gray-500 dark:text-gray-400">{{ formatDate(ticket.starts_at) }} • {{ ticket.venue }}</p>
                </div>
                <span class="inline-flex items-center rounded-full bg-purple-100 text-purple-700 px-3 py-1 text-xs font-semibold uppercase tracking-[0.16em] dark:bg-purple-950/50 dark:text-purple-300">
                  {{ ticket.ticket_status }}
                </span>
              </div>

              <div class="mt-4 grid grid-cols-2 gap-3 text-sm text-gray-600 dark:text-gray-300">
                <div>
                  <div class="font-semibold">Ticket</div>
                  <div>#{{ ticket.ticket_number }}</div>
                </div>
                <div>
                  <div class="font-semibold">Price</div>
                  <div>{{ formatPrice(ticket.price) }}</div>
                </div>
              </div>

              <div class="mt-4 grid grid-cols-2 gap-3 text-sm text-gray-600 dark:text-gray-300">
                <div>
                  <div class="font-semibold">Seat</div>
                  <div>{{ ticket.seat_number || 'Auto-assign' }}</div>
                </div>
                <div>
                  <div class="font-semibold">Payment</div>
                  <div>{{ ticket.payment_method || 'Free' }}</div>
                </div>
              </div>

              <div class="mt-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <button @click="viewTicket(ticket.ticket_id)" type="button" class="w-full sm:w-auto inline-flex items-center justify-center rounded-2xl bg-blue-600 hover:bg-blue-700 text-white font-semibold px-5 py-3 transition">
                  View Ticket
                </button>
                <button @click="cancelTicket(ticket.ticket_id)" type="button" :disabled="ticket.ticket_status !== 'valid'" class="w-full sm:w-auto inline-flex items-center justify-center rounded-2xl border border-red-300 text-red-600 font-semibold px-5 py-3 transition disabled:opacity-50 disabled:cursor-not-allowed">
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
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';

const router = useRouter();
const authStore = useAuthStore();
const API_BASE = import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000';

const tickets = ref([]);
const isLoading = ref(true);
const errorMessage = ref('');

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

onMounted(fetchTickets);
</script>
