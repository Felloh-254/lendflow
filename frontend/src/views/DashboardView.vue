<script setup>
import { ref, onMounted, computed } from 'vue'
import { RouterLink } from 'vue-router'
import { api } from '../api/client'
import { useAuthStore } from '../stores/auth'
import { formatMoney, formatDate } from '../utils/format'
import AppCard from '../components/AppCard.vue'
import StatusPill from '../components/StatusPill.vue'
import EmptyState from '../components/EmptyState.vue'

const auth = useAuthStore()
const recentApplications = ref([])
const recentLoans = ref([])
const loading = ref(true)

const greeting = computed(() => {
  const hour = new Date().getHours()
  if (hour < 12) return 'Good morning'
  if (hour < 18) return 'Good afternoon'
  return 'Good evening'
})

onMounted(async () => {
  try {
    const [appsRes, loansRes] = await Promise.all([
      api.get('/loan-applications', { params: { per_page: 5 } }),
      api.get('/loans', { params: { per_page: 5 } }),
    ])
    recentApplications.value = appsRes.data.data
    recentLoans.value = loansRes.data.data
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="space-y-6">
    <div>
      <h2 class="text-xl font-semibold text-text">{{ greeting }}, {{ auth.user?.name?.split(' ')[0] }}</h2>
      <p class="text-sm text-text-secondary mt-1">
        <span v-if="auth.isCustomer">Here's where things stand with your loans.</span>
        <span v-else-if="auth.role === 'loan_officer'">Applications waiting on your review, at a glance.</span>
        <span v-else>An overview of the current loan portfolio.</span>
      </p>
    </div>

    <div class="flex flex-wrap gap-3">
      <RouterLink
        v-if="auth.isCustomer"
        :to="{ name: 'loan-application-new' }"
        class="inline-flex items-center gap-2 rounded-lg bg-primary text-white px-4 py-2 text-sm font-medium hover:bg-primary/90"
      >
        Apply for a loan
      </RouterLink>
      <RouterLink
        :to="{ name: 'loan-applications' }"
        class="inline-flex items-center gap-2 rounded-lg border border-border bg-surface px-4 py-2 text-sm font-medium hover:bg-bg"
      >
        View applications
      </RouterLink>
      <RouterLink
        :to="{ name: 'loans' }"
        class="inline-flex items-center gap-2 rounded-lg border border-border bg-surface px-4 py-2 text-sm font-medium hover:bg-bg"
      >
        View loans
      </RouterLink>
    </div>

    <div class="grid md:grid-cols-2 gap-5">
      <AppCard>
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-sm font-semibold text-text">Recent applications</h3>
          <RouterLink :to="{ name: 'loan-applications' }" class="text-xs text-primary hover:underline">View all</RouterLink>
        </div>
        <EmptyState
          v-if="!loading && recentApplications.length === 0"
          title="No applications yet"
          :description="auth.isCustomer ? 'Apply for your first loan to see it here.' : 'Nothing has come through yet.'"
        />
        <ul v-else class="divide-y divide-border">
          <li v-for="a in recentApplications" :key="a.id" class="py-3 first:pt-0 last:pb-0">
            <RouterLink :to="{ name: 'loan-application-detail', params: { id: a.id } }" class="flex items-center justify-between gap-3 group">
              <div class="min-w-0">
                <p class="text-sm font-medium text-text truncate group-hover:text-primary">{{ a.loan_product?.name || 'Loan application' }}</p>
                <p class="text-xs text-text-secondary tabular-figures">{{ formatMoney(a.amount_requested) }} · {{ formatDate(a.created_at) }}</p>
              </div>
              <StatusPill :status="a.status" />
            </RouterLink>
          </li>
        </ul>
      </AppCard>

      <AppCard>
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-sm font-semibold text-text">Recent loans</h3>
          <RouterLink :to="{ name: 'loans' }" class="text-xs text-primary hover:underline">View all</RouterLink>
        </div>
        <EmptyState
          v-if="!loading && recentLoans.length === 0"
          title="No loans yet"
          description="Approved applications turn into loans here."
        />
        <ul v-else class="divide-y divide-border">
          <li v-for="l in recentLoans" :key="l.id" class="py-3 first:pt-0 last:pb-0">
            <RouterLink :to="{ name: 'loan-detail', params: { id: l.id } }" class="flex items-center justify-between gap-3 group">
              <div class="min-w-0">
                <p class="text-sm font-medium text-text truncate group-hover:text-primary tabular-figures">{{ formatMoney(l.principal_amount) }}</p>
                <p class="text-xs text-text-secondary tabular-figures">Outstanding {{ formatMoney(l.outstanding_principal + l.outstanding_interest + l.outstanding_fees) }}</p>
              </div>
              <StatusPill :status="l.status" />
            </RouterLink>
          </li>
        </ul>
      </AppCard>
    </div>
  </div>
</template>
