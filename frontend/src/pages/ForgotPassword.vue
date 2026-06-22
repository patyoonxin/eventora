<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'

const router = useRouter()
const email = ref('')
const isSubmitting = ref(false)
const submitted = ref(false)
const errors = ref({})

const API_BASE = import.meta.env.VITE_API_BASE_URL

const isValidEmail = (val) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val)

const handleSubmit = async () => {
  errors.value = {}

  if (!email.value.trim()) {
    errors.value.email = 'Email is required'
    return
  }
  if (!isValidEmail(email.value)) {
    errors.value.email = 'Please enter a valid email'
    return
  }

  isSubmitting.value = true
  try {
    const response = await fetch(`${API_BASE}/api/forgot-password`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
      },
      body: JSON.stringify({ email: email.value }),
    })

    const text = await response.text()
    console.log('Response:', text)

    if (!response.ok) {
      errors.value.email = JSON.parse(text).error || 'Something went wrong.'
      return
    }

    submitted.value = true
  } catch (err) {
    errors.value.email = 'Something went wrong. Please try again.'
  } finally {
    isSubmitting.value = false
  }
}
</script>

<template>
  <div class="page-bg px-4 sm:px-6 lg:px-8">
    <div class="orb orb-top" />
    <div class="orb orb-bottom" />

    <div class="card-wrap mx-auto" style="animation: slideUp 0.6s ease-out">
      <div class="card">

        <!-- Logo -->
        <div class="logo-wrap" style="animation: logoFloat 3s ease-in-out infinite">
          <img src="@/assets/logo.png" alt="EventOra Logo" class="logo" />
        </div>

        <!-- Success State -->
      <div v-if="submitted" class="success-state">
          <div class="success-icon">
            <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
            </svg>
        </div>
        <h2 class="success-title">Check your email</h2>
        <p class="success-body">
          If <span class="email-highlight">{{ email }}</span> is registered, you'll receive a reset link shortly.
        </p>
        <button @click="router.push('/login')" class="btn-primary w-full">
          Back to Sign In
        </button>
      </div>

        <!-- Form State -->
        <div v-else class="form-state">
          <div class="form-heading">
            <h2 class="form-title">Forgot your password?</h2>
            <p class="form-subtitle">Enter your email and we'll send you a reset link.</p>
          </div>

          <div class="field">
            <label>Email Address</label>
            <input
              v-model="email"
              type="email"
              placeholder="you@example.com"
              :class="['field-input', errors.email && 'field-input--error']"
            />
            <p v-if="errors.email" class="error-msg">{{ errors.email }}</p>
          </div>

          <button @click="handleSubmit" :disabled="isSubmitting" class="btn-primary w-full">
            {{ isSubmitting ? 'Sending...' : 'Send Reset Link' }}
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

.card-wrap { position: relative; z-index: 10; width: 100%; max-width: 360px; margin-inline: auto; }
.card {
  background: #fff; border-radius: 20px; padding: 36px;
  box-shadow: 0 8px 40px rgba(124,58,237,0.10), 0 1.5px 6px rgba(0,0,0,0.04);
  border: 1px solid rgba(255,255,255,0.8);
}

.logo-wrap { display: flex; justify-content: center; margin-bottom: 28px; }
.logo { height: 48px; width: auto; object-fit: contain; }

/* Success */
.success-state { display: flex; flex-direction: column; align-items: center; gap: 12px; text-align: center; margin-bottom: 0; }
.success-icon {
  width: 64px; height: 64px; border-radius: 9999px;
  background: linear-gradient(135deg, #7c3aed, #3b82f6);
  display: flex; align-items: center; justify-content: center;
  box-shadow: 0 8px 24px rgba(124,58,237,0.30);
}
.success-title { font-size: 17px; font-weight: 600; color: #111827; margin: 0; }
.success-body { font-size: 13px; color: #6b7280; margin: 0; line-height: 1.6; }
.email-highlight { font-weight: 600; color: #7c3aed; }

/* Form */
.form-state { display: flex; flex-direction: column; gap: 18px; margin-bottom: 20px; }
.form-heading { display: flex; flex-direction: column; gap: 4px; }
.form-title { font-size: 17px; font-weight: 600; color: #111827; margin: 0; }
.form-subtitle { font-size: 13px; color: #6b7280; margin: 0; }

.field { display: flex; flex-direction: column; gap: 6px; }
.field label { font-size: 12px; font-weight: 600; color: #374151; }
.field-input {
  width: 100%; padding: 11px 14px; font-size: 14px;
  border: 1.5px solid #e5e7eb; border-radius: 10px;
  background: #f9fafb; color: #111827; outline: none;
  transition: border-color 0.2s, box-shadow 0.2s;
  box-sizing: border-box;
}
.field-input::placeholder { color: #9ca3af; }
.field-input:focus { border-color: #7c3aed; background: #fff; box-shadow: 0 0 0 3px rgba(124,58,237,0.10); }
.field-input--error { border-color: #ef4444; }
.error-msg { font-size: 12px; color: #ef4444; }

/* Buttons */
.btn-primary {
  display: block; padding: 13px 20px; font-size: 14px; font-weight: 600;
  color: #fff; background: linear-gradient(90deg, #7c3aed, #3b82f6);
  border: none; border-radius: 10px; cursor: pointer;
  box-shadow: 0 4px 14px rgba(124,58,237,0.25);
  transition: opacity 0.2s, transform 0.15s, box-shadow 0.2s;
  text-align: center;
}
.btn-primary:hover:not(:disabled) { opacity: 0.92; transform: translateY(-1px); box-shadow: 0 6px 20px rgba(124,58,237,0.30); }
.btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }

.btn-ghost {
  display: block; padding: 11px 20px; font-size: 14px; font-weight: 600;
  color: #6b7280; background: #f3f4f6;
  border: none; border-radius: 10px; cursor: pointer;
  transition: background 0.2s; text-align: center;
}
.btn-ghost:hover { background: #e5e7eb; }

.w-full { width: 100%; }
.mt-2 { margin-top: 8px; }

/* Footer */
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

@media (max-width: 640px) {
  .page-bg { padding: 32px 12px; }
  .card { padding: 28px 22px; }
}
</style>