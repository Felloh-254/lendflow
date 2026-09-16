<script setup>
import { ref, onMounted, watch } from 'vue'
import { api } from '../api/client'
import { formatMoney } from '../utils/format'
import StatusPill from '../components/StatusPill.vue'
import EmptyState from '../components/EmptyState.vue'

const loans = ref([])
const loading = ref(true)
const statusFilter = ref('')
const page = ref(1)
const meta = ref(null)

const STATUSES = ['approved', 'pending_disbursement', 'active', 'overdue', 'completed', 'defaulted', 'cancelled']

async function load() {
  loading.value = true
  try {
    const { data } = await api.get('/loans', {
      params: { status: statusFilter.value || undefined, page: page.value, per_page: 15 },
    })
    loans.value = data.data
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
    <select v-model="statusFilter" class="rounded-lg border border-border bg-surface px-3 py-1.5 text-sm text-text">
      <option value="">All statuses</option>
      <option v-for="s in STATUSES" :key="s" :value="s" class="capitalize">{{ s.replaceAll('_', ' ') }}</option>
    </select>

    <div class="rounded-xl border border-border bg-surface overflow-hidden">
      <EmptyState v-if="!loading && loans.length === 0" title="No loans found" description="Try a different filter." />

      <table v-else class="w-full text-sm">
        <thead>
          <tr class="border-b border-border text-left text-text-secondary">
            <th class="px-4 py-3 font-medium">Principal</th>
            <th class="px-4 py-3 font-medium">Outstanding</th>
            <th class="px-4 py-3 font-medium">Status</th>
            <th class="px-4 py-3 font-medium">Disbursed</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="l in loans"
            :key="l.id"
            class="border-b border-border last:border-0 hover:bg-bg cursor-pointer"
            @click="$router.push({ name: 'loan-detail', params: { id: l.id } })"
          >
            <td class="px-4 py-3 font-medium text-text tabular-figures">{{ formatMoney(l.principal_amount) }}</td>
            <td class="px-4 py-3 tabular-figures text-text">{{ formatMoney(l.outstanding_principal + l.outstanding_interest + l.outstanding_fees) }}</td>
            <td class="px-4 py-3"><StatusPill :status="l.status" /></td>
            <td class="px-4 py-3 text-text-secondary">{{ l.disbursed_at ? new Date(l.disbursed_at).toLocaleDateString() : '—' }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="meta && meta.last_page > 1" class="flex items-center justify-between text-sm text-text-secondary">
      <span>Page {{ meta.current_page }} of {{ meta.last_page }}</span>
      <div class="flex gap-2">
        <button class="rounded-lg border border-border px-3 py-1.5 disabled:opacity-40" :disabled="page <= 1" @click="page--">Previous</button>
        <button class="rounded-lg border border-border px-3 py-1.5 disabled:opacity-40" :disabled="page >= meta.last_page" @click="page++">Next</button>
      </div>
    </div>
  </div>
</template>
