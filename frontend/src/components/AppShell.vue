<script setup>
import { ref, computed } from 'vue'
import { useRoute, RouterLink } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { useTheme } from '../composables/useTheme'
import ThemeToggle from './ThemeToggle.vue'

const auth = useAuthStore()
const route = useRoute()
const { theme } = useTheme()
const mobileNavOpen = ref(false)

const navItems = computed(() => {
  const items = [
    { name: 'dashboard', label: 'Overview', icon: 'home' },
    { name: 'loan-applications', label: 'Applications', icon: 'file' },
    { name: 'loans', label: 'Loans', icon: 'briefcase' },
    { name: 'loan-products', label: 'Products', icon: 'tag' },
  ]
  if (auth.isAdmin) {
    items.push({ name: 'admin-users', label: 'Users', icon: 'users' })
  }
  return items
})

const pageTitle = computed(() => {
  const found = navItems.value.find((i) => i.name === route.name)
  if (found) return found.label
  if (route.name === 'loan-application-new') return 'New Application'
  if (route.name === 'loan-application-detail') return 'Application'
  if (route.name === 'loan-detail') return 'Loan'
  return 'LendFlow'
})

const roleLabel = computed(() => {
  const labels = { customer: 'Customer', loan_officer: 'Loan Officer', manager: 'Manager', admin: 'Admin' }
  return labels[auth.role] || auth.role
})

async function handleLogout() {
  await auth.logout()
  window.location.href = '/login'
}
</script>

<template>
  <div class="min-h-screen bg-bg text-text flex">
    <!-- Mobile overlay -->
    <div
      v-if="mobileNavOpen"
      class="fixed inset-0 bg-black/40 z-30 md:hidden"
      @click="mobileNavOpen = false"
    />

    <!-- Sidebar -->
    <aside
      class="fixed md:static inset-y-0 left-0 z-40 w-64 shrink-0 border-r border-border bg-surface transform transition-transform md:translate-x-0"
      :class="mobileNavOpen ? 'translate-x-0' : '-translate-x-full'"
    >
      <div class="h-16 flex items-center gap-2 px-5 border-b border-border">
        <div class="h-7 w-7 rounded-md bg-primary flex items-center justify-center text-white text-sm font-semibold">L</div>
        <span class="font-semibold tracking-tight">LendFlow</span>
      </div>

      <nav class="p-3 space-y-0.5">
        <RouterLink
          v-for="item in navItems"
          :key="item.name"
          :to="{ name: item.name }"
          class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm transition-colors"
          :class="route.name === item.name
            ? 'bg-primary/10 text-primary font-medium border-l-2 border-primary -ml-px pl-[11px]'
            : 'text-text-secondary hover:bg-bg hover:text-text'"
          @click="mobileNavOpen = false"
        >
          {{ item.label }}
        </RouterLink>
      </nav>

      <div class="absolute bottom-0 inset-x-0 p-3 border-t border-border">
        <div class="flex items-center gap-3 px-2 py-2">
          <div class="h-8 w-8 rounded-full bg-accent/15 text-accent flex items-center justify-center text-xs font-semibold shrink-0">
            {{ auth.user?.name?.[0]?.toUpperCase() }}
          </div>
          <div class="min-w-0 flex-1">
            <p class="text-sm font-medium truncate">{{ auth.user?.name }}</p>
            <p class="text-xs text-text-secondary">{{ roleLabel }}</p>
          </div>
          <button
            class="text-xs text-text-secondary hover:text-danger transition-colors"
            @click="handleLogout"
          >
            Log out
          </button>
        </div>
      </div>
    </aside>

    <!-- Main column -->
    <div class="flex-1 flex flex-col min-w-0">
      <header class="h-16 border-b border-border bg-surface flex items-center justify-between px-4 md:px-6 shrink-0">
        <div class="flex items-center gap-3">
          <button class="md:hidden text-text-secondary" @click="mobileNavOpen = true" aria-label="Open menu">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12h18M3 6h18M3 18h18"/></svg>
          </button>
          <h1 class="text-lg font-semibold tracking-tight">{{ pageTitle }}</h1>
        </div>
        <ThemeToggle />
      </header>

      <main class="flex-1 p-4 md:p-6 max-w-6xl w-full mx-auto">
        <slot />
      </main>
    </div>
  </div>
</template>
