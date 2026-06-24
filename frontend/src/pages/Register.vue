<script setup>
import { reactive, ref } from 'vue'
import axios from 'axios'
import { useRouter } from 'vue-router'

const router = useRouter()

const form = reactive({
  name: '',
  email: '',
  password: '',
  confirmPassword: ''
})

const errors = ref({})
const message = ref('')
const success = ref(false)
const isSubmitting = ref(false)

const register = async () => {
  message.value = ''
  errors.value = {}

  if (form.password !== form.confirmPassword) {
    errors.value.confirmPassword = 'Passwords do not match'
    return
  }

  isSubmitting.value = true

  try {
    await axios.post('http://localhost:8000/api/register', {
      name: form.name,
      email: form.email,
      password: form.password
    })

    success.value = true
    message.value = 'Registration successful!'

    setTimeout(() => {
      router.push('/login')
    }, 1500)

  } catch (error) {
    success.value = false
    message.value = error.response?.data?.message || 'Registration failed. Please try again.'
  } finally {
    isSubmitting.value = false
  }
}
</script>

<template>
  <div class="min-h-screen flex items-center justify-center px-4 sm:px-6 lg:px-8 page-bg">

    <!-- Card -->
    <div class="card w-full max-w-md sm:max-w-lg">

      <!-- Logo -->
      <div class="flex justify-center mb-8">
        <img
          src="@/assets/logo.png"
          alt="EventOra Logo"
          class="h-14 w-auto object-contain"
        />
      </div>

      <!-- Full Name -->
      <div class="field">
        <label>Full Name</label>
        <input
          v-model="form.name"
          type="text"
          placeholder="Enter your full name"
          required
        />
      </div>

      <!-- Email -->
      <div class="field">
        <label>Email Address</label>
        <input
          v-model="form.email"
          type="email"
          placeholder="you@example.com"
          required
        />
      </div>

      <!-- Password -->
      <div class="field">
        <label>Password</label>
        <input
          v-model="form.password"
          type="password"
          placeholder="Enter your password"
          required
        />
      </div>

      <!-- Confirm Password -->
      <div class="field">
        <label>Confirm Password</label>
        <input
          v-model="form.confirmPassword"
          type="password"
          placeholder="Confirm your password"
          required
          :class="{ 'input-error': errors.confirmPassword }"
        />
        <p v-if="errors.confirmPassword" class="error-msg">
          {{ errors.confirmPassword }}
        </p>
      </div>

      <!-- Submit -->
      <button
        @click="register"
        :disabled="isSubmitting"
        class="btn-primary"
      >
        {{ isSubmitting ? 'Creating Account...' : 'Create Account' }}
      </button>

      <!-- Feedback -->
      <p v-if="message" class="feedback-msg" :class="success ? 'text-green-500' : 'text-red-500'">
        {{ message }}
      </p>

      <!-- Footer -->
      <p class="footer-text">
        Already have an account?
        <router-link to="/login" class="footer-link">Sign In</router-link>
      </p>

    </div>
  </div>
</template>

<style scoped>
.page-bg {
  background: linear-gradient(135deg, #ede9fe 0%, #dbeafe 100%);
  min-height: 100vh;
  padding: 48px 16px 60px;
}

.card {
  background: #ffffff;
  border-radius: 20px;
  padding: 40px 28px;
  box-shadow: 0 8px 40px rgba(124, 58, 237, 0.10), 0 1.5px 6px rgba(0,0,0,0.04);
  border: 1px solid rgba(255,255,255,0.8);
}

.field {
  margin-bottom: 18px;
}

.field label {
  display: block;
  font-size: 13px;
  font-weight: 600;
  color: #374151;
  margin-bottom: 6px;
  text-align: left;
}

.field input {
  width: 100%;
  padding: 11px 14px;
  font-size: 14px;
  border: 1.5px solid #e5e7eb;
  border-radius: 10px;
  background: #f9fafb;
  color: #111827;
  outline: none;
  transition: border-color 0.2s, box-shadow 0.2s;
  box-sizing: border-box;
}

.field input::placeholder {
  color: #9ca3af;
}

.field input:focus {
  border-color: #7c3aed;
  background: #fff;
  box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.10);
}

.field input.input-error {
  border-color: #ef4444;
}

.error-msg {
  font-size: 12px;
  color: #ef4444;
  margin-top: 4px;
  text-align: left;
}

.btn-primary {
  width: 100%;
  padding: 13px;
  margin-top: 6px;
  font-size: 15px;
  font-weight: 600;
  color: #ffffff;
  background: linear-gradient(90deg, #7c3aed, #3b82f6);
  border: none;
  border-radius: 10px;
  cursor: pointer;
  transition: opacity 0.2s, transform 0.15s, box-shadow 0.2s;
  box-shadow: 0 4px 14px rgba(124, 58, 237, 0.25);
}

.btn-primary:hover:not(:disabled) {
  opacity: 0.92;
  transform: translateY(-1px);
  box-shadow: 0 6px 20px rgba(124, 58, 237, 0.30);
}

.btn-primary:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.feedback-msg {
  font-size: 13px;
  text-align: center;
  margin-top: 10px;
}

.footer-text {
  font-size: 13px;
  color: #6b7280;
  text-align: center;
  margin-top: 24px;
  padding-top: 20px;
  border-top: 1px solid #f3f4f6;
}

.footer-link {
  font-weight: 600;
  background: linear-gradient(90deg, #7c3aed, #3b82f6);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
  text-decoration: none;
}
</style>