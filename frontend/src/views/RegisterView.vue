<script setup>
import { ref, reactive } from 'vue'
import { useRouter, RouterLink } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { apiErrorMessage } from '../utils/apiError'
import AppButton from '../components/AppButton.vue'
import ErrorBanner from '../components/ErrorBanner.vue'

const auth = useAuthStore()
const router = useRouter()

const form = reactive({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
  phone: '',
  national_id: '',
  date_of_birth: '',
  monthly_income: '',
  employment_status: 'employed',
})

const loading = ref(false)
const error = ref('')

async function handleSubmit() {
  loading.value = true
  error.value = ''
  try {
    await auth.register(form)
    router.push({ name: 'dashboard' })
  } catch (e) {
    error.value = apiErrorMessage(e)
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="min-h-screen flex items-center justify-center bg-bg px-4 py-10">
    <div class="w-full max-w-md">
      <div class="flex items-center gap-2 mb-8 justify-center">
        <div class="h-8 w-8 rounded-md bg-primary flex items-center justify-center text-white text-sm font-semibold">L</div>
        <span class="font-semibold tracking-tight text-lg text-text">LendFlow</span>
      </div>

      <div class="rounded-xl border border-border bg-surface p-6">
        <h1 class="text-lg font-semibold text-text mb-1">Create your account</h1>
        <p class="text-sm text-text-secondary mb-5">Apply for a loan and manage repayments in one place.</p>

        <form class="space-y-4" @submit.prevent="handleSubmit">
          <ErrorBanner :message="error" />

          <div class="grid grid-cols-2 gap-3">
            <div class="col-span-2">
              <label class="block text-sm font-medium text-text mb-1.5">Full name</label>
              <input v-model="form.name" required class="input" />
            </div>
            <div class="col-span-2">
              <label class="block text-sm font-medium text-text mb-1.5">Email</label>
              <input v-model="form.email" type="email" required class="input" />
            </div>
            <div>
              <label class="block text-sm font-medium text-text mb-1.5">Password</label>
              <input v-model="form.password" type="password" required class="input" />
            </div>
            <div>
              <label class="block text-sm font-medium text-text mb-1.5">Confirm</label>
              <input v-model="form.password_confirmation" type="password" required class="input" />
            </div>
            <div>
              <label class="block text-sm font-medium text-text mb-1.5">Phone</label>
              <input v-model="form.phone" required placeholder="2547XXXXXXXX" class="input" />
            </div>
            <div>
              <label class="block text-sm font-medium text-text mb-1.5">National ID</label>
              <input v-model="form.national_id" required class="input" />
            </div>
            <div>
              <label class="block text-sm font-medium text-text mb-1.5">Date of birth</label>
              <input v-model="form.date_of_birth" type="date" required class="input" />
            </div>
            <div>
              <label class="block text-sm font-medium text-text mb-1.5">Monthly income (KES)</label>
              <input v-model="form.monthly_income" type="number" min="0" required class="input" />
            </div>
            <div class="col-span-2">
              <label class="block text-sm font-medium text-text mb-1.5">Employment status</label>
              <select v-model="form.employment_status" class="input">
                <option value="employed">Employed</option>
                <option value="self_employed">Self-employed</option>
                <option value="unemployed">Unemployed</option>
              </select>
            </div>
          </div>

          <AppButton type="submit" class="w-full" :loading="loading">Create account</AppButton>
        </form>
      </div>

      <p class="text-center text-sm text-text-secondary mt-5">
        Already have an account?
        <RouterLink :to="{ name: 'login' }" class="text-primary font-medium hover:underline">Sign in</RouterLink>
      </p>
    </div>
  </div>
</template>

<style scoped>
.input {
  width: 100%;
  border-radius: 0.5rem;
  border: 1px solid var(--color-border);
  background-color: var(--color-bg);
  padding: 0.5rem 0.75rem;
  font-size: 0.875rem;
  color: var(--color-text);
}
.input:focus {
  outline: none;
  border-color: #4F46E5;
}
</style>
