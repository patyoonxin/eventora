<template>
  <div class="min-h-screen px-4 sm:px-6 lg:px-8 py-8 bg-white dark:bg-gray-900">
    <!-- Header section (unchanged) -->
    <div class="flex flex-col gap-3 mb-8">
      <div class="mb-6 text-left items-center justify-between pl-2">
        <h2 class="text-3xl font-extrabold text-gray-900 dark:text-white">
          Scan QR Code
        </h2>
        <p class="text-sm text-gray-400 mt-1">Use the camera to scan a student ticket and mark attendance.</p>
      </div>

      <div class="rounded-3xl bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 p-4">
        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2 text-left">
          Select event to check in
        </label>
        <div class="flex items-center gap-3 flex-col sm:flex-row">
          <select
            v-model="selectedEventId"
            class="w-full sm:w-auto min-w-[220px] rounded-2xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 px-4 py-3 shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
          >
            <option disabled value="">Choose an event</option>
            <option v-for="event in events" :key="event.id" :value="event.id">
              {{ event.title }} • {{ formatDate(event.starts_at) }}
            </option>
          </select>
          <button
            type="button"
            class="rounded-2xl bg-gradient-to-r from-purple-600 to-blue-600 dark:from-purple-500 dark:to-blue-500 text-white px-5 py-3 font-semibold transition hover:bg-blue-500 disabled:opacity-50"
            :disabled="!selectedEventId || scanning || isLoading"
            @click="toggleScanner"
          >
            {{ scanning ? "Stop scanning" : "Start scanning" }}
          </button>
        </div>
        <p class="mt-3 text-sm text-gray-500 dark:text-gray-400 text-left">
          {{ platformInfo }}
        </p>
      </div>
    </div>

    <div v-if="isLoading" class="rounded-3xl bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 p-10 text-center text-gray-500 dark:text-gray-400">
      Loading events and camera permissions...
    </div>

    <div v-else>
      <div class="grid gap-6 lg:grid-cols-[1.5fr_1fr]">
        <!-- Scanner area - only show on web -->
        <div v-if="isWeb" class="rounded-3xl bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 p-6">
          <div id="html5qr-reader" class="w-full min-h-[360px] rounded-3xl bg-black/5 dark:bg-white/5 overflow-hidden"></div>
          <div class="mt-4 space-y-3">
            <div class="flex items-center justify-between gap-4">
              <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">Camera</span>
              <select
                v-model="selectedCameraId"
                class="rounded-2xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 px-3 py-2"
                :disabled="cameraDevices.length === 0"
              >
                <option disabled value="">Select a camera</option>
                <option v-for="device in cameraDevices" :key="device.id" :value="device.id">
                  {{ device.label || `Camera ${device.id}` }}
                </option>
              </select>
            </div>
            <div class="text-sm text-gray-600 dark:text-gray-400">
              Scanned result will be sent to the organiser check-in endpoint automatically.
            </div>
          </div>
        </div>

        <!-- Status section -->
        <div class="space-y-4">
          <div class="rounded-3xl bg-white dark:bg-gray-950 border border-gray-200 dark:border-gray-800 p-6 shadow-sm">
            <div class="flex items-center justify-between">
              <div>
                <h2 class="text-xl font-bold text-gray-900 dark:text-white">Scan status</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Scan one ticket at a time for attendance.</p>
              </div>
              <span :class="[
                'inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold',
                scanning
                  ? 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-200'
                  : 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',
              ]">
                {{ scanning ? "Scanning" : "Idle" }}
              </span>
            </div>

            <div class="mt-5 space-y-3">
              <div class="rounded-3xl bg-gray-50 dark:bg-gray-900 p-4">
                <p class="text-sm text-gray-500 dark:text-gray-400">Last scan</p>
                <p class="mt-2 text-sm text-gray-800 dark:text-gray-100 break-words">
                  {{ lastScanText || "No scans yet" }}
                </p>
              </div>

              <div :class="[
                'rounded-3xl p-4',
                messageType === 'success'
                  ? 'bg-emerald-50 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200'
                  : 'bg-rose-50 text-rose-800 dark:bg-rose-900/40 dark:text-rose-200'
              ]">
                <p class="text-sm font-semibold">{{ messageTitle }}</p>
                <p class="mt-2 text-sm leading-relaxed">
                  {{ statusMessage || messageFallback }}
                </p>
              </div>
            </div>
          </div>

          <div class="rounded-3xl bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">How it works</h3>
            <ul class="mt-3 space-y-2 text-sm text-gray-600 dark:text-gray-400 text-left">
              <li>1. Select the event you are checking students in for.</li>
              <li>2. Click Start scanning and allow camera access.</li>
              <li>3. Hold the ticket QR code in front of the camera.</li>
              <li>4. The scanned ticket is sent to the server and marked checked in.</li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
<script setup>
import { ref, onMounted, onUnmounted, watch, computed } from "vue";
import { useAuthStore } from "@/stores/auth";
import { Capacitor } from "@capacitor/core";
import { BarcodeScanner } from "@capacitor-mlkit/barcode-scanning";
import { Html5Qrcode } from "html5-qrcode";

const authStore = useAuthStore();
const API_BASE = import.meta.env.VITE_API_BASE_URL || "http://localhost:8000";

// Platform detection
const isWeb = computed(() => !Capacitor.isNativePlatform());
const platformInfo = computed(() => {
  if (isWeb.value) {
    return "If the camera prompt does not appear, make sure your browser has camera access enabled.";
  }
  return "Using native Android camera with ML Kit for scanning.";
});

const events = ref([]);
const selectedEventId = ref("");
const cameraDevices = ref([]);
const selectedCameraId = ref("");
const scanning = ref(false);
const isLoading = ref(true);
const isSubmitting = ref(false);
const html5Qrcode = ref(null);
const lastScanText = ref("");
const statusMessage = ref("");
const messageType = ref("");

const messageTitle = computed(() => {
  if (messageType.value === "success") return "Checked in successfully";
  if (messageType.value === "error") return "Scan failed";
  return "Ready to scan";
});

const messageFallback = computed(() => {
  if (messageType.value === "success")
    return "The ticket was accepted and attendance has been recorded.";
  if (messageType.value === "error")
    return "Please review the scanned code or try again.";
  return "Select an event and start the scanner to begin.";
});

const formatDate = (value) => {
  if (!value) return "";
  return new Date(value).toLocaleDateString("en-GB", {
    day: "numeric",
    month: "short",
    year: "numeric",
  });
};

const loadEvents = async () => {
  try {
    const response = await fetch(`${API_BASE}/api/society/past-events`, {
      headers: {
        Authorization: `Bearer ${authStore.token}`,
        Accept: "application/json",
      },
    });
    const data = await response.json();

    if (response.ok && data.status === "success") {
      events.value = data.data || [];
      if (events.value.length && !selectedEventId.value) {
        selectedEventId.value = events.value[0].id;
      }
    } else {
      throw new Error(data.message || "Unable to load events.");
    }

    // Only load cameras on web
    if (isWeb.value) {
      await loadCameras();
    }
  } catch (error) {
    statusMessage.value = error.message || "Could not load events.";
    messageType.value = "error";
  } finally {
    isLoading.value = false;
  }
};

const loadCameras = async () => {
  try {
    const devices = await Html5Qrcode.getCameras();
    cameraDevices.value = devices || [];
    if (cameraDevices.value.length && !selectedCameraId.value) {
      selectedCameraId.value = cameraDevices.value[0].id;
    }
  } catch (error) {
    console.warn("Camera list failed:", error);
    statusMessage.value =
      "Unable to access camera devices. Please allow camera permissions.";
    messageType.value = "error";
  }
};

const startScanner = async () => {
  if (!selectedEventId.value) return;

  if (isWeb.value) {
    await startWebScanner();
  } else {
    await startAndroidScanner();
  }
};

const startWebScanner = async () => {
  if (!selectedCameraId.value) return;

  try {
    if (!html5Qrcode.value) {
      html5Qrcode.value = new Html5Qrcode("html5qr-reader");
    }

    scanning.value = true;
    statusMessage.value = "";
    messageType.value = "";

    await html5Qrcode.value.start(
      { deviceId: { exact: selectedCameraId.value } },
      { fps: 10, qrbox: 250 },
      async (decodedText) => {
        lastScanText.value = decodedText;
        await handleScan(decodedText);
      },
      (errorMessage) => {
        console.debug("QR scan error:", errorMessage);
      }
    );
  } catch (error) {
    scanning.value = false;
    statusMessage.value = error.message || "Could not start scanner.";
    messageType.value = "error";
  }
};

const startAndroidScanner = async () => {
  try {
    scanning.value = true;
    statusMessage.value = "";
    messageType.value = "";

    // Request camera permission
    const permission = await BarcodeScanner.requestPermissions();
    
    if (permission.camera !== "granted") {
      throw new Error("Camera permission denied");
    }

    // Start scanning
    const result = await BarcodeScanner.scan({
      formats: [1], // 1 = QR_CODE format
    });

    if (result && result.barcodes && result.barcodes.length > 0) {
      const qrValue = result.barcodes[0].value;
      lastScanText.value = qrValue;
      await handleScan(qrValue);
    }

    scanning.value = false;
  } catch (error) {
    scanning.value = false;
    statusMessage.value = error.message || "Could not start scanner.";
    messageType.value = "error";
    console.error("Android scanner error:", error);
  }
};

const stopScanner = async () => {
  if (isWeb.value) {
    await stopWebScanner();
  } else {
    // ML Kit scanner stops automatically after scan
    scanning.value = false;
  }
};

const stopWebScanner = async () => {
  if (!html5Qrcode.value || !scanning.value) return;
  try {
    await html5Qrcode.value.stop();
    await html5Qrcode.value.clear();
  } catch (error) {
    console.warn("Failed to stop QR scanner cleanly:", error);
  } finally {
    scanning.value = false;
  }
};

const toggleScanner = async () => {
  if (scanning.value) {
    await stopScanner();
    return;
  }
  await startScanner();
};

const handleScan = async (decodedText) => {
  if (isSubmitting.value) return;
  if (!selectedEventId.value) {
    statusMessage.value = "Choose an event before scanning.";
    messageType.value = "error";
    return;
  }

  isSubmitting.value = true;
  try {
    const response = await fetch(
      `${API_BASE}/api/society/events/${selectedEventId.value}/checkin`,
      {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Authorization: `Bearer ${authStore.token}`,
          Accept: "application/json",
        },
        body: JSON.stringify({ qr_payload: decodedText }),
      }
    );

    const data = await response.json();
    if (response.ok && data.status === "success") {
      statusMessage.value = data.message || "Checked in successfully.";
      messageType.value = "success";
    } else {
      statusMessage.value =
        data.message || "Check-in failed. Please try again.";
      messageType.value = "error";
    }
  } catch (error) {
    statusMessage.value =
      error.message || "Unable to complete attendance check-in.";
    messageType.value = "error";
  } finally {
    isSubmitting.value = false;
    await stopScanner();
  }
};

onMounted(loadEvents);

onUnmounted(async () => {
  await stopScanner();
});

watch(selectedEventId, async () => {
  if (scanning.value) {
    await stopScanner();
  }
  statusMessage.value = "";
  messageType.value = "";
});
</script>