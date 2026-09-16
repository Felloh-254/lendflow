<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import { api } from '../api/client'
import { apiErrorMessage } from '../utils/apiError'
import { formatMoney } from '../utils/format'
import AppCard from '../components/AppCard.vue'
import AppButton from '../components/AppButton.vue'
import ErrorBanner from '../components/ErrorBanner.vue'

const router = useRouter()
const products = ref([])
const selectedProductId = ref(null)
const amount = ref('')
const termMonths = ref('')
const purpose = ref('')
const loading = ref(false)
const loadingProducts = ref(true)
const error = ref('')

const selectedProduct = computed(() => products.value.find((p) => p.id === selectedProductId.value))

onMounted(async () => {
  try {
    const { data } = await api.get('/loan-products')
    products.value = data.data
  } finally {
    loadingProducts.value = false
  }
})

async function handleSubmit() {
  loading.value = true
  error.value = ''
  try {
    const { data } = await api.post('/loan-applications', {
      loan_product_id: selectedProductId.value,
      amount_requested: amount.value,
      term_months: termMonths.value,
      purpose: purpose.value,
    })
    router.push({ name: 'loan-application-detail', params: { id: data.id } })
  } catch (e) {
    error.value = apiErrorMessage(e)
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="max-w-lg">
    <AppCard>
      <h2 class="text-base font-semibold text-text mb-1">New loan application</h2>
      <p class="text-sm text-text-secondary mb-5">This creates a draft — you can review and submit it for assessment afterward.</p>

      <form class="space-y-4" @submit.prevent="handleSubmit">
        <ErrorBanner :message="error" />

        <div>
          <label class="block text-sm font-medium text-text mb-1.5">Loan product</label>
          <select v-model="selectedProductId" required class="input" :disabled="loadingProducts">
            <option :value="null" disabled>Select a product</option>
            <option v-for="p in products" :key="p.id" :value="p.id">{{ p.name }} — {{ p.interest_rate }}% APR</option>
          </select>
          <p v-if="selectedProduct" class="text-xs text-text-secondary mt-1.5">
            {{ formatMoney(selectedProduct.min_amount) }}–{{ formatMoney(selectedProduct.max_amount) }},
            {{ selectedProduct.term_min }}–{{ selectedProduct.term_max }} months
          </p>
        </div>

        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-sm font-medium text-text mb-1.5">Amount (KES)</label>
            <input v-model="amount" type="number" min="1" step="0.01" required class="input" />
          </div>
          <div>
            <label class="block text-sm font-medium text-text mb-1.5">Term (months)</label>
            <input v-model="termMonths" type="number" min="1" required class="input" />
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium text-text mb-1.5">Purpose</label>
          <textarea v-model="purpose" required rows="3" class="input resize-none" placeholder="What's this loan for?" />
        </div>

        <AppButton type="submit" class="w-full" :loading="loading">Create draft application</AppButton>
      </form>
    </AppCard>
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
