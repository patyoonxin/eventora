<script setup>
import { ref, computed, onMounted } from "vue";
import { useAuthStore } from "@/stores/auth";

const authStore = useAuthStore();
const API_BASE = import.meta.env.VITE_API_BASE_URL;

const societies = ref([]);
const availableOrganisers = ref([]);
const assignedOrganisers = ref([]);
const loading = ref(true);
const search = ref("");
const showAddModal = ref(false);
const showOrganisersModal = ref(false);
const selectedSociety = ref(null);
const newSociety = ref({ name: "", faculty: "", advisor_id: "" });
const newSocietyErrors = ref({});
const selectedOrganizerId = ref("");
const organiserError = ref("");
const organiserSuccess = ref("");
const organiserLoading = ref(false);
const errorMsg = ref("");

const headers = () => ({
  "Content-Type": "application/json",
  Authorization: `Bearer ${authStore.token}`,
  Accept: "application/json",
});

const fetchSocieties = async () => {
  loading.value = true;
  errorMsg.value = "";
  try {
    const res = await fetch(`${API_BASE}/api/admin/societies`, {
      headers: headers(),
    });
    const data = await res.json();
    societies.value = data.map((society) => ({
      ...society,
      created: society.created_at?.split("T")[0] ?? society.created_at?.split(" ")[0] ?? "",
    }));
  } catch {
    errorMsg.value = "Failed to load societies.";
  } finally {
    loading.value = false;
  }
};

const fetchOrganisers = async () => {
  try {
    const res = await fetch(`${API_BASE}/api/admin/organisers`, {
      headers: headers(),
    });
    const data = await res.json();
    availableOrganisers.value = Array.isArray(data) ? data : [];
  } catch {
    availableOrganisers.value = [];
  }
};

onMounted(() => {
  fetchSocieties();
  fetchOrganisers();
});

const filteredSocieties = computed(() =>
  societies.value.filter((society) => {
    const query = search.value.toLowerCase();
    return (
      society.name?.toLowerCase().includes(query) ||
      society.faculty?.toLowerCase().includes(query) ||
      String(society.advisor_id ?? "").includes(query)
    );
  }),
);

const validateNewSociety = () => {
  newSocietyErrors.value = {};
  if (!newSociety.value.name.trim()) newSocietyErrors.value.name = "Society name is required";
  if (!newSociety.value.faculty.trim()) newSocietyErrors.value.faculty = "Faculty is required";
  if (newSociety.value.advisor_id !== "" && Number.isNaN(Number(newSociety.value.advisor_id))) {
    newSocietyErrors.value.advisor_id = "Advisor ID must be a number";
  }
  return Object.keys(newSocietyErrors.value).length === 0;
};

const addSociety = async () => {
  if (!validateNewSociety()) return;

  try {
    const payload = {
      ...newSociety.value,
      advisor_id: newSociety.value.advisor_id === "" ? null : Number(newSociety.value.advisor_id),
    };

    const res = await fetch(`${API_BASE}/api/admin/societies`, {
      method: "POST",
      headers: headers(),
      body: JSON.stringify(payload),
    });
    const data = await res.json();

    if (!res.ok) {
      newSocietyErrors.value.form = data.error || "Failed to create society";
      return;
    }

    await fetchSocieties();
    showAddModal.value = false;
    newSociety.value = { name: "", faculty: "", advisor_id: "" };
    newSocietyErrors.value = {};
    errorMsg.value = "Society created successfully.";
  } catch {
    newSocietyErrors.value.form = "Something went wrong.";
  }
};

const closeAddModal = () => {
  showAddModal.value = false;
  newSociety.value = { name: "", faculty: "", advisor_id: "" };
  newSocietyErrors.value = {};
};

const openOrganisersModal = async (society) => {
  selectedSociety.value = society;
  showOrganisersModal.value = true;
  selectedOrganizerId.value = "";
  organiserError.value = "";
  organiserSuccess.value = "";
  organiserLoading.value = true;

  try {
    const res = await fetch(`${API_BASE}/api/admin/societies/${society.id}/organisers`, {
      headers: headers(),
    });
    const data = await res.json();
    assignedOrganisers.value = Array.isArray(data) ? data : [];
  } catch {
    organiserError.value = "Failed to load organisers.";
    assignedOrganisers.value = [];
  } finally {
    organiserLoading.value = false;
  }
};

const closeOrganisersModal = () => {
  showOrganisersModal.value = false;
  selectedSociety.value = null;
  assignedOrganisers.value = [];
  selectedOrganizerId.value = "";
  organiserError.value = "";
  organiserSuccess.value = "";
};

const assignOrganizer = async () => {
  if (!selectedSociety.value) return;
  if (!selectedOrganizerId.value) {
    organiserError.value = "Please choose an organiser.";
    return;
  }

  organiserLoading.value = true;
  organiserError.value = "";
  organiserSuccess.value = "";

  try {
    const res = await fetch(`${API_BASE}/api/admin/societies/${selectedSociety.value.id}/organisers`, {
      method: "POST",
      headers: headers(),
      body: JSON.stringify({ user_id: Number(selectedOrganizerId.value) }),
    });
    const data = await res.json();

    if (!res.ok) {
      organiserError.value = data.error || "Failed to assign organiser.";
      return;
    }

    assignedOrganisers.value = [...assignedOrganisers.value, data.organiser];
    selectedOrganizerId.value = "";
    organiserSuccess.value = "Organiser added successfully.";
  } catch {
    organiserError.value = "Something went wrong.";
  } finally {
    organiserLoading.value = false;
  }
};

const removeOrganizer = async (organiserId) => {
  if (!selectedSociety.value) return;

  organiserLoading.value = true;
  organiserError.value = "";
  organiserSuccess.value = "";

  try {
    const res = await fetch(`${API_BASE}/api/admin/societies/${selectedSociety.value.id}/organisers/${organiserId}`, {
      method: "DELETE",
      headers: headers(),
    });
    const data = await res.json();

    if (!res.ok) {
      organiserError.value = data.error || "Failed to remove organiser.";
      return;
    }

    assignedOrganisers.value = assignedOrganisers.value.filter((organiser) => organiser.id !== organiserId);
    organiserSuccess.value = "Organiser removed successfully.";
  } catch {
    organiserError.value = "Something went wrong.";
  } finally {
    organiserLoading.value = false;
  }
};
</script>

<template>
  <div class="min-h-screen px-4 sm:px-6 lg:px-8 py-8 bg-[linear-gradient(135deg,#ede9fe_0%,#dbeafe_100%)]">
    <div class="max-w-7xl mx-auto">
      <div class="mb-6 text-left flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between pl-2">
        <div>
          <h2 class="text-3xl font-extrabold text-gray-900">Society Management</h2>
          <p class="header-sub">{{ societies.length }} registered societies</p>
        </div>
        <button @click="showAddModal = true" class="btn-add">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
          </svg>
          Add Society
        </button>
      </div>

      <div class="search-wrap">
        <svg class="search-icon w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11A6 6 0 1 0 5 11a6 6 0 0 0 12 0z" />
        </svg>
        <input v-model="search" type="text" placeholder="Search societies..." class="search-input" />
      </div>

      <div v-if="errorMsg" class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
        {{ errorMsg }}
      </div>

      <div class="list-panel">
        <div v-if="loading" class="empty">Loading...</div>
        <div v-else-if="filteredSocieties.length === 0" class="empty">No societies found.</div>
        <div v-else>
          <button v-for="society in filteredSocieties" :key="society.id" type="button" class="society-row society-row--interactive" @click="openOrganisersModal(society)">
            <div class="society-text">
              <p class="sname">{{ society.name }}</p>
              <p class="sfaculty">{{ society.faculty }}</p>
            </div>
            <div class="society-meta">
              <span class="chip chip--purple">Advisor ID: {{ society.advisor_id ?? '—' }}</span>
              <span class="chip">Created {{ society.created }}</span>
            </div>
          </button>
        </div>
      </div>
    </div>

    <transition name="fade">
      <div v-if="showAddModal" class="modal-wrap" @click.self="closeAddModal">
        <div class="modal-bg" @click="closeAddModal" />
        <div class="modal">
          <div class="modal-head">
            <div>
              <h3 class="modal-title">Add New Society</h3>
              <p class="modal-sub">Fill in the details below</p>
            </div>
            <button @click="closeAddModal" class="close-btn">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          <div class="modal-body">
            <div class="field">
              <label>Society Name</label>
              <input v-model="newSociety.name" type="text" placeholder="Enter society name" :class="['field-input', newSocietyErrors.name && 'err']" />
              <p v-if="newSocietyErrors.name" class="err-msg">{{ newSocietyErrors.name }}</p>
            </div>
            <div class="field">
              <label>Faculty</label>
              <input v-model="newSociety.faculty" type="text" placeholder="Enter faculty" :class="['field-input', newSocietyErrors.faculty && 'err']" />
              <p v-if="newSocietyErrors.faculty" class="err-msg">{{ newSocietyErrors.faculty }}</p>
            </div>
            <div class="field">
              <label>Advisor User ID (Optional)</label>
              <input v-model="newSociety.advisor_id" type="number" min="1" placeholder="Enter advisor user ID" :class="['field-input', newSocietyErrors.advisor_id && 'err']" />
              <p v-if="newSocietyErrors.advisor_id" class="err-msg">{{ newSocietyErrors.advisor_id }}</p>
            </div>
            <p v-if="newSocietyErrors.form" class="err-msg">{{ newSocietyErrors.form }}</p>
          </div>

          <div class="modal-foot">
            <button @click="closeAddModal" class="btn-ghost">Cancel</button>
            <button @click="addSociety" class="btn-primary">Add Society</button>
          </div>
        </div>
      </div>
    </transition>

    <transition name="fade">
      <div v-if="showOrganisersModal" class="modal-wrap" @click.self="closeOrganisersModal">
        <div class="modal-bg" @click="closeOrganisersModal" />
        <div class="modal modal--wide">
          <div class="modal-head">
            <div class="text-left">
              <h3 class="modal-title">Manage Organisers</h3>
              <p class="modal-sub">Assign organiser users to {{ selectedSociety?.name || 'this society' }}</p>
            </div>
            <button @click="closeOrganisersModal" class="close-btn">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          <div class="modal-body">
            <div class="field">
              <label>Select organiser</label>
              <select v-model="selectedOrganizerId" class="field-input field-select">
                <option value="">Choose an organiser</option>
                <option v-for="organiser in availableOrganisers" :key="organiser.id" :value="organiser.id">
                  {{ organiser.name }} — {{ organiser.email }}
                </option>
              </select>
            </div>

            <button @click="assignOrganizer" class="btn-primary btn-full">Add organiser</button>
            <p v-if="organiserError" class="err-msg">{{ organiserError }}</p>
            <p v-if="organiserSuccess" class="success-msg">{{ organiserSuccess }}</p>

            <div class="assigned-list">
              <div class="assigned-title">Assigned organisers</div>
              <div v-if="organiserLoading" class="empty assigned-empty">Loading...</div>
              <div v-else-if="assignedOrganisers.length === 0" class="empty assigned-empty">No organisers assigned yet.</div>
              <div v-else>
                <div v-for="organiser in assignedOrganisers" :key="organiser.id" class="assigned-item">
                  <div class="assigned-item-content">
                    <div>
                      <p class="assigned-name">{{ organiser.name }}</p>
                      <p class="assigned-email">{{ organiser.email }}</p>
                    </div>
                    <button type="button" class="remove-btn" @click="removeOrganizer(organiser.id)">Remove</button>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="modal-foot">
            <button @click="closeOrganisersModal" class="btn-ghost">Close</button>
          </div>
        </div>
      </div>
    </transition>
  </div>
</template>

<style scoped>
.header-sub {
  font-size: 12px;
  color: #6b7280;
  margin: 3px 0 0;
}

.btn-add {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 9px 18px;
  font-size: 13px;
  font-weight: 600;
  color: #fff;
  background: linear-gradient(90deg, #7c3aed, #3b82f6);
  border: none;
  border-radius: 10px;
  cursor: pointer;
  box-shadow: 0 4px 14px rgba(124, 58, 237, 0.25);
  transition: opacity 0.2s, transform 0.15s;
}
.btn-add:hover {
  opacity: 0.9;
  transform: translateY(-1px);
}

.search-wrap {
  position: relative;
  margin-bottom: 16px;
}
.search-icon {
  position: absolute;
  left: 13px;
  top: 50%;
  transform: translateY(-50%);
  color: #9ca3af;
}
.search-input {
  width: 100%;
  padding: 10px 14px 10px 38px;
  font-size: 13px;
  border: 1.5px solid #e5e7eb;
  border-radius: 10px;
  background: #fff;
  color: #111827;
  outline: none;
  transition: border-color 0.2s, box-shadow 0.2s;
  box-sizing: border-box;
}
.search-input:focus {
  border-color: #7c3aed;
  box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.1);
}
.search-input::placeholder {
  color: #9ca3af;
}

.list-panel {
  background: #fff;
  border-radius: 14px;
  box-shadow: 0 2px 16px rgba(124, 58, 237, 0.07);
  overflow: hidden;
}
.empty {
  padding: 40px;
  text-align: center;
  color: #9ca3af;
  font-size: 13px;
}

.society-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 14px 18px;
  border-bottom: 1px solid #f3f4f6;
  width: 100%;
  text-align: left;
  background: transparent;
  border-left: none;
  border-right: none;
  border-top: none;
}
.society-row:last-child {
  border-bottom: none;
}
.society-row--interactive {
  cursor: pointer;
}
.society-row--interactive:hover {
  background: #faf5ff;
}
.society-text {
  flex: 1;
  min-width: 0;
  text-align: left;
}
.sname {
  font-size: 14px;
  font-weight: 600;
  color: #111827;
  margin: 0;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.sfaculty {
  font-size: 12px;
  color: #6b7280;
  margin: 2px 0 0;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.society-meta {
  display: flex;
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: 8px;
}
.chip {
  font-size: 11px;
  font-weight: 600;
  padding: 3px 10px;
  border-radius: 9999px;
  white-space: nowrap;
  color: #6b7280;
  background: #f3f4f6;
}
.chip--purple {
  color: #7c3aed;
  background: #f5f3ff;
}
.chip--blue {
  color: #2563eb;
  background: #eff6ff;
}

.modal-wrap {
  position: fixed;
  inset: 0;
  z-index: 60;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 16px;
}
.modal-bg {
  position: absolute;
  inset: 0;
  background: rgba(0, 0, 0, 0.3);
  backdrop-filter: blur(4px);
}
.modal {
  position: relative;
  z-index: 10;
  width: 100%;
  max-width: 400px;
  background: #fff;
  border-radius: 18px;
  padding: 26px;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.12);
}
.modal--wide {
  max-width: 460px;
}
.modal-head {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 20px;
}
.modal-title {
  font-size: 15px;
  font-weight: 700;
  color: #111827;
  margin: 0 0 3px;
}
.modal-sub {
  font-size: 12px;
  color: #6b7280;
  margin: 0;
}
.close-btn {
  padding: 6px;
  border-radius: 7px;
  background: none;
  border: none;
  cursor: pointer;
  color: #9ca3af;
}
.modal-body {
  display: flex;
  flex-direction: column;
  gap: 14px;
  margin-bottom: 20px;
}
.field {
  display: flex;
  flex-direction: column;
  gap: 5px;
}
.field label {
  font-size: 12px;
  font-weight: 600;
  color: #374151;
}
.field-input {
  width: 100%;
  padding: 9px 12px;
  font-size: 13px;
  border: 1.5px solid #e5e7eb;
  border-radius: 9px;
  background: #f9fafb;
  color: #111827;
  outline: none;
  transition: border-color 0.2s;
  box-sizing: border-box;
}
.field-input:focus {
  border-color: #7c3aed;
  box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.1);
}
.field-input::placeholder {
  color: #9ca3af;
}
.field-select {
  appearance: none;
}
.err {
  border-color: #ef4444 !important;
}
.err-msg {
  font-size: 11px;
  color: #ef4444;
}
.modal-foot {
  display: flex;
  gap: 8px;
  justify-content: flex-end;
}
.btn-full {
  width: 100%;
  justify-content: center;
}
.btn-primary {
  padding: 10px 18px;
  font-size: 13px;
  font-weight: 600;
  color: #fff;
  background: linear-gradient(90deg, #7c3aed, #3b82f6);
  border: none;
  border-radius: 9px;
  cursor: pointer;
}
.btn-ghost {
  padding: 10px 18px;
  font-size: 13px;
  font-weight: 600;
  color: #6b7280;
  background: #f3f4f6;
  border: none;
  border-radius: 9px;
  cursor: pointer;
}
.success-msg {
  font-size: 12px;
  color: #15803d;
}
.assigned-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin-top: 10px;
}
.assigned-title {
  font-size: 12px;
  font-weight: 700;
  color: #374151;
}
.assigned-item {
  padding: 10px 12px;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  background: #f9fafb;
}
.assigned-item-content {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
}
.remove-btn {
  padding: 6px 10px;
  font-size: 12px;
  font-weight: 600;
  color: #dc2626;
  background: #fef2f2;
  border: 1px solid #fecaca;
  border-radius: 8px;
  cursor: pointer;
}
.assigned-name {
  font-size: 13px;
  font-weight: 600;
  color: #111827;
  margin: 0;
}
.assigned-email {
  font-size: 12px;
  color: #6b7280;
  margin: 3px 0 0;
}
.assigned-empty {
  padding: 20px;
}
</style>
