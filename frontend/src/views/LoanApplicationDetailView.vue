<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { api } from '../api/client'
import { useAuthStore } from '../stores/auth'
import { apiErrorMessage } from '../utils/apiError'
import { formatMoney, formatDate } from '../utils/format'
import AppCard from '../components/AppCard.vue'
import AppButton from '../components/AppButton.vue'
import StatusPill from '../components/StatusPill.vue'
import ErrorBanner from '../components/ErrorBanner.vue'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

const application = ref(null)
const loading = ref(true)
const actionLoading = ref('')
const error = ref('')
const rejectReason = ref('')
const showRejectForm = ref(false)

async function load() {
  loading.value = true
  try {
    const { data } = await api.get(`/loan-applications/${route.params.id}`)
    application.value = data
  } catch (e) {
    error.value = apiErrorMessage(e)
  } finally {
    loading.value = false
  }
}

onMounted(load)

const canSubmit = computed(() => auth.isCustomer && application.value?.status === 'draft')
const canCancel = computed(() => auth.isCustomer && ['draft', 'submitted'].includes(application.value?.status))
const canAssess = computed(() => auth.role === 'loan_officer' && ['submitted', 'under_review'].includes(application.value?.status))
const canDecide = computed(() => auth.canApprove && application.value?.status === 'under_review')

async function runAction(action, payload) {
  actionLoading.value = action
  error.value = ''
  try {
    const { data } = await api.post(`/loan-applications/${route.params.id}/${action}`, payload)
    application.value = data
    showRejectForm.value = false
  } catch (e) {
    error.value = apiErrorMessage(e)
  } finally {
    actionLoading.value = ''
  }
}

async function goToLoan() {
  // After approval, find the loan created for this application.
  const { data } = await api.get('/loans', { params: { per_page: 50 } })
  const loan = data.data.find((l) => l.loan_application_id === application.value.id)
  if (loan) router.push({ name: 'loan-detail', params: { id: loan.id } })
}
</script>

<template>
  <div v-if="loading" class="text-sm text-text-secondary">Loading…</div>

  <div v-else-if="application" class="max-w-2xl space-y-5">
    <ErrorBanner :message="error" />

    <AppCard>
      <div class="flex items-start justify-between mb-4">
        <div>
          <h2 class="text-base font-semibold text-text">{{ application.loan_product?.name }}</h2>
          <p class="text-xs text-text-secondary mt-0.5">Application #{{ application.id }}</p>
        </div>
        <StatusPill :status="application.status" />
      </div>

      <dl class="grid grid-cols-2 gap-y-3 text-sm">
        <dt class="text-text-secondary">Amount requested</dt>
        <dd class="text-text tabular-figures text-right">{{ formatMoney(application.amount_requested) }}</dd>
        <dt class="text-text-secondary">Term</dt>
        <dd class="text-text text-right">{{ application.term_months }} months</dd>
        <dt class="text-text-secondary">Purpose</dt>
        <dd class="text-text text-right">{{ application.purpose }}</dd>
        <dt class="text-text-secondary">Submitted</dt>
        <dd class="text-text text-right">{{ formatDate(application.submitted_at) }}</dd>
      </dl>
    </AppCard>

    <AppCard v-if="application.credit_assessment?.credit_score">
      <h3 class="text-sm font-semibold text-text mb-3">Credit assessment</h3>
      <dl class="grid grid-cols-2 gap-y-3 text-sm">
        <dt class="text-text-secondary">Credit score</dt>
        <dd class="text-text tabular-figures text-right">{{ application.credit_assessment.credit_score }}</dd>
        <dt class="text-text-secondary">Debt-to-income ratio</dt>
        <dd class="text-text tabular-figures text-right">{{ (application.credit_assessment.debt_to_income_ratio * 100).toFixed(1) }}%</dd>
        <dt class="text-text-secondary">Risk level</dt>
        <dd class="text-right"><StatusPill :status="application.credit_assessment.risk_level" /></dd>
        <dt class="text-text-secondary">Recommendation</dt>
        <dd class="text-right"><StatusPill :status="application.credit_assessment.recommendation" /></dd>
      </dl>
    </AppCard>

    <AppCard v-if="application.status === 'approved'">
      <div class="flex items-center justify-between">
        <p class="text-sm text-text">Approved — a loan has been created.</p>
        <AppButton variant="secondary" @click="goToLoan">View loan</AppButton>
      </div>
    </AppCard>

    <div v-if="canSubmit || canCancel || canAssess || canDecide" class="flex flex-wrap gap-2">
      <AppButton v-if="canSubmit" :loading="actionLoading === 'submit'" @click="runAction('submit')">
        Submit for review
      </AppButton>
      <AppButton v-if="canAssess" variant="secondary" :loading="actionLoading === 'assess'" @click="runAction('assess')">
        Run credit assessment
      </AppButton>
      <AppButton v-if="canDecide" :loading="actionLoading === 'approve'" @click="runAction('approve')">
        Approve
      </AppButton>
      <AppButton v-if="canDecide && !showRejectForm" variant="danger" @click="showRejectForm = true">
        Reject
      </AppButton>
      <AppButton v-if="canCancel" variant="ghost" :loading="actionLoading === 'cancel'" @click="runAction('cancel')">
        Cancel application
      </AppButton>
    </div>

    <AppCard v-if="showRejectForm">
      <label class="block text-sm font-medium text-text mb-1.5">Reason (optional)</label>
      <textarea v-model="rejectReason" rows="2" class="w-full rounded-lg border border-border bg-bg px-3 py-2 text-sm text-text resize-none" />
      <div class="flex gap-2 mt-3">
        <AppButton variant="danger" :loading="actionLoading === 'reject'" @click="runAction('reject', { reason: rejectReason })">
          Confirm rejection
        </AppButton>
        <AppButton variant="ghost" @click="showRejectForm = false">Cancel</AppButton>
      </div>
    </AppCard>
  </div>
</template>
