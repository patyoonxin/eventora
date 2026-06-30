<script setup>
import { ref, onMounted, computed } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useAuthStore } from "@/stores/auth"; 

const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();

const API_BASE = import.meta.env.VITE_API_BASE_URL;
const isEditMode = computed(() => !!route.params.id);

const form = ref({
  title: "",
  description: "",
  price: 0.00,
  starts_at: "",
  ends_at: "",
  venue: "",
  capacity: 0,
  categories: [],
  image: null,
  document: null,
});

const previewImage = ref(null);

// existing files
const existingImage = ref(null);
const existingDoc = ref(null);

const categoriesList = ["Academic", "Sports", "Cultural", "Religious"];


onMounted(async () => {
  const token = authStore.token;

  if (!token) {
    router.replace('/login');
    return;
  }

  try {
    const accessResponse = await fetch(`${API_BASE}/api/society/upcoming-events`, {
      headers: {
        Authorization: `Bearer ${token}`,
      },
    });

    if (!accessResponse.ok) {
      alert("You no longer have access to manage events for this society.");
      router.replace('/society/home');
      return;
    }
  } catch {
    alert("Unable to verify society access.");
    router.replace('/society/home');
    return;
  }

  if (!isEditMode.value) return;

  const response = await fetch(
    `${API_BASE}/api/society/events/${route.params.id}`,
    {
      headers: {
        Authorization: `Bearer ${token}`,
      },
    },
  );

  if (!response.ok) {
    console.error(await response.text());
    return;
  }

  const json = await response.json();

  if (json.status === "success") {
    const event = json.data;

    form.value.title = event.title;
    form.value.description = event.description;
    form.value.price = event.price;
    form.value.venue = event.venue;
    form.value.capacity = event.capacity;

    form.value.starts_at = event.starts_at ? event.starts_at.slice(0, 16) : "";

    form.value.ends_at = event.ends_at ? event.ends_at.slice(0, 16) : "";

    // ✅ convert DB string → array
    form.value.categories = event.category_tags
      ? event.category_tags.split(",").map((c) => c.trim())
      : [];

    previewImage.value = event.image_path
      ? `${API_BASE}/${event.image_path}`
      : null;

    existingImage.value = event.image_path;
    existingDoc.value = event.supporting_document;
  }
});

const handleFile = (e, type) => {
  const file = e.target.files[0];
  form.value[type] = file;

  if (type === "image") {
    previewImage.value = URL.createObjectURL(file);
  }
};

const toggleCategory = (cat) => {
  const index = form.value.categories.indexOf(cat);

  if (index === -1) {
    form.value.categories.push(cat);
  } else {
    form.value.categories.splice(index, 1);
  }
};

const handleSave = async () => {
  const formData = new FormData();

  formData.append("title", form.value.title);
  formData.append("description", form.value.description);
  formData.append("price", form.value.price);
  formData.append("venue", form.value.venue);
  formData.append("starts_at", form.value.starts_at);
  formData.append("ends_at", form.value.ends_at);
  formData.append("capacity", form.value.capacity);
  formData.append(
    "category_tags",
    form.value.categories.join(",")
  );

  if (form.value.image) {
    formData.append("image", form.value.image);
  }

  if (form.value.document) {
    formData.append("document", form.value.document);
  }

  // Only send existing files in edit mode
  if (isEditMode.value) {
    formData.append(
      "existing_image",
      existingImage.value || ""
    );

    formData.append(
      "existing_doc",
      existingDoc.value || ""
    );
  }

  const endpoint = isEditMode.value
    ? `${API_BASE}/api/society/events/${route.params.id}/update`
    : `${API_BASE}/api/society/events/add`;

  const response = await fetch(endpoint, {
    method: "POST",
    headers: {
      "Authorization": `Bearer ${authStore.token}`,
    },
    body: formData,
  });

  const json = await response.json();

  if (!response.ok) {
    alert(json.message || "Operation failed");
    return;
  }

  alert(
    isEditMode.value
      ? "Event updated successfully"
      : "Event created successfully"
  );

  if (isEditMode.value) {
    router.push(`/society/events/${route.params.id}`);
  } else {
    router.push(`/society/events/${json.event_id}`);
  }
};
</script>

<template>
  <div class="max-w-xl mx-auto p-4 space-y-6 text-left relative">
    
    <div class="relative">
      <button 
        @click.stop="router.back()" 
        type="button"
        class="absolute top-5 left-5 z-10 w-12 h-12 flex items-center justify-center bg-white dark:bg-gray-800 text-gray-800 dark:text-white rounded-full shadow-lg hover:scale-105 active:scale-95 transition-all"
        aria-label="Go back"
      >
        <span class="text-xl font-bold">←</span>
      </button>

      <div
        @click="$refs.imgInput.click()"
        class="h-64 bg-purple-200 rounded-[2.5rem] flex items-center justify-center cursor-pointer overflow-hidden relative"
      >
        <img
          v-if="previewImage"
          :src="previewImage"
          class="w-full h-full object-cover"
          alt="Preview"
        />
        <span v-else class="text-gray-500 font-medium">Upload Photo Here</span>

        <input
          id="image-upload"
          type="file"
          ref="imgInput"
          hidden
          @change="(e) => handleFile(e, 'image')"
          accept="image/*"
        />
      </div>
    </div>

    <div class="space-y-1">
      <label for="title" class="text-xs font-semibold uppercase tracking-wider text-purple-600 block px-1">Event Title</label>
      <input
        id="title"
        v-model="form.title"
        class="text-2xl font-bold w-full border-b focus:outline-none py-1"
        placeholder="Title ..."
      />
    </div>

    <div class="space-y-5">
      <div class="space-y-1">
        <label for="description" class="text-xs font-semibold uppercase tracking-wider text-gray-500 block px-1">Description</label>
        <textarea
          id="description"
          v-model="form.description"
          placeholder="Describe your event..."
          class="w-full p-4 bg-gray-50 rounded-2xl focus:ring-2 focus:ring-purple-300 focus:outline-none"
        />
      </div>

      <div class="space-y-1">
        <label for="price" class="text-xs font-semibold uppercase tracking-wider text-gray-500 block px-1">Price ($)</label>
        <input
          id="price"
          v-model="form.price"
          type="number"
          placeholder="0.00"
          class="w-full p-4 bg-gray-50 rounded-2xl focus:ring-2 focus:ring-purple-300 focus:outline-none"
        />
      </div>

      <div class="space-y-1">
        <label for="starts_at" class="text-xs font-semibold uppercase tracking-wider text-gray-500 block px-1">Starts At</label>
        <input
          id="starts_at"
          v-model="form.starts_at"
          type="datetime-local"
          class="w-full p-4 bg-gray-50 rounded-2xl focus:ring-2 focus:ring-purple-300 focus:outline-none"
        />
      </div>

      <div class="space-y-1">
        <label for="ends_at" class="text-xs font-semibold uppercase tracking-wider text-gray-500 block px-1">Ends At</label>
        <input
          id="ends_at"
          v-model="form.ends_at"
          type="datetime-local"
          class="w-full p-4 bg-gray-50 rounded-2xl focus:ring-2 focus:ring-purple-300 focus:outline-none"
        />
      </div>

      <div class="space-y-1">
        <label for="venue" class="text-xs font-semibold uppercase tracking-wider text-gray-500 block px-1">Location / Venue</label>
        <input
          id="venue"
          v-model="form.venue"
          placeholder="e.g. Grand Hall or Zoom Link"
          class="w-full p-4 bg-gray-50 rounded-2xl focus:ring-2 focus:ring-purple-300 focus:outline-none"
        />
      </div>

      <div class="space-y-1">
        <label for="capacity" class="text-xs font-semibold uppercase tracking-wider text-gray-500 block px-1">Maximum Capacity</label>
        <input
          id="capacity"
          v-model="form.capacity"
          type="number"
          min="1"
          placeholder="e.g. 100"
          class="w-full p-4 bg-gray-50 rounded-2xl focus:ring-2 focus:ring-purple-300 focus:outline-none"
        />
      </div>

      <div class="space-y-2">
        <label class="text-xs font-semibold uppercase tracking-wider text-gray-500 block px-1">Select Categories</label>
        <div class="flex flex-wrap gap-2">
          <button
            v-for="cat in categoriesList"
            :key="cat"
            type="button"
            @click="toggleCategory(cat)"
            :class="[
              'px-4 py-2 rounded-full border transition',
              form.categories.includes(cat)
                ? 'bg-purple-500 text-white border-purple-500'
                : 'bg-white text-gray-600 border-gray-300',
            ]"
          >
            {{ cat }}
          </button>
        </div>
      </div>

      <div class="space-y-1">
        <label for="document-upload" class="text-xs font-semibold uppercase tracking-wider text-gray-500 block px-1">Supporting Document</label>
        <div class="p-4 bg-gray-50 rounded-2xl flex justify-between items-center">
          <span class="text-gray-400 text-sm">Upload PDF or Doc</span>
          <input 
            id="document-upload" 
            type="file" 
            @change="(e) => handleFile(e, 'document')" 
            class="text-sm file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-purple-50 file:text-purple-700 hover:file:bg-purple-100"
          />
        </div>
      </div>
    </div>

    <button
      @click="handleSave"
      type="button"
      class="w-full py-5 text-base sm:text-lg font-bold text-white tracking-widest uppercase bg-gradient-to-r from-blue-600 to-purple-500 dark:from-blue-500 dark:to-purple-500 rounded-2xl shadow-md shadow-purple-500/20 hover:opacity-95 transition-opacity active:scale-[0.99] focus:outline-none"
    >
      {{ isEditMode ? "Update Event" : "Create Event" }}
    </button>
  </div>
</template>
