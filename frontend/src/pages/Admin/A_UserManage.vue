<script setup>
import { ref, computed, onMounted } from 'vue'
import { useAuthStore } from '@/stores/auth'

const authStore = useAuthStore()
const API_BASE = import.meta.env.VITE_API_BASE_URL

const users = ref([])
const loading = ref(true)
const roles = ['attendee', 'organiser', 'faculty_admin']
const roleLabels = { attendee: 'Attendee', organiser: 'Organiser', faculty_admin: 'Faculty Admin' }
const roleColors = {
  attendee:      { bg: '#eff6ff', text: '#1d4ed8', dot: '#3b82f6' },
  organiser:     { bg: '#f0fdf4', text: '#15803d', dot: '#22c55e' },
  faculty_admin: { bg: '#faf5ff', text: '#7c3aed', dot: '#a855f7' },
}

const search = ref('')
const selectedUser = ref(null)
const showAddModal = ref(false)
const showDeleteConfirm = ref(false)
const userToDelete = ref(null)
const editingRole = ref(false)
const tempRole = ref('')
const newUser = ref({ name: '', email: '', role: 'attendee' })
const newUserErrors = ref({})
const errorMsg = ref('')

const headers = () => ({
  'Content-Type': 'application/json',
  Authorization: `Bearer ${authStore.token}`,
  Accept: 'application/json',
})

// ── Fetch all users ──────────────────────────────────────────────
const fetchUsers = async () => {
  loading.value = true
  try {
    const res = await fetch(`${API_BASE}/api/admin/users`, { headers: headers() })
    const data = await res.json()
    users.value = data.map(u => ({ ...u, joined: u.created_at?.split('T')[0] ?? u.created_at?.split(' ')[0] ?? '' }))
  } catch (e) {
    errorMsg.value = 'Failed to load users.'
  } finally {
    loading.value = false
  }
}

onMounted(fetchUsers)

const filteredUsers = computed(() =>
  users.value.filter(u =>
    u.name.toLowerCase().includes(search.value.toLowerCase()) ||
    u.email.toLowerCase().includes(search.value.toLowerCase()) ||
    roleLabels[u.role].toLowerCase().includes(search.value.toLowerCase())
  )
)

const initials = (name) => name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2)
const selectUser = (user) => { selectedUser.value = { ...user }; editingRole.value = false; tempRole.value = user.role }
const startEditRole = () => { tempRole.value = selectedUser.value.role; editingRole.value = true }

// ── Update role ──────────────────────────────────────────────────
const saveRole = async () => {
  try {
    const res = await fetch(`${API_BASE}/api/admin/users/${selectedUser.value.id}/role`, {
      method: 'PUT',
      headers: headers(),
      body: JSON.stringify({ role: tempRole.value }),
    })
    if (!res.ok) throw new Error()
    const idx = users.value.findIndex(u => u.id === selectedUser.value.id)
    if (idx !== -1) { users.value[idx].role = tempRole.value; selectedUser.value = { ...users.value[idx] } }
    editingRole.value = false
  } catch {
    errorMsg.value = 'Failed to update role.'
  }
}

const cancelEditRole = () => { editingRole.value = false }

// ── Delete user ──────────────────────────────────────────────────
const confirmDelete = (user) => { userToDelete.value = user; showDeleteConfirm.value = true }
const deleteUser = async () => {
  try {
    const res = await fetch(`${API_BASE}/api/admin/users/${userToDelete.value.id}`, {
      method: 'DELETE',
      headers: headers(),
    })
    if (!res.ok) throw new Error()
    users.value = users.value.filter(u => u.id !== userToDelete.value.id)
    if (selectedUser.value?.id === userToDelete.value.id) selectedUser.value = null
    showDeleteConfirm.value = false
    userToDelete.value = null
  } catch {
    errorMsg.value = 'Failed to delete user.'
  }
}

// ── Add user ─────────────────────────────────────────────────────
const validateNewUser = () => {
  newUserErrors.value = {}
  if (!newUser.value.name.trim()) newUserErrors.value.name = 'Name is required'
  if (!newUser.value.email.trim()) newUserErrors.value.email = 'Email is required'
  else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(newUser.value.email)) newUserErrors.value.email = 'Invalid email'
  return Object.keys(newUserErrors.value).length === 0
}

const addUser = async () => {
  if (!validateNewUser()) return
  try {
    const res = await fetch(`${API_BASE}/api/admin/users`, {
      method: 'POST',
      headers: headers(),
      body: JSON.stringify(newUser.value),
    })
    const data = await res.json()
    if (!res.ok) {
      newUserErrors.value.email = data.error || 'Failed to create user'
      return
    }
    await fetchUsers()
    showAddModal.value = false
    newUser.value = { name: '', email: '', role: 'attendee' }
    newUserErrors.value = {}
  } catch {
    newUserErrors.value.email = 'Something went wrong.'
  }
}

const closeAddModal = () => {
  showAddModal.value = false
  newUser.value = { name: '', email: '', role: 'attendee' }
  newUserErrors.value = {}
}
</script>

<template>
  <div class="page">

    <!-- Header -->
    <div class="header">
      <div>
        <h1 class="header-title">User Management</h1>
        <p class="header-sub">{{ users.length }} registered users</p>
      </div>
      <button @click="showAddModal = true" class="btn-add">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
        </svg>
        Add User
      </button>
    </div>

    <!-- Search -->
    <div class="search-wrap">
      <svg class="search-icon w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11A6 6 0 1 0 5 11a6 6 0 0 0 12 0z" />
      </svg>
      <input v-model="search" type="text" placeholder="Search users..." class="search-input" />
    </div>

    <!-- Body -->
    <div class="body">

      <!-- List panel -->
<div class="list-panel">
  <div v-if="filteredUsers.length === 0" class="empty">No users found.</div>
  <div
    v-for="user in filteredUsers" :key="user.id"
    @click="selectUser(user)"
    :class="['user-row', selectedUser?.id === user.id && 'user-row--active']"
  >
    <div v-if="user.profile_picture"
      style="width:40px; height:40px; border-radius:9999px; overflow:hidden; flex-shrink:0;">
      <img :src="user.profile_picture" alt="Avatar" style="width:100%; height:100%; object-fit:cover;" />
    </div>
    <div v-else class="avatar" :style="{ background: `linear-gradient(135deg, ${roleColors[user.role].dot}, #3b82f6)` }">
      {{ initials(user.name) }}
    </div>

    <div class="user-text">
      <p class="uname">{{ user.name }}</p>
      <p class="uemail">{{ user.email }}</p>
    </div>
    <span class="badge" :style="{ background: roleColors[user.role].bg, color: roleColors[user.role].text }">
      {{ roleLabels[user.role] }}
    </span>
  </div>
</div>

      <!-- Detail panel -->
      <div class="detail-panel">
        <!-- Empty state -->
        <div v-if="!selectedUser" class="detail-empty">
          <div class="detail-empty-icon">
            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
            </svg>
          </div>
          <p class="detail-empty-text">Select a user to view details</p>
        </div>

        <!-- User detail -->
        <div v-else>
          <!-- Avatar + name -->
          <div class="detail-top">
            <!-- ✅ Show real avatar if available, else initials -->
            <div v-if="selectedUser.profile_picture" 
              style="width:64px; height:64px; border-radius:9999px; overflow:hidden; box-shadow: 0 4px 14px rgba(124,58,237,0.25);">
              <img :src="selectedUser.profile_picture" alt="Avatar" style="width:100%; height:100%; object-fit:cover;" />
            </div>
          <div v-else class="detail-avatar" :style="{ background: `linear-gradient(135deg, ${roleColors[selectedUser.role].dot}, #3b82f6)` }">
            {{ initials(selectedUser.name) }}
          </div>

          <p class="detail-name">{{ selectedUser.name }}</p>
          <p class="detail-email">{{ selectedUser.email }}</p>
          <span class="badge badge--lg" :style="{ background: roleColors[selectedUser.role].bg, color: roleColors[selectedUser.role].text }">
            <span class="badge-dot" :style="{ background: roleColors[selectedUser.role].dot }" />
            {{ roleLabels[selectedUser.role] }}
          </span>
        </div>

          <div class="divider" />

          <!-- Info -->
          <div class="info-rows">
            <div class="info-row"><span class="info-label">User ID</span><span class="info-val">#{{ selectedUser.id }}</span></div>
            <div class="info-row"><span class="info-label">Joined</span><span class="info-val">{{ selectedUser.joined }}</span></div>
            <div class="info-row">
              <span class="info-label">Profile Pic</span>
              <span class="info-val">
                <img v-if="selectedUser.profile_picture" :src="selectedUser.profile_picture" 
                  style="width:28px; height:28px; border-radius:9999px; object-fit:cover;" />
                <span v-else>Not set</span>
              </span>
            </div>
          </div>

          <div class="divider" />

          <!-- Role -->
          <div class="section">
            <p class="section-label">Role</p>
            <div v-if="!editingRole" class="role-view">
              <span class="badge" :style="{ background: roleColors[selectedUser.role].bg, color: roleColors[selectedUser.role].text }">{{ roleLabels[selectedUser.role] }}</span>
              <button @click="startEditRole" class="btn-outline">Change</button>
            </div>
            <div v-else class="role-edit">
              <select v-model="tempRole" class="role-select">
                <option v-for="r in roles" :key="r" :value="r">{{ roleLabels[r] }}</option>
              </select>
              <div class="role-btns">
                <button @click="cancelEditRole" class="btn-sm-ghost">Cancel</button>
                <button @click="saveRole" class="btn-sm-primary">Save</button>
              </div>
            </div>
          </div>

          <div class="divider" />

          <!-- Delete -->
          <div class="section">
            <p class="section-label">Danger Zone</p>
            <button @click="confirmDelete(selectedUser)" class="btn-danger">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
              </svg>
              Delete User
            </button>
          </div>
        </div>
      </div>

    </div>

    <!-- Add User Modal -->
    <transition name="fade">
      <div v-if="showAddModal" class="modal-wrap" @click.self="closeAddModal">
        <div class="modal-bg" @click="closeAddModal" />
        <div class="modal">
          <div class="modal-head">
            <div><h3 class="modal-title">Add New User</h3><p class="modal-sub">Fill in the details below</p></div>
            <button @click="closeAddModal" class="close-btn">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
          </div>
          <div class="modal-body">
            <div class="field"><label>Full Name</label>
              <input v-model="newUser.name" type="text" placeholder="Enter full name" :class="['field-input', newUserErrors.name && 'err']" />
              <p v-if="newUserErrors.name" class="err-msg">{{ newUserErrors.name }}</p>
            </div>
            <div class="field"><label>Email Address</label>
              <input v-model="newUser.email" type="email" placeholder="user@utm.my" :class="['field-input', newUserErrors.email && 'err']" />
              <p v-if="newUserErrors.email" class="err-msg">{{ newUserErrors.email }}</p>
            </div>
            <div class="field"><label>Role</label>
              <select v-model="newUser.role" class="field-input">
                <option v-for="r in roles" :key="r" :value="r">{{ roleLabels[r] }}</option>
              </select>
            </div>
          </div>
          <div class="modal-foot">
            <button @click="closeAddModal" class="btn-ghost">Cancel</button>
            <button @click="addUser" class="btn-primary">Add User</button>
          </div>
        </div>
      </div>
    </transition>

    <!-- Delete Confirm Modal -->
    <transition name="fade">
      <div v-if="showDeleteConfirm" class="modal-wrap" @click.self="showDeleteConfirm = false">
        <div class="modal-bg" @click="showDeleteConfirm = false" />
        <div class="modal modal--sm">
          <div class="del-icon"><svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg></div>
          <h3 class="modal-title" style="text-align:center">Delete User?</h3>
          <p class="modal-sub" style="text-align:center;margin-bottom:20px"><strong>{{ userToDelete?.name }}</strong> will be permanently removed.</p>
          <div class="modal-foot">
            <button @click="showDeleteConfirm = false" class="btn-ghost">Cancel</button>
            <button @click="deleteUser" class="btn-danger-solid">Delete</button>
          </div>
        </div>
      </div>
    </transition>

  </div>
</template>

<style scoped>
/* ── Page ── */
.page {
  min-height: 100vh;
  background: linear-gradient(135deg, #ede9fe 0%, #dbeafe 100%);
  padding: 28px 28px;
  box-sizing: border-box;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

/* ── Header ── */
.header { display: flex; align-items: center; justify-content: space-between; }
.header-title { font-size: 20px; font-weight: 700; color: #111827; margin: 0; }
.header-sub { font-size: 12px; color: #6b7280; margin: 3px 0 0; }

.btn-add {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 9px 18px; font-size: 13px; font-weight: 600; color: #fff;
  background: linear-gradient(90deg, #7c3aed, #3b82f6);
  border: none; border-radius: 10px; cursor: pointer;
  box-shadow: 0 4px 14px rgba(124,58,237,0.25);
  transition: opacity 0.2s, transform 0.15s;
}
.btn-add:hover { opacity: 0.9; transform: translateY(-1px); }

/* ── Search ── */
.search-wrap { position: relative; }
.search-icon { position: absolute; left: 13px; top: 50%; transform: translateY(-50%); color: #9ca3af; }
.search-input {
  width: 100%; padding: 10px 14px 10px 38px; font-size: 13px;
  border: 1.5px solid #e5e7eb; border-radius: 10px;
  background: #fff; color: #111827; outline: none;
  transition: border-color 0.2s, box-shadow 0.2s; box-sizing: border-box;
}
.search-input:focus { border-color: #7c3aed; box-shadow: 0 0 0 3px rgba(124,58,237,0.10); }
.search-input::placeholder { color: #9ca3af; }

/* ── Body ── */
.body { display: flex; gap: 16px; align-items: flex-start; flex: 1; }

/* ── List Panel ── */
.list-panel {
  flex: 0 0 60%; background: #fff; border-radius: 14px;
  box-shadow: 0 2px 16px rgba(124,58,237,0.07); overflow: hidden; min-width: 0;
}
.empty { padding: 40px; text-align: center; color: #9ca3af; font-size: 13px; }

.user-row {
  display: flex; align-items: center; gap: 12px;
  padding: 14px 18px; cursor: pointer;
  border-bottom: 1px solid #f3f4f6;
  transition: background 0.15s;
}
.user-row:last-child { border-bottom: none; }
.user-row:hover { background: #faf5ff; }
.user-row--active { background: #f5f3ff; border-left: 3px solid #7c3aed; padding-left: 15px; }

.avatar {
  width: 40px; height: 40px; border-radius: 9999px; flex-shrink: 0;
  display: flex; align-items: center; justify-content: center;
  font-size: 14px; font-weight: 700; color: #fff;
}
.user-text { flex: 1; min-width: 0; text-align: left; }
.uname { font-size: 14px; font-weight: 600; color: #111827; margin: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.uemail { font-size: 12px; color: #6b7280; margin: 2px 0 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

.badge {
  font-size: 11px; font-weight: 600; padding: 3px 10px;
  border-radius: 9999px; white-space: nowrap; flex-shrink: 0;
  display: inline-flex; align-items: center; gap: 5px;
}
.badge--lg { font-size: 12px; padding: 4px 12px; }
.badge-dot { width: 6px; height: 6px; border-radius: 9999px; flex-shrink: 0; }

/* ── Detail Panel ── */
.detail-panel {
  flex: 0 0 calc(40% - 14px); background: #fff; border-radius: 14px;
  box-shadow: 0 2px 16px rgba(124,58,237,0.07); overflow: hidden;
}

.detail-empty {
  display: flex; flex-direction: column; align-items: center;
  justify-content: center; gap: 10px; padding: 48px 20px;
  color: #d1d5db; text-align: center;
}
.detail-empty-icon {
  width: 52px; height: 52px; border-radius: 9999px; background: #f3f4f6;
  display: flex; align-items: center; justify-content: center; color: #d1d5db;
}
.detail-empty-text { font-size: 13px; color: #9ca3af; }

.detail-top { display: flex; flex-direction: column; align-items: center; gap: 6px; padding: 24px 20px 16px; text-align: center; }
.detail-avatar {
  width: 64px; height: 64px; border-radius: 9999px;
  display: flex; align-items: center; justify-content: center;
  font-size: 22px; font-weight: 700; color: #fff;
  box-shadow: 0 4px 14px rgba(124,58,237,0.25);
}
.detail-name { font-size: 15px; font-weight: 700; color: #111827; margin: 0; }
.detail-email { font-size: 11px; color: #6b7280; margin: 0; }

.divider { height: 1px; background: #f3f4f6; margin: 0 20px; }

.info-rows { display: flex; flex-direction: column; gap: 10px; padding: 14px 20px; }
.info-row { display: flex; justify-content: space-between; align-items: center; }
.info-label { font-size: 11px; color: #9ca3af; font-weight: 500; }
.info-val { font-size: 12px; color: #374151; font-weight: 500; }

.section { padding: 14px 20px; }
.section-label { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.07em; color: #9ca3af; margin: 0 0 10px; }

.role-view { display: flex; align-items: center; justify-content: space-between; }
.role-edit { display: flex; flex-direction: column; gap: 8px; }
.role-select {
  width: 100%; padding: 8px 10px; font-size: 13px;
  border: 1.5px solid #e5e7eb; border-radius: 8px;
  background: #f9fafb; color: #111827; outline: none;
}
.role-select:focus { border-color: #7c3aed; }
.role-btns { display: flex; gap: 6px; }

/* ── Buttons ── */
.btn-outline {
  padding: 5px 12px; font-size: 11px; font-weight: 600;
  color: #7c3aed; background: #f5f3ff; border: 1.5px solid #ddd6fe;
  border-radius: 7px; cursor: pointer; transition: background 0.2s;
}
.btn-outline:hover { background: #ede9fe; }

.btn-sm-ghost {
  flex: 1; padding: 7px; font-size: 12px; font-weight: 600;
  color: #6b7280; background: #f3f4f6; border: none; border-radius: 7px; cursor: pointer;
}
.btn-sm-primary {
  flex: 1; padding: 7px; font-size: 12px; font-weight: 600;
  color: #fff; background: linear-gradient(90deg, #7c3aed, #3b82f6);
  border: none; border-radius: 7px; cursor: pointer;
}

.btn-danger {
  width: 100%; display: flex; align-items: center; justify-content: center; gap: 6px;
  padding: 9px; font-size: 12px; font-weight: 600; color: #ef4444;
  background: #fef2f2; border: 1.5px solid #fecaca; border-radius: 9px; cursor: pointer;
  transition: background 0.2s;
}
.btn-danger:hover { background: #fee2e2; }

.btn-danger-solid {
  flex: 1; padding: 10px 18px; font-size: 13px; font-weight: 600;
  color: #fff; background: #ef4444; border: none; border-radius: 9px; cursor: pointer;
}
.btn-danger-solid:hover { opacity: 0.9; }

.btn-primary {
  padding: 10px 18px; font-size: 13px; font-weight: 600; color: #fff;
  background: linear-gradient(90deg, #7c3aed, #3b82f6);
  border: none; border-radius: 9px; cursor: pointer;
  box-shadow: 0 4px 14px rgba(124,58,237,0.20); transition: opacity 0.2s;
}
.btn-primary:hover { opacity: 0.92; }

.btn-ghost {
  padding: 10px 18px; font-size: 13px; font-weight: 600;
  color: #6b7280; background: #f3f4f6; border: none; border-radius: 9px; cursor: pointer;
}
.btn-ghost:hover { background: #e5e7eb; }

/* ── Modal ── */
.modal-wrap {
  position: fixed; inset: 0; z-index: 50;
  display: flex; align-items: center; justify-content: center; padding: 16px;
}
.modal-bg { position: absolute; inset: 0; background: rgba(0,0,0,0.30); backdrop-filter: blur(4px); }
.modal {
  position: relative; z-index: 10; width: 100%; max-width: 400px;
  background: #fff; border-radius: 18px; padding: 26px;
  box-shadow: 0 20px 60px rgba(0,0,0,0.12);
}
.modal--sm { max-width: 320px; }
.modal-head { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; }
.modal-title { font-size: 15px; font-weight: 700; color: #111827; margin: 0 0 3px; }
.modal-sub { font-size: 12px; color: #6b7280; margin: 0; }
.close-btn { padding: 6px; border-radius: 7px; background: none; border: none; cursor: pointer; color: #9ca3af; }
.close-btn:hover { background: #f3f4f6; color: #374151; }

.modal-body { display: flex; flex-direction: column; gap: 14px; margin-bottom: 20px; }
.field { display: flex; flex-direction: column; gap: 5px; }
.field label { font-size: 12px; font-weight: 600; color: #374151; }
.field-input {
  width: 100%; padding: 9px 12px; font-size: 13px;
  border: 1.5px solid #e5e7eb; border-radius: 9px;
  background: #f9fafb; color: #111827; outline: none;
  transition: border-color 0.2s, box-shadow 0.2s; box-sizing: border-box;
}
.field-input:focus { border-color: #7c3aed; background: #fff; box-shadow: 0 0 0 3px rgba(124,58,237,0.10); }
.field-input::placeholder { color: #9ca3af; }
.err { border-color: #ef4444 !important; }
.err-msg { font-size: 11px; color: #ef4444; }

.modal-foot { display: flex; gap: 8px; justify-content: flex-end; }

.del-icon {
  width: 52px; height: 52px; border-radius: 9999px; background: #ef4444;
  display: flex; align-items: center; justify-content: center; color: #fff;
  margin: 0 auto 14px; box-shadow: 0 6px 20px rgba(239,68,68,0.30);
}

/* Transition */
.fade-enter-active, .fade-leave-active { transition: opacity 0.25s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>