<script setup>
import { useTheme } from '../composables/useTheme'

const { theme, setTheme } = useTheme()

const options = [
  { value: 'light', label: 'Light' },
  { value: 'dark', label: 'Dark' },
  { value: 'system', label: 'Auto' },
]
</script>

<template>
  <div class="flex items-center rounded-lg border border-border p-0.5 bg-bg text-xs">
    <button
      v-for="opt in options"
      :key="opt.value"
      class="px-2.5 py-1 rounded-md transition-colors"
      :class="theme === opt.value ? 'bg-surface shadow-sm text-text font-medium' : 'text-text-secondary hover:text-text'"
      @click="setTheme(opt.value)"
    >
      {{ opt.label }}
    </button>
  </div>
</template>
