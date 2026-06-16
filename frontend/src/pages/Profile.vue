<script setup>
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'

const router = useRouter()

const isEditing = ref(false)
const isSaving = ref(false)
const saveSuccess = ref(false)
const errors = ref({})

const avatarInput = ref(null)
const avatarPreview = ref(null)

const form = ref({
  fullName: 'Vishal Khadok',
  email: 'hello@halallab.co',
  phone: '408-841-0926',
})
const original = ref({ ...form.value })

const isValidEmail = (val) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val)
const isValidPhone = (val) => /^[\d\s\-+()]{7,15}$/.test(val)

const initials = computed(() =>
  form.value.fullName.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2)
)

const validate = () => {
  errors.value = {}
  if (!form.value.fullName.trim()) errors.value.fullName = 'Full name is required'
  if (!form.value.email.trim()) errors.value.email = 'Email is required'
  else if (!isValidEmail(form.value.email)) errors.value.email = 'Please enter a valid email'
  if (form.value.phone && !isValidPhone(form.value.phone)) errors.value.phone = 'Please enter a valid phone number'
  return Object.keys(errors.value).length === 0
}

const handleSave = async () => {
  if (!validate()) return
  isSaving.value = true
  await new Promise(r => setTimeout(r, 1500))
  isSaving.value = false
  saveSuccess.value = true
  original.value = { ...form.value }
  isEditing.value = false
  setTimeout(() => saveSuccess.value = false, 3000)
}

const handleCancel = () => {
  form.value = { ...original.value }
  errors.value = {}
  isEditing.value = false
}

const triggerAvatarUpload = () => { if (isEditing.value) avatarInput.value?.click() }
const handleAvatarChange = (e) => {
  const file = e.target.files[0]
  if (!file) return
  const reader = new FileReader()
  reader.onload = (ev) => avatarPreview.value = ev.target.result
  reader.readAsDataURL(file)
}

const showPasswordModal = ref(false)
const pwForm = ref({ current: '', newPw: '', confirm: '' })
const pwErrors = ref({})
const pwSaving = ref(false)
const pwSuccess = ref(false)
const showCurrent = ref(false)
const showNew = ref(false)
const showConfirm = ref(false)

const openPasswordModal = () => {
  pwForm.value = { current: '', newPw: '', confirm: '' }
  pwErrors.value = {}
  pwSuccess.value = false
  showPasswordModal.value = true
}

const closePasswordModal = () => { showPasswordModal.value = false }

const validatePw = () => {
  pwErrors.value = {}
  if (!pwForm.value.current) pwErrors.value.current = 'Current password is required'
  if (!pwForm.value.newPw) pwErrors.value.newPw = 'New password is required'
  else if (pwForm.value.newPw.length < 8) pwErrors.value.newPw = 'Must be at least 8 characters'
  if (!pwForm.value.confirm) pwErrors.value.confirm = 'Please confirm your new password'
  else if (pwForm.value.newPw !== pwForm.value.confirm) pwErrors.value.confirm = 'Passwords do not match'
  return Object.keys(pwErrors.value).length === 0
}

const handleChangePassword = async () => {
  if (!validatePw()) return
  pwSaving.value = true
  await new Promise(r => setTimeout(r, 1500))
  pwSaving.value = false
  pwSuccess.value = true
  setTimeout(() => { pwSuccess.value = false; closePasswordModal() }, 2000)
}

const pwStrength = computed(() => {
  const pw = pwForm.value.newPw
  if (!pw) return 0
  let score = 0
  if (pw.length >= 8) score++
  if (/[A-Z]/.test(pw)) score++
  if (/[0-9]/.test(pw)) score++
  if (/[^A-Za-z0-9]/.test(pw)) score++
  return score
})

const pwStrengthLabel = computed(() => ['', 'Weak', 'Fair', 'Good', 'Strong'][pwStrength.value])
const pwStrengthColors = ['', '#f87171', '#fb923c', '#facc15', '#22c55e']
</script>

<template>
  <div class="page-bg">
    <!-- Decorative Orbs -->
    <div class="orb orb-top" />
    <div class="orb orb-bottom" />

    <!-- Profile Card -->
    <div class="card-wrap" style="animation: slideUp 0.6s ease-out">
      <div class="card">

        <!-- Header -->
        <div class="card-header">
          <button @click="router.back()" class="back-btn">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
          </button>
          <img src="@/assets/logo.png" alt="EventOra Logo" class="header-logo" />
          <div class="w-9" />
        </div>

        <!-- Avatar -->
        <div class="avatar-wrap" style="animation: logoFloat 3s ease-in-out infinite">
          <button @click="triggerAvatarUpload" class="avatar-btn" :class="{ editable: isEditing }">
            <img v-if="avatarPreview" :src="avatarPreview" alt="Avatar" class="avatar-img" />
            <div v-else class="avatar-initials">{{ initials }}</div>
          </button>
          <button v-if="isEditing" @click="triggerAvatarUpload" class="avatar-edit-btn">
            <svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15.232 5.232l3.536 3.536M9 13l6.586-6.586a2 2 0 012.828 2.828L11.828 15.828a2 2 0 01-1.414.586H9v-2a2 2 0 01.586-1.414z" />
            </svg>
          </button>
          <input ref="avatarInput" type="file" accept="image/*" class="hidden" @change="handleAvatarChange" />
        </div>

        <!-- Name display -->
        <div v-if="!isEditing" class="name-display">
          <h2 class="name-text">{{ form.fullName }}</h2>
          <p class="email-text">{{ form.email }}</p>
        </div>

        <!-- Success toast -->
        <transition name="fade">
          <div v-if="saveSuccess" class="success-toast">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
            Profile updated successfully!
          </div>
        </transition>

        <!-- Fields -->
        <div class="fields">
          <div v-for="field in [
            { key: 'fullName', label: 'Full Name', type: 'text', placeholder: 'Your full name' },
            { key: 'email', label: 'Email', type: 'email', placeholder: 'you@example.com' },
            { key: 'phone', label: 'Phone Number', type: 'tel', placeholder: 'Your phone number' },
          ]" :key="field.key" class="field">
            <label>{{ field.label }}</label>
            <input
              v-model="form[field.key]"
              :type="field.type"
              :placeholder="field.placeholder"
              :disabled="!isEditing"
              :class="['field-input', !isEditing && 'field-input--disabled', errors[field.key] && 'field-input--error']"
            />
            <p v-if="errors[field.key]" class="error-msg">{{ errors[field.key] }}</p>
          </div>
        </div>

        <!-- Actions -->
        <div class="actions">
          <template v-if="isEditing">
            <div class="btn-row">
              <button @click="handleCancel" class="btn-cancel">Cancel</button>
              <button @click="handleSave" :disabled="isSaving" class="btn-primary">
                {{ isSaving ? 'Saving...' : 'Save' }}
              </button>
            </div>
          </template>
          <template v-else>
            <button @click="isEditing = true" class="btn-primary w-full">Edit Profile</button>
          </template>
        </div>

        <!-- Divider + links -->
        <div class="divider-section">
          <button @click="openPasswordModal" class="menu-item">
            <span class="menu-icon menu-icon--purple">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
              </svg>
            </span>
            <span class="menu-label">Change Password</span>
            <svg class="menu-arrow" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
            </svg>
          </button>

          <button class="menu-item menu-item--red">
            <span class="menu-icon menu-icon--red">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
              </svg>
            </span>
            <span class="menu-label">Sign Out</span>
            <svg class="menu-arrow" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
            </svg>
          </button>
        </div>

      </div>
    </div>

    <!-- Change Password Modal -->
    <transition name="overlay">
      <div v-if="showPasswordModal" class="modal-backdrop" @click.self="closePasswordModal">
        <div class="modal-backdrop-bg" @click="closePasswordModal" />
        <transition name="sheet">
          <div v-if="showPasswordModal" class="modal-sheet">
            <div class="drag-handle" />

            <div class="modal-header">
              <div>
                <h3 class="modal-title">Change Password</h3>
                <p class="modal-subtitle">Choose a strong new password</p>
              </div>
              <button @click="closePasswordModal" class="close-btn">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>

            <transition name="fade">
              <div v-if="pwSuccess" class="pw-success">
                <div class="pw-success-icon">
                  <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                  </svg>
                </div>
                <p class="pw-success-title">Password updated!</p>
                <p class="pw-success-sub">You're all set. Closing…</p>
              </div>
            </transition>

            <div v-if="!pwSuccess" class="pw-fields">
              <!-- Current Password -->
              <div class="field">
                <label>Current Password</label>
                <div class="pw-input-wrap">
                  <input v-model="pwForm.current" :type="showCurrent ? 'text' : 'password'" placeholder="Enter current password"
                    :class="['field-input', pwErrors.current && 'field-input--error']" />
                  <button type="button" @click="showCurrent = !showCurrent" class="eye-btn">
                    <svg v-if="!showCurrent" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                    <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
                  </button>
                </div>
                <p v-if="pwErrors.current" class="error-msg">{{ pwErrors.current }}</p>
              </div>

              <!-- New Password -->
              <div class="field">
                <label>New Password</label>
                <div class="pw-input-wrap">
                  <input v-model="pwForm.newPw" :type="showNew ? 'text' : 'password'" placeholder="Min. 8 characters"
                    :class="['field-input', pwErrors.newPw && 'field-input--error']" />
                  <button type="button" @click="showNew = !showNew" class="eye-btn">
                    <svg v-if="!showNew" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                    <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
                  </button>
                </div>
                <div v-if="pwForm.newPw" class="strength-wrap">
                  <div class="strength-bars">
                    <div v-for="i in 4" :key="i" class="strength-bar" :style="{ background: i <= pwStrength ? pwStrengthColors[pwStrength] : '#e5e7eb' }" />
                  </div>
                  <p class="strength-label" :style="{ color: pwStrengthColors[pwStrength] }">{{ pwStrengthLabel }}</p>
                </div>
                <p v-if="pwErrors.newPw" class="error-msg">{{ pwErrors.newPw }}</p>
              </div>

              <!-- Confirm Password -->
              <div class="field">
                <label>Confirm New Password</label>
                <div class="pw-input-wrap">
                  <input v-model="pwForm.confirm" :type="showConfirm ? 'text' : 'password'" placeholder="Re-enter new password"
                    :class="['field-input', pwErrors.confirm && 'field-input--error']" />
                  <button type="button" @click="showConfirm = !showConfirm" class="eye-btn">
                    <svg v-if="!showConfirm" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                    <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
                  </button>
                </div>
                <p v-if="pwErrors.confirm" class="error-msg">{{ pwErrors.confirm }}</p>
              </div>

              <button @click="handleChangePassword" :disabled="pwSaving" class="btn-primary w-full mt-1">
                {{ pwSaving ? 'Updating...' : 'Update Password' }}
              </button>
            </div>
          </div>
        </transition>
      </div>
    </transition>
  </div>
</template>

<style scoped>
/* ── Page ── */
.page-bg {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 48px 16px;
  background: linear-gradient(135deg, #ede9fe 0%, #dbeafe 100%);
  position: relative;
  overflow: hidden;
}

.orb {
  position: absolute;
  border-radius: 9999px;
  opacity: 0.18;
  filter: blur(60px);
  pointer-events: none;
}
.orb-top {
  top: 0; right: 0;
  width: 380px; height: 380px;
  background: linear-gradient(135deg, #7c3aed, #a855f7);
  animation: float 20s ease-in-out infinite;
}
.orb-bottom {
  bottom: 0; left: 0;
  width: 320px; height: 320px;
  background: linear-gradient(135deg, #3b82f6, #60a5fa);
  animation: float 15s ease-in-out infinite reverse;
}

/* ── Card ── */
.card-wrap { position: relative; z-index: 10; width: 100%; max-width: 384px; }
.card {
  background: #ffffff;
  border-radius: 20px;
  padding: 36px;
  box-shadow: 0 8px 40px rgba(124,58,237,0.10), 0 1.5px 6px rgba(0,0,0,0.04);
  border: 1px solid rgba(255,255,255,0.8);
}

/* ── Header ── */
.card-header { display: flex; align-items: center; margin-bottom: 24px; }
.back-btn {
  padding: 8px; margin-left: -8px; border-radius: 8px;
  background: none; border: none; cursor: pointer;
  color: #9ca3af; transition: color 0.2s, background 0.2s;
}
.back-btn:hover { color: #374151; background: #f3f4f6; }
.header-logo { flex: 1; height: 40px; width: auto; object-fit: contain; }

/* ── Avatar ── */
.avatar-wrap { display: flex; justify-content: center; margin-bottom: 20px; position: relative; width: fit-content; margin-inline: auto; }
.avatar-btn {
  width: 96px; height: 96px; border-radius: 9999px; overflow: hidden;
  border: 4px solid #ede9fe; background: none; cursor: default;
  transition: border-color 0.3s;
}
.avatar-btn.editable { cursor: pointer; }
.avatar-btn.editable:hover { border-color: #c4b5fd; }
.avatar-img { width: 100%; height: 100%; object-fit: cover; }
.avatar-initials {
  width: 100%; height: 100%;
  background: linear-gradient(135deg, #a78bfa, #60a5fa);
  display: flex; align-items: center; justify-content: center;
  font-size: 24px; font-weight: 700; color: #fff; user-select: none;
}
.avatar-edit-btn {
  position: absolute; bottom: 0; right: 0;
  width: 28px; height: 28px; border-radius: 9999px;
  background: linear-gradient(135deg, #7c3aed, #3b82f6);
  border: none; cursor: pointer; display: flex; align-items: center; justify-content: center;
  box-shadow: 0 4px 12px rgba(124,58,237,0.30);
  transition: transform 0.2s;
}
.avatar-edit-btn:hover { transform: scale(1.1); }

/* ── Name display ── */
.name-display { text-align: center; margin-bottom: 20px; }
.name-text { font-size: 17px; font-weight: 600; color: #111827; margin: 0; }
.email-text { font-size: 13px; color: #6b7280; margin: 4px 0 0; }

/* ── Toast ── */
.success-toast {
  display: flex; align-items: center; gap: 8px;
  padding: 12px 16px; margin-bottom: 20px; border-radius: 10px;
  background: #f0fdf4; border: 1px solid #bbf7d0;
  font-size: 13px; font-weight: 500; color: #15803d;
}

/* ── Fields ── */
.fields { display: flex; flex-direction: column; gap: 16px; margin-bottom: 20px; }
.field { display: flex; flex-direction: column; gap: 6px; }
.field label {
  font-size: 11px; font-weight: 600; letter-spacing: 0.08em;
  text-transform: uppercase; color: #6b7280;
}
.field-input {
  width: 100%; padding: 11px 14px; font-size: 14px;
  border: 1.5px solid #e5e7eb; border-radius: 10px;
  background: #f9fafb; color: #111827; outline: none;
  transition: border-color 0.2s, box-shadow 0.2s, background 0.2s;
  box-sizing: border-box;
}
.field-input::placeholder { color: #9ca3af; }
.field-input:focus { border-color: #7c3aed; background: #fff; box-shadow: 0 0 0 3px rgba(124,58,237,0.10); }
.field-input--disabled { background: #f9fafb; color: #6b7280; cursor: default; border-color: #f3f4f6; }
.field-input--error { border-color: #ef4444; }
.error-msg { font-size: 12px; color: #ef4444; }

/* ── Buttons ── */
.btn-primary {
  padding: 13px 20px; font-size: 14px; font-weight: 600;
  color: #fff; background: linear-gradient(90deg, #7c3aed, #3b82f6);
  border: none; border-radius: 10px; cursor: pointer;
  box-shadow: 0 4px 14px rgba(124,58,237,0.25);
  transition: opacity 0.2s, transform 0.15s, box-shadow 0.2s;
}
.btn-primary:hover:not(:disabled) { opacity: 0.92; transform: translateY(-1px); box-shadow: 0 6px 20px rgba(124,58,237,0.30); }
.btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }
.btn-cancel {
  flex: 1; padding: 13px; font-size: 14px; font-weight: 600;
  color: #6b7280; background: #f3f4f6; border: none; border-radius: 10px;
  cursor: pointer; transition: background 0.2s;
}
.btn-cancel:hover { background: #e5e7eb; }
.btn-row { display: flex; gap: 12px; }
.btn-row .btn-primary { flex: 1; }
.w-full { width: 100%; }
.actions { margin-bottom: 0; }

/* ── Divider section ── */
.divider-section { border-top: 1px solid #f3f4f6; margin-top: 20px; padding-top: 16px; display: flex; flex-direction: column; gap: 4px; }
.menu-item {
  width: 100%; display: flex; align-items: center; gap: 12px;
  padding: 12px; border-radius: 10px; background: none; border: none;
  cursor: pointer; font-size: 14px; font-weight: 500; color: #4b5563;
  transition: background 0.2s;
}
.menu-item:hover { background: #f9fafb; }
.menu-item--red { color: #ef4444; }
.menu-item--red:hover { background: #fef2f2; }
.menu-icon {
  width: 32px; height: 32px; border-radius: 9999px;
  display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.menu-icon--purple { background: #f5f3ff; color: #7c3aed; }
.menu-icon--red { background: #fef2f2; color: #ef4444; }
.menu-label { flex: 1; text-align: left; }
.menu-arrow { width: 16px; height: 16px; color: #d1d5db; flex-shrink: 0; }

/* ── Modal ── */
.modal-backdrop {
  position: fixed; inset: 0; z-index: 50;
  display: flex; align-items: flex-end; justify-content: center;
}
@media (min-width: 640px) { .modal-backdrop { align-items: center; padding: 16px; } }
.modal-backdrop-bg { position: absolute; inset: 0; background: rgba(0,0,0,0.35); backdrop-filter: blur(4px); }
.modal-sheet {
  position: relative; z-index: 10; width: 100%;
  background: #fff; border-radius: 24px 24px 0 0;
  padding: 20px 24px 32px; box-shadow: 0 -8px 40px rgba(0,0,0,0.12);
}
@media (min-width: 640px) {
  .modal-sheet { max-width: 400px; border-radius: 20px; padding: 32px; }
}
.drag-handle { width: 40px; height: 4px; border-radius: 9999px; background: #e5e7eb; margin: 0 auto 16px; }
@media (min-width: 640px) { .drag-handle { display: none; } }
.modal-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 24px; }
.modal-title { font-size: 15px; font-weight: 600; color: #111827; margin: 0; }
.modal-subtitle { font-size: 12px; color: #6b7280; margin: 4px 0 0; }
.close-btn {
  padding: 8px; border-radius: 8px; background: none; border: none; cursor: pointer;
  color: #9ca3af; transition: color 0.2s, background 0.2s;
}
.close-btn:hover { color: #374151; background: #f3f4f6; }

/* Password modal content */
.pw-fields { display: flex; flex-direction: column; gap: 16px; }
.pw-input-wrap { position: relative; }
.pw-input-wrap .field-input { padding-right: 44px; }
.eye-btn {
  position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
  background: none; border: none; cursor: pointer; color: #9ca3af; transition: color 0.2s;
}
.eye-btn:hover { color: #6b7280; }
.strength-wrap { margin-top: 6px; }
.strength-bars { display: flex; gap: 4px; margin-bottom: 4px; }
.strength-bar { height: 4px; flex: 1; border-radius: 9999px; transition: background 0.3s; }
.strength-label { font-size: 11px; font-weight: 500; }

/* Success state */
.pw-success { display: flex; flex-direction: column; align-items: center; gap: 10px; padding: 24px 0; text-align: center; }
.pw-success-icon {
  width: 56px; height: 56px; border-radius: 9999px;
  background: linear-gradient(135deg, #7c3aed, #3b82f6);
  display: flex; align-items: center; justify-content: center;
  box-shadow: 0 8px 24px rgba(124,58,237,0.30);
}
.pw-success-title { font-size: 15px; font-weight: 600; color: #111827; margin: 0; }
.pw-success-sub { font-size: 13px; color: #6b7280; margin: 0; }

/* ── Animations ── */
@keyframes float {
  0%, 100% { transform: translate(0, 0); }
  50% { transform: translate(30px, -30px); }
}
@keyframes logoFloat {
  0%, 100% { transform: translateY(0); }
  50% { transform: translateY(-6px); }
}
@keyframes slideUp {
  from { opacity: 0; transform: translateY(30px); }
  to { opacity: 1; transform: translateY(0); }
}

.overlay-enter-active, .overlay-leave-active { transition: opacity 0.3s ease; }
.overlay-enter-from, .overlay-leave-to { opacity: 0; }

.sheet-enter-active { transition: transform 0.35s cubic-bezier(0.32, 0.72, 0, 1), opacity 0.3s ease; }
.sheet-leave-active { transition: transform 0.25s ease-in, opacity 0.2s ease; }
.sheet-enter-from, .sheet-leave-to { transform: translateY(100%); opacity: 0; }
@media (min-width: 640px) {
  .sheet-enter-from, .sheet-leave-to { transform: scale(0.95) translateY(0); opacity: 0; }
}

.fade-enter-active, .fade-leave-active { transition: opacity 0.4s ease, transform 0.4s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; transform: translateY(-6px); }
</style>