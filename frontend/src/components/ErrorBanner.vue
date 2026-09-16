<script setup>
defineProps({ message: { type: String, default: '' } })
</script>

<template>
  <div v-if="message" class="rounded-lg border border-danger/30 bg-danger/10 px-4 py-3 text-sm text-danger">
    {{ message }}
  </div>
</template>
