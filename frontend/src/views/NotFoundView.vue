<script setup>
import { RouterLink } from 'vue-router'
</script>

<template>
  <div class="min-h-screen flex items-center justify-center bg-bg text-text px-4">
    <div class="text-center">
      <p class="text-sm text-text-secondary mb-2">Page not found</p>
      <h1 class="text-2xl font-semibold mb-4">This page doesn't exist.</h1>
      <RouterLink to="/" class="text-primary text-sm font-medium hover:underline">Back to dashboard</RouterLink>
    </div>
  </div>
</template>
