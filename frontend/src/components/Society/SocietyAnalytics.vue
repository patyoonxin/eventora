<script setup>
import { ref, onMounted, nextTick } from "vue";
import { useAuthStore } from "@/stores/auth";
import Chart from "chart.js/auto";

const API_BASE = import.meta.env.VITE_API_BASE_URL;
const authStore = useAuthStore();

const revenueCanvas = ref(null);
const categoryCanvas = ref(null);

const isLoading = ref(true);
const errorMessage = ref("");

// Reactive state to hold the summary cards data
const summary = ref({
  total_events: 0,
  total_revenue: 0,
});

let revenueChartInstance = null;
let categoryChartInstance = null;

onMounted(async () => {
  try {
    const response = await fetch(`${API_BASE}/api/society/analytics`, {
      headers: {
        Authorization: `Bearer ${authStore.token}`,
        Accept: "application/json",
      },
    });
    if (!response.ok) {
      const text = await response.text();
      console.error(text);
      throw new Error(`HTTP ${response.status}`);
    }

    const json = await response.json();

    if (json.status !== "success") {
      throw new Error(json.message || "Failed to fetch analytics data.");
    }

    // 1. Assign summary values
    summary.value = json.data.summary;

    console.log("Revenue Data:", json.data.revenue_over_time);
    console.log("Category Data:", json.data.popular_categories);

    // 2. Render the charts using the rest of the payload
    isLoading.value = false;

    await nextTick();

    renderRevenueChart(json.data.revenue_over_time);
    renderCategoryChart(json.data.popular_categories);

    return;
  } catch (err) {
    errorMessage.value = err.message || "Error loading dashboard metrics.";
  } finally {
    //isLoading.value = false;
  }
});

// Chart 1: Line Chart (Total Revenue Generated Over Time)
const renderRevenueChart = (rawData) => {
  console.log(revenueCanvas.value);
  console.log(rawData);
  if (!revenueCanvas.value) return;

  // Extract dates for horizontal axis labels, and revenue calculations for vertical axis
  const labels = rawData.map((item) => {
    const d = new Date(item.event_date);
    return d.toLocaleDateString("en-GB", { day: "numeric", month: "short" });
  });
  const revenueValues = rawData.map((item) => parseFloat(item.revenue));

  revenueChartInstance = new Chart(revenueCanvas.value, {
    type: "line",
    data: {
      labels: labels,
      datasets: [
        {
          label: "Revenue (RM)",
          data: revenueValues,
          borderColor: "#6366f1", // Trendy Modern Indigo
          backgroundColor: "rgba(99, 102, 241, 0.1)", // Light subtle shading under the slope line
          fill: true,
          tension: 0.3, // Give the line a slight, smooth professional curve
          borderWidth: 3,
          pointBackgroundColor: "#4f46e5",
          pointRadius: 4,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { display: false }, // Hidden because card title already specifies its use case
      },
      scales: {
        y: {
          beginAtZero: true,
          ticks: { callback: (value) => "RM " + value }, // Appends currency prefix natively
        },
      },
    },
  });
};

// Chart 2: Doughnut Chart (Most Popular Event Categories)
const renderCategoryChart = (rawData) => {
  if (!categoryCanvas.value) return;

  const labels = rawData.map((item) => item.category);
  const ticketCounts = rawData.map((item) => item.tickets_sold);

  categoryChartInstance = new Chart(categoryCanvas.value, {
    type: "doughnut",
    data: {
      labels: labels,
      datasets: [
        {
          data: ticketCounts,
          backgroundColor: [
            "#3b82f6", // Academic - Blue
            "#10b981", // Sports - Emerald
            "#f59e0b", // Cultural - Amber
            "#8b5cf6", // Religious - Purple
            "#6b7280", // Others - Gray
          ],
          borderWidth: 2,
          hoverOffset: 6,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          position: "bottom",
          labels: {
            boxWidth: 12,
            font: { size: 11 },
            padding: 16,
          },
        },
      },
    },
  });
};
</script>

<template>
  <div class="bg-gray-50 dark:bg-gray-900 p-4 sm:p-6 pb-24">
    <div v-if="isLoading" class="text-center py-20 text-gray-400 font-medium">
      Calculating parameters...
    </div>
    <div
      v-else-if="errorMessage"
      class="text-center py-20 text-red-500 font-bold"
    >
      {{ errorMessage }}
    </div>

    <div v-else class="max-w-4xl mx-auto space-y-6">
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div
          class="bg-white dark:bg-gray-800 p-5 rounded-[2rem] shadow-sm border border-gray-100 dark:border-gray-700 flex flex-col justify-between"
        >
          <div>
            <span
              class="text-xs font-bold text-gray-400 uppercase tracking-widest block"
              >Track Record</span
            >
            <h2
              class="text-xs sm:text-sm font-extrabold text-gray-500 dark:text-gray-300 uppercase mt-1"
            >
              Total Approved Events
            </h2>
          </div>
          <div
            class="text-3xl sm:text-4xl font-black text-purple-600 dark:text-purple-400 mt-4 tracking-tight"
          >
            {{ summary.total_events }}
          </div>
        </div>

        <div
          class="bg-white dark:bg-gray-800 p-5 rounded-[2rem] shadow-sm border border-gray-100 dark:border-gray-700 flex flex-col justify-between"
        >
          <div>
            <span
              class="text-xs font-bold text-gray-400 uppercase tracking-widest block"
              >Earnings</span
            >
            <h2
              class="text-xs sm:text-sm font-extrabold text-gray-500 dark:text-gray-300 uppercase mt-1"
            >
              Total Revenue
            </h2>
          </div>
          <div
            class="text-2xl sm:text-4xl font-black text-emerald-600 dark:text-emerald-400 mt-4 tracking-tight"
          >
            RM{{ Number(summary.total_revenue || 0).toFixed(2) }}
          </div>
        </div>
      </div>
      <div
        class="bg-white dark:bg-gray-800 p-5 rounded-[2rem] shadow-sm border border-gray-100 dark:border-gray-700"
      >
        <div class="mb-2">
          <span
            class="text-xs font-bold text-gray-400 uppercase tracking-widest block"
            >Financial Trends</span
          >
          <h2
            class="text-lg font-extrabold text-gray-800 dark:text-white uppercase"
          >
            Revenue Generated Over Time
          </h2>
        </div>
        <div class="relative h-64 w-full">
          <canvas ref="revenueCanvas"></canvas>
        </div>
      </div>

      <div
        class="bg-white dark:bg-gray-800 p-5 rounded-[2rem] shadow-sm border border-gray-100 dark:border-gray-700"
      >
        <div class="mb-4">
          <span
            class="text-xs font-bold text-gray-400 uppercase tracking-widest block"
            >Student Demographics</span
          >
          <h2
            class="text-lg font-extrabold text-gray-800 dark:text-white uppercase"
          >
            Most Popular Event Categories
          </h2>
        </div>
        <div class="relative h-64 w-full">
          <canvas ref="categoryCanvas"></canvas>
        </div>
      </div>
    </div>
  </div>
</template>
