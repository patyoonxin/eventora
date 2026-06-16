<script setup>
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'

const router = useRouter()

const form = ref({ password: '', confirm: '' })
const errors = ref({})
const isSubmitting = ref(false)
const submitted = ref(false)
const showPassword = ref(false)
const showConfirm = ref(false)

const pwStrength = computed(() => {
  const pw = form.value.password
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

const validate = () => {
  errors.value = {}
  if (!form.value.password) errors.value.password = 'Password is required'
  else if (form.value.password.length < 8) errors.value.password = 'Must be at least 8 characters'
  if (!form.value.confirm) errors.value.confirm = 'Please confirm your password'
  else if (form.value.password !== form.value.confirm) errors.value.confirm = 'Passwords do not match'
  return Object.keys(errors.value).length === 0
}

const handleSubmit = async () => {
  if (!validate()) return
  isSubmitting.value = true
  await new Promise(r => setTimeout(r, 1500))
  isSubmitting.value = false
  submitted.value = true
}
</script>

<template>
  <div class="page-bg">
    <div class="orb orb-top" />
    <div class="orb orb-bottom" />

    <div class="card-wrap" style="animation: slideUp 0.6s ease-out">
      <div class="card">

        <!-- Logo -->
        <div class="logo-wrap" style="animation: logoFloat 3s ease-in-out infinite">
          <img src="@/assets/logo.png" alt="EventOra Logo" class="logo" />
        </div>

        <!-- Success State -->
        <div v-if="submitted" class="success-state">
          <div class="success-icon">
            <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
          </div>
          <h2 class="success-title">Password updated!</h2>
          <p class="success-body">Your password has been reset successfully. You can now sign in with your new password.</p>
          <button @click="router.push('/login')" class="btn-primary w-full">
            Go to Sign In
          </button>
        </div>

        <!-- Form State -->
        <div v-else class="form-state">
          <div class="form-heading">
            <h2 class="form-title">Create new password</h2>
            <p class="form-subtitle">Your new password must be at least 8 characters.</p>
          </div>

          <!-- New Password -->
          <div class="field">
            <label>New Password</label>
            <div class="pw-wrap">
              <input
                v-model="form.password"
                :type="showPassword ? 'text' : 'password'"
                placeholder="Enter new password"
                :class="['field-input', errors.password && 'field-input--error']"
              />
              <button type="button" @click="showPassword = !showPassword" class="eye-btn">
                <svg v-if="!showPassword" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                </svg>
                <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                </svg>
              </button>
            </div>
            <!-- Strength bar -->
            <div v-if="form.password" class="strength-wrap">
              <div class="strength-bars">
                <div v-for="i in 4" :key="i" class="strength-bar"
                  :style="{ background: i <= pwStrength ? pwStrengthColors[pwStrength] : '#e5e7eb' }" />
              </div>
              <p class="strength-label" :style="{ color: pwStrengthColors[pwStrength] }">{{ pwStrengthLabel }}</p>
            </div>
            <p v-if="errors.password" class="error-msg">{{ errors.password }}</p>
          </div>

          <!-- Confirm Password -->
          <div class="field">
            <label>Confirm Password</label>
            <div class="pw-wrap">
              <input
                v-model="form.confirm"
                :type="showConfirm ? 'text' : 'password'"
                placeholder="Re-enter new password"
                :class="['field-input', errors.confirm && 'field-input--error']"
              />
              <button type="button" @click="showConfirm = !showConfirm" class="eye-btn">
                <svg v-if="!showConfirm" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                </svg>
                <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                </svg>
              </button>
            </div>
            <p v-if="errors.confirm" class="error-msg">{{ errors.confirm }}</p>
          </div>

          <button @click="handleSubmit" :disabled="isSubmitting" class="btn-primary w-full">
            {{ isSubmitting ? 'Updating...' : 'Reset Password' }}
          </button>
        </div>

        <!-- Footer -->
        <div v-if="!submitted" class="footer">
          <p class="footer-text">
            Remember your password?
            <router-link to="/login" class="footer-link">Sign In</router-link>
          </p>
        </div>

      </div>
    </div>
  </div>
</template>

<style scoped>
.page-bg {
  min-height: 100vh;
  display: flex; align-items: center; justify-content: center;
  padding: 48px 16px;
  background: linear-gradient(135deg, #ede9fe 0%, #dbeafe 100%);
  position: relative; overflow: hidden;
}
.orb {
  position: absolute; border-radius: 9999px;
  opacity: 0.18; filter: blur(60px); pointer-events: none;
}
.orb-top { top: 0; right: 0; width: 380px; height: 380px; background: linear-gradient(135deg, #7c3aed, #a855f7); animation: float 20s ease-in-out infinite; }
.orb-bottom { bottom: 0; left: 0; width: 320px; height: 320px; background: linear-gradient(135deg, #3b82f6, #60a5fa); animation: float 15s ease-in-out infinite reverse; }

.card-wrap { position: relative; z-index: 10; width: 100%; max-width: 360px; }
.card {
  background: #fff; border-radius: 20px; padding: 36px;
  box-shadow: 0 8px 40px rgba(124,58,237,0.10), 0 1.5px 6px rgba(0,0,0,0.04);
  border: 1px solid rgba(255,255,255,0.8);
}

.logo-wrap { display: flex; justify-content: center; margin-bottom: 28px; }
.logo { height: 48px; width: auto; object-fit: contain; }

/* Success */
.success-state { display: flex; flex-direction: column; align-items: center; gap: 14px; text-align: center; }
.success-icon {
  width: 64px; height: 64px; border-radius: 9999px;
  background: linear-gradient(135deg, #7c3aed, #3b82f6);
  display: flex; align-items: center; justify-content: center;
  box-shadow: 0 8px 24px rgba(124,58,237,0.30);
}
.success-title { font-size: 17px; font-weight: 600; color: #111827; margin: 0; }
.success-body { font-size: 13px; color: #6b7280; margin: 0; line-height: 1.6; }

/* Form */
.form-state { display: flex; flex-direction: column; gap: 18px; margin-bottom: 20px; }
.form-heading { display: flex; flex-direction: column; gap: 4px; }
.form-title { font-size: 17px; font-weight: 600; color: #111827; margin: 0; }
.form-subtitle { font-size: 13px; color: #6b7280; margin: 0; }

.field { display: flex; flex-direction: column; gap: 6px; }
.field label { font-size: 12px; font-weight: 600; color: #374151; }

.pw-wrap { position: relative; }
.field-input {
  width: 100%; padding: 11px 44px 11px 14px; font-size: 14px;
  border: 1.5px solid #e5e7eb; border-radius: 10px;
  background: #f9fafb; color: #111827; outline: none;
  transition: border-color 0.2s, box-shadow 0.2s;
  box-sizing: border-box;
}
.field-input::placeholder { color: #9ca3af; }
.field-input:focus { border-color: #7c3aed; background: #fff; box-shadow: 0 0 0 3px rgba(124,58,237,0.10); }
.field-input--error { border-color: #ef4444; }

.eye-btn {
  position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
  background: none; border: none; cursor: pointer; color: #9ca3af; transition: color 0.2s;
}
.eye-btn:hover { color: #6b7280; }

.strength-wrap { margin-top: 4px; }
.strength-bars { display: flex; gap: 4px; margin-bottom: 4px; }
.strength-bar { height: 4px; flex: 1; border-radius: 9999px; transition: background 0.3s; }
.strength-label { font-size: 11px; font-weight: 500; margin: 0; }

.error-msg { font-size: 12px; color: #ef4444; }

.btn-primary {
  display: block; padding: 13px 20px; font-size: 14px; font-weight: 600;
  color: #fff; background: linear-gradient(90deg, #7c3aed, #3b82f6);
  border: none; border-radius: 10px; cursor: pointer;
  box-shadow: 0 4px 14px rgba(124,58,237,0.25);
  transition: opacity 0.2s, transform 0.15s, box-shadow 0.2s;
  text-align: center;
}
.btn-primary:hover:not(:disabled) { opacity: 0.92; transform: translateY(-1px); }
.btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }
.w-full { width: 100%; }

.footer { border-top: 1px solid #f3f4f6; margin-top: 20px; padding-top: 16px; }
.footer-text { font-size: 13px; color: #6b7280; text-align: center; margin: 0; }
.footer-link {
  font-weight: 600;
  background: linear-gradient(90deg, #7c3aed, #3b82f6);
  -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
  text-decoration: none;
}

@keyframes float {
  0%, 100% { transform: translate(0, 0); }
  50% { transform: translate(30px, -30px); }
}
@keyframes logoFloat {
  0%, 100% { transform: translateY(0); }
  50% { transform: translateY(-8px); }
}
@keyframes slideUp {
  from { opacity: 0; transform: translateY(30px); }
  to { opacity: 1; transform: translateY(0); }
}
</style>