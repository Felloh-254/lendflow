<script setup>
defineProps({
  title: { type: String, required: true },
  description: { type: String, default: '' },
})
</script>

<template>
  <div class="text-center py-16 px-6">
    <p class="text-sm font-medium text-text">{{ title }}</p>
    <p v-if="description" class="text-sm text-text-secondary mt-1">{{ description }}</p>
    <div v-if="$slots.action" class="mt-4">
      <slot name="action" />
    </div>
  </div>
</template>
