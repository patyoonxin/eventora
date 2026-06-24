<script setup>
import { ref, onMounted, nextTick } from "vue";
import { useAuthStore } from "@/stores/auth"
import Chart from 'chart.js/auto'

const API_BASE = import.meta.env.VITE_API_BASE_URL
const authStore = useAuthStore()

// DOM Canvas Elements Hook
const impactCanvas = ref(null)
const revenueCanvas = ref(null)

// UI States
const isLoading = ref(true)
const errorMessage = ref('')

// Reactive KPI States
const summary = ref({
  total_societies: 0,
  total_events: 0,
  total_attendances: 0
})

// Keep chart instances in memory to safely destroy/recreate if needed
let impactChartInstance = null
let revenueChartInstance = null

onMounted(async () => {
  try {
    const response = await fetch(`${API_BASE}/api/admin/analytics`, {
      headers: {
        'Authorization': `Bearer ${authStore.token}`,
        'Accept': 'application/json'
      }
    })
    const json = await response.json()

    if (json.status !== 'success') {
      throw new Error(json.message || 'Failed to fetch admin metrics.')
    }

    // Populate Top Cards
    summary.value = json.data.summary

    isLoading.value = false;
    await nextTick();

    // Render Both Charts
    renderImpactChart(json.data.society_impact)
    renderRevenueChart(json.data.revenue_share)

  } catch (err) {
    errorMessage.value = err.message || 'Error compiling analytics engine.'
  } finally {
    //isLoading.value = false
  }
})

// Chart 1: Grouped Bar Chart (Activity vs Impact)
const renderImpactChart = (rawData) => {
  if (!impactCanvas.value) return
  console.log("Raw Impact Data:", rawData) // Debug log to inspect data structure
  const labels = rawData.map(item => item.society_name)
  const eventCounts = rawData.map(item => parseInt(item.total_events))
  const attendanceCounts = rawData.map(item => parseInt(item.total_attended))

  impactChartInstance = new Chart(impactCanvas.value, {
    type: 'bar',
    data: {
      labels: labels,
      datasets: [
        {
          label: 'Events Hosted',
          barThickness: 30,
          data: eventCounts,
          backgroundColor: '#c084fc', // Pastel Purple
          borderRadius: 6,
        },
        {
          label: 'Total Attendances (QR Verified)',
          data: attendanceCounts,
          backgroundColor: '#6366f1', // Vibrant Deep Indigo
          borderRadius: 6,
          barThickness: 30
        }
      ]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { position: 'bottom', labels: { boxWidth: 12, padding: 12 } }
      },
      scales: {
        y: { beginAtZero: true, ticks: { precision: 0 } },
        x: { grid: { display: false }}
      }
    }
  })
}

// Chart 2: Doughnut Chart (Campus Revenue Share)
const renderRevenueChart = (rawData) => {
  if (!revenueCanvas.value) return

  const labels = rawData.map(item => item.society_name)
  const revenues = rawData.map(item => parseFloat(item.total_revenue))

  revenueChartInstance = new Chart(revenueCanvas.value, {
    type: 'doughnut',
    data: {
      labels: labels,
      datasets: [{
        data: revenues,
        backgroundColor: [
          '#6366f1', // Indigo
          '#10b981', // Emerald
          '#f59e0b', // Amber
          '#3b82f6', // Blue
          '#ec4899', // Pink
          '#8b5cf6', // Violet
          '#9ca3af'  // Gray
        ],
        borderWidth: 2,
        hoverOffset: 4
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          position: 'bottom',
          labels: { boxWidth: 12, font: { size: 11 }, padding: 12 }
        },
        tooltip: {
          callbacks: {
            label: (context) => {
              const val = context.raw || 0
              return ` ${context.label}: RM ${val.toFixed(2)}`
            }
          }
        }
      }
    }
  })
}
</script>

<template>
  <div class="min-h-screen bg-gray-50 dark:bg-gray-900 p-4 sm:p-6 pb-24">
    
    <div class="max-w-7xl mx-auto mb-6 text-left items-center justify-between pl-2">
      <h2 class="text-3xl sm:text-3xl font-black text-gray-900 dark:text-white tracking-tight mt-2">
        Campus Oversight Metrics
      </h2>
      <p class="text-sm text-gray-400 mt-1">Consolidated verification of society events, student impact, and transactions.</p>
    </div>

    <div v-if="isLoading" class="text-center py-20 text-gray-400 font-medium">
      Parsing campus data fields...
    </div>
    <div v-else-if="errorMessage" class="text-center py-20 text-red-500 font-bold">
      {{ errorMessage }}
    </div>

    <div v-else class="max-w-4xl mx-auto space-y-6">
      
      <div class="grid grid-cols-2 gap-3 sm:gap-4">
        
        <div class="bg-white dark:bg-gray-800 p-4 sm:p-5 rounded-[2rem] shadow-sm border border-gray-100 dark:border-gray-700 flex flex-col justify-between">
          <h3 class="text-[10px] sm:text-xs font-bold text-gray-400 uppercase tracking-widest leading-tight">Societies</h3>
          <div class="text-2xl sm:text-4xl font-black text-gray-900 dark:text-white mt-3 tracking-tight">
            {{ summary.total_societies }}
          </div>
        </div>

        <div class="bg-white dark:bg-gray-800 p-4 sm:p-5 rounded-[2rem] shadow-sm border border-gray-100 dark:border-gray-700 flex flex-col justify-between">
          <h3 class="text-[10px] sm:text-xs font-bold text-gray-400 uppercase tracking-widest leading-tight">Total Events</h3>
          <div class="text-2xl sm:text-4xl font-black text-purple-600 dark:text-purple-400 mt-3 tracking-tight">
            {{ summary.total_events }}
          </div>
        </div>

      </div>

      <div class="bg-white dark:bg-gray-800 p-5 rounded-[2rem] shadow-sm border border-gray-100 dark:border-gray-700">
        <div class="mb-4">
          <span class="text-xs font-bold text-gray-400 uppercase tracking-widest block">Engagement Index</span>
          <h2 class="text-base sm:text-lg font-extrabold text-gray-800 dark:text-white uppercase">
            Society Activity vs. Student Impact
          </h2>
        </div>
        <div class="relative h-80 w-full">
          <canvas ref="impactCanvas"></canvas>
        </div>
      </div>

      <div class="bg-white dark:bg-gray-800 p-5 rounded-[2rem] shadow-sm border border-gray-100 dark:border-gray-700">
        <div class="mb-4">
          <span class="text-xs font-bold text-gray-400 uppercase tracking-widest block">Financial Distribution</span>
          <h2 class="text-base sm:text-lg font-extrabold text-gray-800 dark:text-white uppercase">
            Campus Revenue Share (RM)
          </h2>
        </div>
        <div class="relative h-64 w-full">
          <canvas ref="revenueCanvas"></canvas>
        </div>
      </div>

    </div>
  </div>
</template>