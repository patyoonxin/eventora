<template>
  <div class="min-h-screen bg-white dark:bg-gray-900 pb-16">
    <div class="px-4 sm:px-6 lg:px-8 py-8 max-w-3xl mx-auto">
      
      <div v-if="isLoading" class="text-center text-gray-500 py-24">
        Loading ticket...
      </div>
      <div v-else-if="errorMessage" class="text-center text-red-500 py-24">
        {{ errorMessage }}
      </div>
      <div
        v-else
        class="rounded-[2rem] bg-white dark:bg-gray-950 border border-gray-200 dark:border-gray-800 p-8 shadow-sm"
      >
        <div class="flex flex-col gap-6">
          <button
       @click="router.push('/tickets')"
        class="top-5 left-5 w-12 h-12 flex items-center justify-center bg-white dark:bg-gray-800 text-gray-800 dark:text-white rounded-full shadow-lg"
      >
        <span class="text-xl font-bold">←</span>
      </button>
          <div>
            <h2 class="text-3xl font-bold text-gray-900 dark:text-white">
              {{ ticket.event_title }}
            </h2>
            <p class="text-gray-500 dark:text-gray-400 mt-2">
              {{ formatDate(ticket.starts_at) }} • {{ ticket.venue }}
            </p>
          </div>

          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div class="rounded-3xl bg-gray-50 dark:bg-gray-900 p-5">
              <div
                class="text-xs uppercase text-gray-500 dark:text-gray-400 tracking-[0.25em] mb-2"
              >
                Ticket
              </div>
              <div class="text-4xl font-black text-gray-900 dark:text-white">
                {{ ticket.ticket_number }}
              </div>
              <div class="mt-3 text-sm text-gray-500 dark:text-gray-400">
                Status: {{ ticket.ticket_status }}
              </div>
            </div>
            <div class="rounded-3xl bg-gray-50 dark:bg-gray-900 p-5">
              <div
                class="text-xs uppercase text-gray-500 dark:text-gray-400 tracking-[0.25em] mb-2"
              >
                Issued
              </div>
              <div class="text-lg font-semibold text-gray-900 dark:text-white">
                {{ formatDate(ticket.issued_at) }}
              </div>
              <div class="mt-3 text-sm text-gray-500 dark:text-gray-400">
                Price: {{ formatPrice(ticket.price) }}
              </div>
            </div>
          </div>

          <div
            class="rounded-3xl bg-gradient-to-r from-blue-600 to-purple-500 text-white p-6 text-center"
          >
            <div class="text-sm uppercase tracking-[0.25em] opacity-90">
              Scan to check in
            </div>
            <div
              class="mt-6 bg-white/10 rounded-3xl p-4 break-words text-sm font-medium"
            >
              <img
                v-if="qrSrc"
                :src="qrSrc"
                alt="Ticket QR"
                class="mx-auto w-48 h-48"
              />
              <div v-else class="truncate">{{ ticket.qr_payload }}</div>
            </div>
          </div>

          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div class="rounded-3xl bg-gray-50 dark:bg-gray-900 p-5">
              <div
                class="text-xs uppercase text-gray-500 dark:text-gray-400 tracking-[0.25em] mb-2"
              >
                Event description
              </div>
              <p
                class="text-sm leading-relaxed text-gray-600 dark:text-gray-300"
              >
                {{ ticket.event_description }}
              </p>
            </div>

            <div class="rounded-3xl bg-gray-50 dark:bg-gray-900 p-5">
              <div
                class="text-xs uppercase text-gray-500 dark:text-gray-400 tracking-[0.25em] mb-2"
              >
                Category tags
              </div>
              <div class="flex flex-wrap gap-2">
                <span
                  v-for="tag in tagsArray"
                  :key="tag"
                  class="inline-flex items-center rounded-full bg-white/10 px-3 py-1 text-xs font-semibold text-gray-600 dark:text-white"
                  >{{ tag }}</span
                >
              </div>
            </div>
          </div>

          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 mt-4">
            <div class="rounded-3xl bg-gray-50 dark:bg-gray-900 p-5">
              <div
                class="text-xs uppercase text-gray-500 dark:text-gray-400 tracking-[0.25em] mb-2"
              >
                Seat assignment
              </div>
              <div class="text-lg font-semibold text-gray-900 dark:text-white">
                {{ ticket.seat_number || "TBD" }}
              </div>
            </div>
            <div class="rounded-3xl bg-gray-50 dark:bg-gray-900 p-5">
              <div
                class="text-xs uppercase text-gray-500 dark:text-gray-400 tracking-[0.25em] mb-2"
              >
                Payment method
              </div>
              <div class="text-lg font-semibold text-gray-900 dark:text-white">
                {{ ticket.payment_method || "Free" }}
              </div>
            </div>
          </div>

          <div class="mt-4 flex flex-col sm:flex-row gap-3 items-center justify-center">
            <a
              v-if="googleCalendarUrl"
              :href="googleCalendarUrl"
              target="_blank"
              rel="noreferrer noopener"
              class="inline-flex items-center justify-center px-6 py-4 rounded-3xl bg-gradient-to-r from-purple-600 to-blue-600 dark:from-purple-500 dark:to-blue-500  text-white font-semibold hover:bg-blue-700 transition"
            >
              Add to Google Calendar
            </a>
          </div>

          <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            Share this ticket code only with event staff and do not disclose it
            publicly.
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useAuthStore } from "@/stores/auth";
import QRCode from "qrcode";

const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();
const ticketId = route.params.id;
const API_BASE = import.meta.env.VITE_API_BASE_URL || "http://localhost:8000";

const ticket = ref(null);
const qrSrc = ref("");
const isLoading = ref(true);
const errorMessage = ref("");

const tagsArray = computed(() => {
  if (!ticket.value?.category_tags) return [];
  return ticket.value.category_tags.split(",").map((tag) => tag.trim());
});

const formatDate = (dateString) => {
  if (!dateString) return "";
  return new Date(dateString).toLocaleDateString("en-GB", {
    day: "numeric",
    month: "long",
    year: "numeric",
  });
};

const formatPrice = (price) => {
  if (price === null || price === undefined) return "RM0";
  return parseFloat(price) === 0 ? "RM0" : `RM${parseFloat(price).toFixed(2)}`;
};

const googleCalendarUrl = computed(() => {
  if (!ticket.value || !ticket.value.starts_at || !ticket.value.event_title)
    return "";

  const startDate = new Date(ticket.value.starts_at);
  const endDate = ticket.value.ends_at
    ? new Date(ticket.value.ends_at)
    : new Date(startDate.getTime() + 2 * 60 * 60 * 1000);

  const formatDateForCalendar = (date) => {
    const pad = (n) => String(n).padStart(2, "0");
    return `${date.getUTCFullYear()}${pad(date.getUTCMonth() + 1)}${pad(date.getUTCDate())}T${pad(date.getUTCHours())}${pad(date.getUTCMinutes())}00Z`;
  };

  const title = encodeURIComponent(ticket.value.event_title);
  const details = encodeURIComponent(
    `Ticket #${ticket.value.ticket_number} • Seat ${ticket.value.seat_number || "N/A"}`,
  );
  const location = encodeURIComponent(ticket.value.venue || "");
  const dates = `${formatDateForCalendar(startDate)}/${formatDateForCalendar(endDate)}`;

  return `https://www.google.com/calendar/render?action=TEMPLATE&text=${title}&dates=${dates}&details=${details}&location=${location}&sf=true&output=xml`;
});

const fetchTicket = async () => {
  try {
    const response = await fetch(`${API_BASE}/api/users/tickets/${ticketId}`, {
      headers: {
        Authorization: `Bearer ${authStore.token}`,
        Accept: "application/json",
      },
    });

    const text = await response.text();
    let json = null;
    try {
      json = JSON.parse(text);
    } catch (e) {
      /* ignore parse errors */
    }

    if (!response.ok) {
      if (response.status === 401) {
        // Demo fallback for local testing when unauthenticated
        ticket.value = {
          ticket_id: ticketId,
          ticket_number: String(ticketId).padStart(5, "0"),
          ticket_status: "demo",
          issued_at: new Date().toISOString(),
          event_title: "Demo Event (local)",
          event_description: "This is a demo view for local QR testing.",
          venue: "Demo Venue",
          starts_at: new Date().toISOString(),
          price: 0,
          category_tags: "Academic",
          qr_payload: `demo://ticket/${ticketId}`,
        };
      } else {
        throw new Error(json?.message || text || "Failed to load ticket.");
      }
    } else {
      ticket.value = json?.data;
    }

    // Generate QR code data URL
    try {
      qrSrc.value = await QRCode.toDataURL(
        ticket.value.qr_payload || ticket.value.ticket_number,
        { margin: 1, width: 360 },
      );
    } catch (qrErr) {
      console.error("QR generation failed:", qrErr);
      qrSrc.value = "";
    }
  } catch (err) {
    errorMessage.value = err.message || "Unable to fetch ticket details.";
    console.error("Ticket detail fetch error:", err);
  } finally {
    isLoading.value = false;
  }
};

onMounted(fetchTicket);
</script>
