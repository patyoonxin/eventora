<script setup>
import { ref, onMounted } from "vue";
import { useRoute, useRouter } from "vue-router";

const route = useRoute();
const router = useRouter();
const API_BASE = import.meta.env.VITE_API_BASE_URL || "http://localhost:8000";

const status = ref("verifying");
const message = ref("Verifying your payment, please wait...");
const ticketId = ref(null);
const sessionId = route.query.session_id || "";

const verifyPayment = async () => {
  if (!sessionId) {
    status.value = "error";
    message.value = "Missing Stripe session ID. Please try again.";
    return;
  }

  try {
    const response = await fetch(
      `${API_BASE}/api/events/verify-payment?session_id=${encodeURIComponent(sessionId)}`,
      {
        headers: {
          Accept: "application/json",
        },
      },
    );

    const data = await response.json();

    if (!response.ok || data.status !== "success") {
      status.value = "error";
      message.value = data.message || "Payment verification failed. Please contact support.";
      return;
    }

    ticketId.value = data.ticket_id ?? null;
    status.value = "success";
    message.value = data.message || "Payment confirmed successfully.";
  } catch (err) {
    status.value = "error";
    message.value = err?.message || "An unexpected error occurred while verifying payment.";
    console.error("Stripe verification error:", err);
  }
};

const goToTicket = () => {
  if (ticketId.value) {
    router.push({ name: "TicketDetail", params: { id: ticketId.value } });
  } else {
    router.push({ name: "MyTickets" });
  }
};

const goHome = () => {
  router.push({ name: "Home" });
};

onMounted(() => {
  verifyPayment();
});
</script>

<template>
  <div class="min-h-screen bg-white dark:bg-gray-950 px-4 py-12 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-3xl">
      <div class="rounded-[2rem] border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 p-8 shadow-lg">
        <div class="space-y-6 text-center">
          <div v-if="status === 'verifying'" class="space-y-4">
            <div class="mx-auto flex h-20 w-20 items-center justify-center rounded-full bg-blue-100 text-blue-600 dark:bg-blue-950 dark:text-blue-200">
              <span class="text-3xl">⏳</span>
            </div>
            <h1 class="text-3xl font-extrabold text-gray-900 dark:text-white">Verifying payment</h1>
            <p class="text-sm text-gray-600 dark:text-gray-300">Please stay on this page while we confirm your Stripe payment.</p>
          </div>

          <div v-if="status === 'success'" class="space-y-4">
            <div class="mx-auto flex h-20 w-20 items-center justify-center rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-200">
              <span class="text-3xl">✅</span>
            </div>
            <h1 class="text-3xl font-extrabold text-gray-900 dark:text-white">Payment successful</h1>
            <p class="text-sm text-gray-600 dark:text-gray-300">{{ message }}</p>
            <button
              @click="goToTicket"
              class="inline-flex items-center justify-center rounded-3xl bg-gradient-to-r from-blue-600 to-purple-600 px-6 py-3 text-white shadow-lg shadow-purple-500/20 hover:opacity-95 focus:outline-none"
            >
              View ticket details
            </button>
          </div>

          <div v-if="status === 'error'" class="space-y-4">
            <div class="mx-auto flex h-20 w-20 items-center justify-center rounded-full bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-200">
              <span class="text-3xl">❌</span>
            </div>
            <h1 class="text-3xl font-extrabold text-gray-900 dark:text-white">Payment verification failed</h1>
            <p class="text-sm text-gray-600 dark:text-gray-300">{{ message }}</p>
            <div class="flex flex-col gap-3 sm:flex-row justify-center">
              <button
                @click="goHome"
                class="inline-flex items-center justify-center rounded-3xl border border-gray-300 bg-white px-6 py-3 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-200"
              >
                Return to home
              </button>
              <button
                @click="goToTicket"
                class="inline-flex items-center justify-center rounded-3xl bg-blue-600 px-6 py-3 text-sm font-semibold text-white hover:bg-blue-700"
              >
                View my tickets
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
