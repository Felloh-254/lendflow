<script setup>
import { ref, onMounted, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { api } from '../api/client'
import { useAuthStore } from '../stores/auth'
import { formatMoney, formatDate } from '../utils/format'
import StatusPill from '../components/StatusPill.vue'
import EmptyState from '../components/EmptyState.vue'
import AppButton from '../components/AppButton.vue'

const auth = useAuthStore()
const applications = ref([])
const loading = ref(true)
const statusFilter = ref('')
const page = ref(1)
const meta = ref(null)

const STATUSES = ['draft', 'submitted', 'under_review', 'approved', 'rejected', 'cancelled']

async function load() {
  loading.value = true
  try {
    const { data } = await api.get('/loan-applications', {
      params: { status: statusFilter.value || undefined, page: page.value, per_page: 15 },
    })
    applications.value = data.data
    meta.value = data.meta
  } finally {
    loading.value = false
  }
}

watch([statusFilter, page], load)
onMounted(load)
</script>

<template>
  <div class="space-y-5">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div class="flex items-center gap-2">
        <select v-model="statusFilter" class="rounded-lg border border-border bg-surface px-3 py-1.5 text-sm text-text">
          <option value="">All statuses</option>
          <option v-for="s in STATUSES" :key="s" :value="s" class="capitalize">{{ s.replaceAll('_', ' ') }}</option>
        </select>
      </div>
      <RouterLink v-if="auth.isCustomer" :to="{ name: 'loan-application-new' }">
        <AppButton>New application</AppButton>
      </RouterLink>
    </div>

    <div class="rounded-xl border border-border bg-surface overflow-hidden">
      <EmptyState
        v-if="!loading && applications.length === 0"
        title="No applications found"
        description="Try a different filter, or create a new application."
      />

      <table v-else class="w-full text-sm">
        <thead>
          <tr class="border-b border-border text-left text-text-secondary">
            <th class="px-4 py-3 font-medium">Product</th>
            <th class="px-4 py-3 font-medium">Amount</th>
            <th class="px-4 py-3 font-medium">Term</th>
            <th class="px-4 py-3 font-medium">Status</th>
            <th class="px-4 py-3 font-medium">Submitted</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="a in applications"
            :key="a.id"
            class="border-b border-border last:border-0 hover:bg-bg cursor-pointer"
            @click="$router.push({ name: 'loan-application-detail', params: { id: a.id } })"
          >
            <td class="px-4 py-3 font-medium text-text">{{ a.loan_product?.name || '—' }}</td>
            <td class="px-4 py-3 tabular-figures text-text">{{ formatMoney(a.amount_requested) }}</td>
            <td class="px-4 py-3 text-text-secondary">{{ a.term_months }} mo</td>
            <td class="px-4 py-3"><StatusPill :status="a.status" /></td>
            <td class="px-4 py-3 text-text-secondary">{{ formatDate(a.submitted_at) }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="meta && meta.last_page > 1" class="flex items-center justify-between text-sm text-text-secondary">
      <span>Page {{ meta.current_page }} of {{ meta.last_page }}</span>
      <div class="flex gap-2">
        <button
          class="rounded-lg border border-border px-3 py-1.5 disabled:opacity-40"
          :disabled="page <= 1"
          @click="page--"
        >Previous</button>
        <button
          class="rounded-lg border border-border px-3 py-1.5 disabled:opacity-40"
          :disabled="page >= meta.last_page"
          @click="page++"
        >Next</button>
      </div>
    </div>
  </div>
</template>
