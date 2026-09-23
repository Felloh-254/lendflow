<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRoute } from 'vue-router'
import { api } from '../api/client'
import { useAuthStore } from '../stores/auth'
import { apiErrorMessage } from '../utils/apiError'
import { formatMoney, formatDate, formatDateTime } from '../utils/format'
import AppCard from '../components/AppCard.vue'
import AppButton from '../components/AppButton.vue'
import StatusPill from '../components/StatusPill.vue'
import ErrorBanner from '../components/ErrorBanner.vue'
import EmptyState from '../components/EmptyState.vue'

const route = useRoute()
const auth = useAuthStore()

const loan = ref(null)
const schedule = ref([])
const transactions = ref([])
const loading = ref(true)
const error = ref('')
const disbursing = ref(false)
const disbursementIdempotencyKey = ref(null)
const disbursementAttempt = ref(null)

const repayAmount = ref('')
const repayMethod = ref('mpesa')
const repayLoading = ref(false)
const repayError = ref('')
const repaymentIdempotencyKey = ref(null)
const repaymentAttempt = ref(null)


async function loadAll() {
  loading.value = true
  try {
    const [loanRes, scheduleRes, txRes] = await Promise.all([
      api.get(`/loans/${route.params.id}`),
      api.get(`/loans/${route.params.id}/schedule`),
      api.get('/transactions', { params: { loan_id: route.params.id, per_page: 20 } }),
    ])
    loan.value = loanRes.data
    schedule.value = scheduleRes.data.data
    transactions.value = txRes.data.data
  } catch (e) {
    error.value = apiErrorMessage(e)
  } finally {
    loading.value = false
  }
}

onMounted(loadAll)

const totalOutstanding = computed(() => {
  if (!loan.value) return 0
  return Number(loan.value.outstanding_principal) + Number(loan.value.outstanding_interest) + Number(loan.value.outstanding_fees)
})

const canDisburse = computed(() => auth.canApprove && loan.value?.status === 'approved')
const canRepay = computed(() => auth.isCustomer && ['active', 'overdue'].includes(loan.value?.status))

async function disburse() {
  disbursing.value = true
  error.value = ''

  try {
    if (!disbursementAttempt.value) {
      disbursementAttempt.value = {
        idempotencyKey: crypto.randomUUID(),
      }
    }

    const { data } = await api.post(
      `/loans/${route.params.id}/disburse`,
      null,
      {
        headers: {
          'Idempotency-Key': disbursementAttempt.value.idempotencyKey,
        },
      }
    )

    loan.value = data

    // The disbursement succeeded.
    // This attempt is now complete.
    disbursementAttempt.value = null

    await loadAll()
  } catch (e) {
    // Keep the same idempotency key.
    // If the user retries, the same transaction key is reused.
    error.value = apiErrorMessage(e)
  } finally {
    disbursing.value = false
  }
}

async function makeRepayment() {
  repayLoading.value = true
  repayError.value = ''

  try {
    const amount = repayAmount.value
    const method = repayMethod.value

    // Create a new transaction attempt if:
    // 1. There isn't one yet, OR
    // 2. The user changed the amount, OR
    // 3. The user changed the payment method.
    if (
      !repaymentAttempt.value ||
      repaymentAttempt.value.amount !== amount ||
      repaymentAttempt.value.method !== method
    ) {
      repaymentAttempt.value = {
        amount,
        method,
        idempotencyKey: crypto.randomUUID(),
      }
    }

    await api.post(
      `/loans/${route.params.id}/repayments`,
      {
        amount: repaymentAttempt.value.amount,
        payment_method: repaymentAttempt.value.method,
      },
      {
        headers: {
          'Idempotency-Key': repaymentAttempt.value.idempotencyKey,
        },
      }
    )

    // Successful repayment.
    repayAmount.value = ''
    repaymentAttempt.value = null

    await loadAll()
  } catch (e) {
    // DO NOT clear repaymentAttempt here.
    // A retry must use the same idempotency key.
    repayError.value = apiErrorMessage(e)
  } finally {
    repayLoading.value = false
  }
}
</script>

<template>
  <div v-if="loading" class="text-sm text-text-secondary">Loading…</div>

  <div v-else-if="loan" class="max-w-3xl space-y-5">
    <ErrorBanner :message="error" />

    <AppCard>
      <div class="flex items-start justify-between mb-4">
        <div>
          <h2 class="text-base font-semibold text-text tabular-figures">{{ formatMoney(loan.principal_amount) }}</h2>
          <p class="text-xs text-text-secondary mt-0.5">Loan #{{ loan.id }} · {{ loan.loan_product?.name }}</p>
        </div>
        <StatusPill :status="loan.status" />
      </div>

      <dl class="grid grid-cols-2 gap-y-3 text-sm mb-4">
        <dt class="text-text-secondary">Principal</dt>
        <dd class="text-text tabular-figures text-right">{{ formatMoney(loan.principal_amount) }}</dd>
        <dt class="text-text-secondary">Interest</dt>
        <dd class="text-text tabular-figures text-right">{{ formatMoney(loan.interest_amount) }}</dd>
        <dt class="text-text-secondary">Total outstanding</dt>
        <dd class="text-text tabular-figures text-right font-medium">{{ formatMoney(totalOutstanding) }}</dd>
        <dt class="text-text-secondary">Disbursed</dt>
        <dd class="text-text text-right">{{ formatDate(loan.disbursed_at) }}</dd>
      </dl>

      <AppButton v-if="canDisburse" :loading="disbursing" @click="disburse">Disburse loan</AppButton>
    </AppCard>

    <AppCard v-if="canRepay">
      <h3 class="text-sm font-semibold text-text mb-3">Make a repayment</h3>
      <ErrorBanner :message="repayError" />
      <form class="flex flex-wrap items-end gap-3 mt-3" @submit.prevent="makeRepayment">
        <div>
          <label class="block text-xs font-medium text-text-secondary mb-1.5">Amount (KES)</label>
          <input
            v-model="repayAmount"
            type="number"
            min="0.01"
            step="0.01"
            required
            class="rounded-lg border border-border bg-bg px-3 py-2 text-sm text-text w-40"
          />
        </div>
        <div>
          <label class="block text-xs font-medium text-text-secondary mb-1.5">Method</label>
          <select v-model="repayMethod" class="rounded-lg border border-border bg-bg px-3 py-2 text-sm text-text">
            <option value="mpesa">M-Pesa</option>
            <option value="bank">Bank</option>
            <option value="wallet">Wallet</option>
          </select>
        </div>
        <AppButton type="submit" :loading="repayLoading">Pay</AppButton>
      </form>
    </AppCard>

    <AppCard>
      <h3 class="text-sm font-semibold text-text mb-3">Repayment schedule</h3>
      <EmptyState v-if="schedule.length === 0" title="No schedule yet" description="The schedule is generated when the loan is disbursed." />
      <div v-else class="overflow-x-auto -mx-5">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-border text-left text-text-secondary">
              <th class="px-5 py-2 font-medium">#</th>
              <th class="px-5 py-2 font-medium">Due</th>
              <th class="px-5 py-2 font-medium text-right">Principal</th>
              <th class="px-5 py-2 font-medium text-right">Interest</th>
              <th class="px-5 py-2 font-medium text-right">Penalty</th>
              <th class="px-5 py-2 font-medium text-right">Total</th>
              <th class="px-5 py-2 font-medium">Status</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="s in schedule" :key="s.id" class="border-b border-border last:border-0">
              <td class="px-5 py-2.5 text-text-secondary">{{ s.installment_number }}</td>
              <td class="px-5 py-2.5 text-text-secondary">{{ formatDate(s.due_date) }}</td>
              <td class="px-5 py-2.5 text-right tabular-figures text-text">{{ formatMoney(s.principal_due) }}</td>
              <td class="px-5 py-2.5 text-right tabular-figures text-text">{{ formatMoney(s.interest_due) }}</td>
              <td class="px-5 py-2.5 text-right tabular-figures" :class="s.penalty_due > 0 ? 'text-danger' : 'text-text-secondary'">{{ formatMoney(s.penalty_due) }}</td>
              <td class="px-5 py-2.5 text-right tabular-figures font-medium text-text">{{ formatMoney(s.total_due) }}</td>
              <td class="px-5 py-2.5"><StatusPill :status="s.status" /></td>
            </tr>
          </tbody>
        </table>
      </div>
    </AppCard>

    <AppCard>
      <h3 class="text-sm font-semibold text-text mb-3">Transactions</h3>
      <EmptyState v-if="transactions.length === 0" title="No transactions yet" />
      <ul v-else class="divide-y divide-border">
        <li v-for="t in transactions" :key="t.id" class="py-3 first:pt-0 last:pb-0 flex items-center justify-between">
          <div>
            <p class="text-sm text-text capitalize">{{ t.type }}</p>
            <p class="text-xs text-text-secondary">{{ formatDateTime(t.created_at) }} · {{ t.reference }}</p>
          </div>
          <p class="text-sm font-medium tabular-figures text-text">{{ formatMoney(t.amount) }}</p>
        </li>
      </ul>
    </AppCard>
  </div>
</template>
