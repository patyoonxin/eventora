<script setup>
import { ref } from "vue"
import { useRouter } from "vue-router"
import { useAuthStore } from "@/stores/auth" // [1] Import the Pinia store

const router = useRouter()
const authStore = useAuthStore() // [2] Initialize your store instance

const email = ref("")
const password = ref("")
const rememberMe = ref(false)
const showPassword = ref(false)
const errors = ref({})
const isSubmitting = ref(false)

const handleSubmit = async () => {
  errors.value = {}

  // Basic validation (Keep this pristine!)
  if (!email.value.trim()) {
    errors.value.email = "Email is required"
  } else if (!isValidEmail(email.value)) {
    errors.value.email = "Please enter a valid email"
  }

  if (!password.value) {
    errors.value.password = "Password is required"
  }

  // If frontend validation passes, trigger Pinia action
  if (Object.keys(errors.value).length === 0) {
    isSubmitting.value = true

    try {
      // [3] Call your clean global login action instead of local fetch
      const loggedInUser = await authStore.login(email.value, password.value)

      console.log("Login successful via Pinia!", loggedInUser)

      // [4] Use the returned user entity or authStore.userRole to process role route splits 
      if (loggedInUser.role === "faculty_admin") {
        router.push("/admin/home")
      } else if (loggedInUser.role === "organiser") {
        router.push("/society/home")
      } else {
        router.push("/home")
      }
    } catch (err) {
      // Assign the backend error message directly to your existing feedback blocks
      errors.value.password =
        err.message || "Unable to connect to authentication server."
    } finally {
      isSubmitting.value = false
    }
  }
}

const isValidEmail = (email) => {
  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
  return emailRegex.test(email)
}

const togglePasswordVisibility = () => {
  showPassword.value = !showPassword.value
}

const handleForgotPassword = () => {
  console.log("Forgot password clicked")
}
</script>

<template>
  <!-- Main Container -->
  <div
    class="min-h-screen flex items-center justify-center px-4 sm:px-6 lg:px-8 py-12 bg-gradient-to-br from-purple-50 to-blue-50 dark:from-slate-950 dark:to-slate-900 relative overflow-hidden"
  >
    <!-- Decorative Gradient Orbs -->
    <div
      class="absolute top-0 right-0 w-96 h-96 bg-gradient-to-br from-purple-500 to-purple-600 rounded-full opacity-20 blur-3xl pointer-events-none animate-float"
      style="animation: float 20s ease-in-out infinite"
    ></div>
    <div
      class="absolute bottom-0 left-0 w-80 h-80 bg-gradient-to-br from-blue-500 to-blue-600 rounded-full opacity-20 blur-3xl pointer-events-none animate-float-reverse"
      style="animation: float 15s ease-in-out infinite reverse"
    ></div>

    <!-- Login Card -->
    <div
      class="relative z-10 w-full max-w-sm animate-slide-up"
      style="animation: slideUp 0.6s ease-out"
    >
      <div
        class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl p-8 sm:p-10 backdrop-blur-sm"
      >
        <!-- Logo Section -->
        <div class="flex justify-center mb-6 sm:mb-8">
          <div
            class="animate-logo-float"
            style="animation: logoFloat 3s ease-in-out infinite"
          >
            <img
              src="@/assets/logo.png"
              alt="EventOra Logo"
              class="w-42 sm:w-50 h-auto object-contain"
              style="filter: drop-shadow(0 10px 25px rgba(124, 58, 237, 0.1))"
            />
          </div>
        </div>

        <!-- Form -->
        <form
          @submit.prevent="handleSubmit"
          class="space-y-5 sm:space-y-6 mb-6 sm:mb-7"
        >
          <!-- Email Field -->
          <div class="space-y-2">
            <label
              for="email"
              class="block text-sm font-semibold text-gray-700 dark:text-gray-200 text-left"
            >
              Email Address
            </label>
            <input
              id="email"
              v-model="email"
              type="email"
              placeholder="you@example.com"
              class="w-full px-4 py-3 sm:py-3 text-sm border-2 border-gray-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 transition-all duration-300 focus:outline-none focus:border-purple-600 focus:ring-4 focus:ring-purple-100 dark:focus:ring-purple-900"
              :class="{
                'border-red-500 focus:ring-red-100 dark:focus:ring-red-900':
                  errors.email,
              }"
            />
            <p
              v-if="errors.email"
              class="text-xs sm:text-sm text-red-600 dark:text-red-400"
            >
              {{ errors.email }}
            </p>
          </div>

          <!-- Password Field -->
          <div class="space-y-2">
            <div class="flex justify-between items-center">
              <label
                for="password"
                class="block text-sm font-semibold text-gray-700 dark:text-gray-200"
              >
                Password
              </label>
              <button
                type="button"
                class="text-xs sm:text-sm font-semibold text-purple-600 dark:text-purple-300 hover:text-purple-700 dark:hover:text-purple-200 transition-all duration-300 hover:translate-x-0.5 p-0 bg-none border-none cursor-pointer"
                @click="handleForgotPassword"
              >
                Forgot Password?
              </button>
            </div>
            <div class="relative">
              <input
                id="password"
                v-model="password"
                :type="showPassword ? 'text' : 'password'"
                placeholder="Enter your password"
                class="w-full px-4 py-3 sm:py-3 text-sm border-2 border-gray-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 transition-all duration-300 focus:outline-none focus:border-purple-600 focus:ring-4 focus:ring-purple-100 dark:focus:ring-purple-900 pr-12"
                :class="{
                  'border-red-500 focus:ring-red-100 dark:focus:ring-red-900':
                    errors.password,
                }"
              />
              <button
                type="button"
                class="absolute right-3 top-1/2 -translate-y-1/2 text-lg transition-transform duration-200 hover:scale-110 focus:outline-none p-2"
                @click="togglePasswordVisibility"
                :aria-label="showPassword ? 'Hide password' : 'Show password'"
              >
                <span v-if="showPassword" class="pi pi-eye-slash"></span>
                <span v-else class="pi pi-eye"></span>
              </button>
            </div>
            <p
              v-if="errors.password"
              class="text-xs sm:text-sm text-red-600 dark:text-red-400"
            >
              {{ errors.password }}
            </p>
          </div>

          <!-- Remember Me Checkbox -->
          <div class="flex items-center gap-3 py-1">
            <input
              id="rememberMe"
              v-model="rememberMe"
              type="checkbox"
              class="w-4 h-4 sm:w-5 sm:h-5 rounded border-2 border-gray-300 dark:border-gray-500 cursor-pointer accent-purple-600 transition-colors duration-200"
            />
            <label
              for="rememberMe"
              class="text-sm text-gray-700 dark:text-gray-300 cursor-pointer select-none"
            >
              Remember me
            </label>
          </div>

          <!-- Submit Button -->
          <button
            type="submit"
            :disabled="isSubmitting"
            class="w-full px-4 py-3 sm:py-3 mt-2 text-sm sm:text-base font-semibold text-white bg-gradient-to-r from-purple-600 to-blue-600 dark:from-purple-500 dark:to-blue-500 rounded-lg transition-all duration-300 hover:-translate-y-0.5 hover:shadow-lg hover:shadow-purple-500/30 active:translate-y-0 focus:outline-none focus:ring-4 focus:ring-purple-300 dark:focus:ring-purple-700 relative overflow-hidden group disabled:opacity-50 disabled:cursor-not-allowed"
          >
            <span class="relative z-10">{{
              isSubmitting ? "Signing In..." : "Sign In"
            }}</span>
            <div
              class="absolute inset-0 bg-gradient-to-r from-purple-700 to-blue-700 dark:from-purple-600 dark:to-blue-600 translate-x-full group-hover:translate-x-0 transition-transform duration-300"
            ></div>
          </button>
        </form>

        <!-- Footer Info -->
        <div class="border-t border-gray-200 dark:border-gray-700 pt-4 sm:pt-6">
          <p
            class="text-xs sm:text-sm text-center text-gray-700 dark:text-gray-300"
          >
            <!--Ready to create events?-->
            <span
              class="font-semibold bg-gradient-to-r from-purple-600 to-blue-600 dark:from-purple-300 dark:to-blue-300 bg-clip-text text-transparent"
            >
              The Next-Gen Event Management Platform
            </span>
          </p>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
@keyframes float {
  0%,
  100% {
    transform: translate(0, 0);
  }
  50% {
    transform: translate(30px, -30px);
  }
}

@keyframes logoFloat {
  0%,
  100% {
    transform: translateY(0);
  }
  50% {
    transform: translateY(-10px);
  }
}

@keyframes slideUp {
  from {
    opacity: 0;
    transform: translateY(30px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}
</style>
