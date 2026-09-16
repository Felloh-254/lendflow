<script setup>
defineProps({
  variant: { type: String, default: 'primary' }, // primary | secondary | danger | ghost
  loading: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },
  type: { type: String, default: 'button' },
})
defineEmits(['click'])

const variantClasses = {
  primary: 'bg-primary text-white hover:bg-primary/90 disabled:bg-primary/50',
  secondary: 'bg-surface border border-border text-text hover:bg-bg disabled:opacity-50',
  danger: 'bg-danger text-white hover:bg-danger/90 disabled:bg-danger/50',
  ghost: 'text-text-secondary hover:text-text hover:bg-bg disabled:opacity-50',
}
</script>

<template>
  <button
    :type="type"
    :disabled="disabled || loading"
    class="inline-flex items-center justify-center gap-2 rounded-lg px-3.5 py-2 text-sm font-medium transition-colors disabled:cursor-not-allowed"
    :class="variantClasses[variant]"
    @click="$emit('click', $event)"
  >
    <svg v-if="loading" class="animate-spin h-3.5 w-3.5" viewBox="0 0 24 24" fill="none">
      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
    </svg>
    <slot />
  </button>
</template>
