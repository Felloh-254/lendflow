<script setup>
import { ref, onMounted, reactive } from 'vue'
import { api } from '../api/client'
import { useAuthStore } from '../stores/auth'
import { apiErrorMessage } from '../utils/apiError'
import { formatMoney } from '../utils/format'
import AppCard from '../components/AppCard.vue'
import AppButton from '../components/AppButton.vue'
import StatusPill from '../components/StatusPill.vue'
import ErrorBanner from '../components/ErrorBanner.vue'

const auth = useAuthStore()
const products = ref([])
const loading = ref(true)
const error = ref('')
const showForm = ref(false)
const editingId = ref(null)
const formLoading = ref(false)

const blankForm = () => ({
  name: '', description: '', min_amount: '', max_amount: '',
  interest_rate: '', term_min: '', term_max: '', status: 'active',
})
const form = reactive(blankForm())

async function load() {
  loading.value = true
  try {
    const { data } = await api.get('/loan-products')
    products.value = data.data
  } finally {
    loading.value = false
  }
}
onMounted(load)

function startCreate() {
  Object.assign(form, blankForm())
  editingId.value = null
  showForm.value = true
}

function startEdit(product) {
  Object.assign(form, {
    name: product.name, description: product.description || '',
    min_amount: product.min_amount, max_amount: product.max_amount,
    interest_rate: product.interest_rate, term_min: product.term_min,
    term_max: product.term_max, status: product.status,
  })
  editingId.value = product.id
  showForm.value = true
}

async function submitForm() {
  formLoading.value = true
  error.value = ''
  try {
    if (editingId.value) {
      await api.patch(`/loan-products/${editingId.value}`, form)
    } else {
      await api.post('/loan-products', form)
    }
    showForm.value = false
    await load()
  } catch (e) {
    error.value = apiErrorMessage(e)
  } finally {
    formLoading.value = false
  }
}
</script>

<template>
  <div class="space-y-5">
    <ErrorBanner :message="error" />

    <div v-if="auth.isAdmin" class="flex justify-end">
      <AppButton v-if="!showForm" @click="startCreate">New product</AppButton>
    </div>

    <AppCard v-if="showForm">
      <h3 class="text-sm font-semibold text-text mb-3">{{ editingId ? 'Edit product' : 'New product' }}</h3>
      <form class="grid grid-cols-2 gap-3" @submit.prevent="submitForm">
        <div class="col-span-2">
          <label class="label">Name</label>
          <input v-model="form.name" required class="input" />
        </div>
        <div class="col-span-2">
          <label class="label">Description</label>
          <input v-model="form.description" class="input" />
        </div>
        <div>
          <label class="label">Min amount</label>
          <input v-model="form.min_amount" type="number" required class="input" />
        </div>
        <div>
          <label class="label">Max amount</label>
          <input v-model="form.max_amount" type="number" required class="input" />
        </div>
        <div>
          <label class="label">Interest rate (%)</label>
          <input v-model="form.interest_rate" type="number" step="0.01" required class="input" />
        </div>
        <div>
          <label class="label">Status</label>
          <select v-model="form.status" class="input">
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
          </select>
        </div>
        <div>
          <label class="label">Min term (months)</label>
          <input v-model="form.term_min" type="number" required class="input" />
        </div>
        <div>
          <label class="label">Max term (months)</label>
          <input v-model="form.term_max" type="number" required class="input" />
        </div>
        <div class="col-span-2 flex gap-2 pt-1">
          <AppButton type="submit" :loading="formLoading">{{ editingId ? 'Save changes' : 'Create product' }}</AppButton>
          <AppButton type="button" variant="ghost" @click="showForm = false">Cancel</AppButton>
        </div>
      </form>
    </AppCard>

    <div class="grid md:grid-cols-2 gap-4">
      <AppCard v-for="p in products" :key="p.id">
        <div class="flex items-start justify-between mb-2">
          <h3 class="text-sm font-semibold text-text">{{ p.name }}</h3>
          <StatusPill :status="p.status" />
        </div>
        <p class="text-xs text-text-secondary mb-3">{{ p.description }}</p>
        <dl class="text-sm space-y-1.5">
          <div class="flex justify-between">
            <dt class="text-text-secondary">Range</dt>
            <dd class="tabular-figures text-text">{{ formatMoney(p.min_amount) }} – {{ formatMoney(p.max_amount) }}</dd>
          </div>
          <div class="flex justify-between">
            <dt class="text-text-secondary">Interest rate</dt>
            <dd class="tabular-figures text-text">{{ p.interest_rate }}% / yr</dd>
          </div>
          <div class="flex justify-between">
            <dt class="text-text-secondary">Term</dt>
            <dd class="text-text">{{ p.term_min }}–{{ p.term_max }} months</dd>
          </div>
        </dl>
        <button v-if="auth.isAdmin" class="text-xs text-primary hover:underline mt-3" @click="startEdit(p)">Edit</button>
      </AppCard>
    </div>
  </div>
</template>

<style scoped>
.label { display: block; font-size: 0.75rem; font-weight: 500; color: var(--color-text-secondary); margin-bottom: 0.375rem; }
.input {
  width: 100%;
  border-radius: 0.5rem;
  border: 1px solid var(--color-border);
  background-color: var(--color-bg);
  padding: 0.5rem 0.75rem;
  font-size: 0.875rem;
  color: var(--color-text);
}
.input:focus { outline: none; border-color: #4F46E5; }
</style>
