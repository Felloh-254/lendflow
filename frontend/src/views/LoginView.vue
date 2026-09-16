<script setup>
import { ref } from 'vue'
import { useRouter, useRoute, RouterLink } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { apiErrorMessage } from '../utils/apiError'
import AppButton from '../components/AppButton.vue'
import ErrorBanner from '../components/ErrorBanner.vue'

const auth = useAuthStore()
const router = useRouter()
const route = useRoute()

const email = ref('')
const password = ref('')
const loading = ref(false)
const error = ref('')

async function handleSubmit() {
  loading.value = true
  error.value = ''
  try {
    await auth.login(email.value, password.value)
    router.push(route.query.redirect || { name: 'dashboard' })
  } catch (e) {
    error.value = apiErrorMessage(e)
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="min-h-screen flex items-center justify-center bg-bg px-4">
    <div class="w-full max-w-sm">
      <div class="flex items-center gap-2 mb-8 justify-center">
        <div class="h-8 w-8 rounded-md bg-primary flex items-center justify-center text-white text-sm font-semibold">L</div>
        <span class="font-semibold tracking-tight text-lg text-text">LendFlow</span>
      </div>

      <div class="rounded-xl border border-border bg-surface p-6">
        <h1 class="text-lg font-semibold text-text mb-1">Sign in</h1>
        <p class="text-sm text-text-secondary mb-5">Access your LendFlow account.</p>

        <form class="space-y-4" @submit.prevent="handleSubmit">
          <ErrorBanner :message="error" />

          <div>
            <label class="block text-sm font-medium text-text mb-1.5" for="email">Email</label>
            <input
              id="email"
              v-model="email"
              type="email"
              required
              autocomplete="email"
              class="w-full rounded-lg border border-border bg-bg px-3 py-2 text-sm text-text placeholder:text-text-secondary focus:border-primary focus:outline-none"
              placeholder="you@example.com"
            />
          </div>

          <div>
            <label class="block text-sm font-medium text-text mb-1.5" for="password">Password</label>
            <input
              id="password"
              v-model="password"
              type="password"
              required
              autocomplete="current-password"
              class="w-full rounded-lg border border-border bg-bg px-3 py-2 text-sm text-text placeholder:text-text-secondary focus:border-primary focus:outline-none"
              placeholder="••••••••"
            />
          </div>

          <AppButton type="submit" class="w-full" :loading="loading">Sign in</AppButton>
        </form>
      </div>

      <p class="text-center text-sm text-text-secondary mt-5">
        New to LendFlow?
        <RouterLink :to="{ name: 'register' }" class="text-primary font-medium hover:underline">Create an account</RouterLink>
      </p>
    </div>
  </div>
</template>
