<script setup>
import { ref, onMounted } from 'vue'
import { api } from '../api/client'
import { useAuthStore } from '../stores/auth'
import { apiErrorMessage } from '../utils/apiError'
import { formatDate } from '../utils/format'
import StatusPill from '../components/StatusPill.vue'
import ErrorBanner from '../components/ErrorBanner.vue'

const auth = useAuthStore()
const users = ref([])
const loading = ref(true)
const error = ref('')
const savingId = ref(null)

const ROLES = ['customer', 'loan_officer', 'manager', 'admin']

async function load() {
  loading.value = true
  try {
    const { data } = await api.get('/admin/users', { params: { per_page: 50 } })
    users.value = data.data
  } finally {
    loading.value = false
  }
}
onMounted(load)

async function updateUser(user, changes) {
  savingId.value = user.id
  error.value = ''
  try {
    const { data } = await api.patch(`/admin/users/${user.id}`, changes)
    Object.assign(user, data)
  } catch (e) {
    error.value = apiErrorMessage(e)
  } finally {
    savingId.value = null
  }
}
</script>

<template>
  <div class="space-y-5">
    <ErrorBanner :message="error" />

    <div class="rounded-xl border border-border bg-surface overflow-hidden">
      <table class="w-full text-sm">
        <thead>
          <tr class="border-b border-border text-left text-text-secondary">
            <th class="px-4 py-3 font-medium">Name</th>
            <th class="px-4 py-3 font-medium">Email</th>
            <th class="px-4 py-3 font-medium">Role</th>
            <th class="px-4 py-3 font-medium">Status</th>
            <th class="px-4 py-3 font-medium">Joined</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="u in users" :key="u.id" class="border-b border-border last:border-0">
            <td class="px-4 py-3 text-text font-medium">{{ u.name }}</td>
            <td class="px-4 py-3 text-text-secondary">{{ u.email }}</td>
            <td class="px-4 py-3">
              <select
                :value="u.role"
                :disabled="u.id === auth.user.id || savingId === u.id"
                class="rounded-lg border border-border bg-bg px-2 py-1 text-xs text-text disabled:opacity-50"
                @change="updateUser(u, { role: $event.target.value })"
              >
                <option v-for="r in ROLES" :key="r" :value="r">{{ r.replaceAll('_', ' ') }}</option>
              </select>
            </td>
            <td class="px-4 py-3">
              <button
                :disabled="u.id === auth.user.id || savingId === u.id"
                class="disabled:opacity-50"
                @click="updateUser(u, { status: u.status === 'active' ? 'suspended' : 'active' })"
              >
                <StatusPill :status="u.status" />
              </button>
            </td>
            <td class="px-4 py-3 text-text-secondary">{{ formatDate(u.created_at) }}</td>
          </tr>
        </tbody>
      </table>
    </div>
    <p class="text-xs text-text-secondary">Click a status pill to toggle it. You can't change your own account here.</p>
  </div>
</template>
